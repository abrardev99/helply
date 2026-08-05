<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\SafeUrl;
use DOMDocument;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Uri;
use Throwable;

class CrawlSiteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * The maximum nesting depth to follow when a sitemap is a sitemap index.
     * Guards against malformed or self-referential sitemap-index loops.
     */
    private const MaxSitemapDepth = 3;

    /**
     * Roughly how many pages we intend to have crawling concurrently. See the dispatch
     * comment in handle(): real parallelism is governed by queue-worker concurrency.
     */
    private const PagesPerParallelGroup = 10;

    /**
     * Hard cap on how many pages a single crawl will ingest. Guards against enormous
     * sitemaps (and link-following crawls) exhausting the queue and the customer's
     * embedding budget.
     */
    public const MaxPages = 200;

    /**
     * @param  string  $agentId  The owning agent (UUID).
     * @param  string  $seedUrl  Any URL on the target site; used to locate sitemap.xml.
     */
    public function __construct(
        public string $agentId,
        public string $seedUrl,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $sitemapUrl = $this->sitemapUrlFor($this->seedUrl);

        $pageUrls = collect($this->collectUrls($sitemapUrl))
            ->push($this->seedUrl) // ensure the seed page itself is crawled
            ->map(fn (string $url): string => trim($url))
            ->filter()
            // Sitemap <loc> values are raw. Canonicalize them the same way link discovery
            // does, so `https://site.test` and `https://site.test/` (or `/x` and `/x/`)
            // never become two documents for one page.
            ->map(fn (string $url): string => SafeUrl::normalize($url))
            // Drop URLs that resolve to private/loopback/link-local targets or use a
            // non-http(s) scheme — an attacker-controlled sitemap could otherwise point
            // the crawler at internal services (SSRF).
            ->filter(fn (string $url): bool => SafeUrl::hasSafeTarget($url))
            ->unique()
            ->values();

        if ($pageUrls->count() > self::MaxPages) {
            Log::warning('CrawlSiteJob truncated an oversized sitemap.', [
                'agent_id' => $this->agentId,
                'discovered' => $pageUrls->count(),
                'limit' => self::MaxPages,
            ]);

            // Keep the seed page and fill the rest of the budget with discovered pages by
            // sorting the seed to the front before truncating.
            $seed = $this->normalizedSeedUrl();

            $pageUrls = $pageUrls
                ->sortByDesc(fn (string $url): bool => $url === $seed)
                ->take(self::MaxPages)
                ->values();
        }

        // Nothing safe to crawl (not even the seed survived the SSRF filter): fail the seed
        // so it does not sit pending forever.
        if ($pageUrls->isEmpty()) {
            Log::warning('CrawlSiteJob found no crawlable URLs.', [
                'agent_id' => $this->agentId,
                'sitemap_url' => $sitemapUrl,
            ]);

            $this->markSeedAsFailed();

            return;
        }

        // A missing sitemap is fine: we start from whatever URLs we have (at minimum the
        // seed) and ProcessPageJob discovers the rest of the site by following internal
        // links, up to MaxPages.
        if ($pageUrls->count() === 1) {
            Log::info('CrawlSiteJob starting from the seed; will follow internal links.', [
                'agent_id' => $this->agentId,
            ]);
        }

        // Idempotent on (agent_id, source_url): create a document per discovered URL only
        // when it does not already exist. Existing rows (e.g. already 'done') are left
        // untouched here and re-affirmed to 'processing' in the bulk update below.
        $documents = $pageUrls->map(fn (string $url): Document => Document::query()->firstOrCreate(
            ['agent_id' => $this->agentId, 'source_url' => $url],
            ['type' => DocumentType::Web, 'status' => DocumentStatus::Pending],
        ));

        // Claim every page in this crawl (-> processing) BEFORE dispatching the batch, so
        // a subsequent five-minute scheduler tick doesn't see freshly-discovered pending
        // pages as new seeds and start a duplicate crawl. ProcessPageJob re-affirms
        // 'processing' per page, so this stays correct on retry.
        Document::query()
            ->whereKey($documents->pluck('id')->all())
            ->update(['status' => DocumentStatus::Processing]);

        $this->dispatchPageBatch($documents);
    }

    /**
     * Dispatch one ProcessPageJob PER PAGE inside a single failure-tolerant batch.
     *
     * BATCH-OF-10 / parallelism note: a Bus::batch dispatches all of its jobs at once;
     * it does not throttle them. The "~10 pages at a time" requirement is therefore
     * satisfied operationally by running ~10 queue workers (worker concurrency), NOT by
     * the batch object. We group the per-page jobs into sets of PagesPerParallelGroup
     * below to make that intended concurrency explicit and to keep the dispatch payload
     * predictable; all groups still belong to the one named batch so the crawl is tracked
     * (and can complete / report) as a single unit. allowFailures() lets one bad page
     * fail without cancelling the rest of the crawl.
     *
     * @param  Collection<int, Document>  $documents
     */
    private function dispatchPageBatch(Collection $documents): void
    {
        $pageJobs = $documents
            ->map(fn (Document $document): ProcessPageJob => new ProcessPageJob($document->id))
            ->chunk(self::PagesPerParallelGroup) // groups of 10 (intended concurrency)
            ->flatten()
            ->all();

        Bus::batch($pageJobs)
            ->name("crawl:{$this->agentId}")
            ->allowFailures()
            ->then(function (Batch $batch): void {
                // Optional: the crawl finished. (Avoid $this here — batch callbacks are
                // serialized and run later by the queue.)
                Log::info('Crawl batch completed.', [
                    'batch' => $batch->name,
                    'total' => $batch->totalJobs,
                    'failed' => $batch->failedJobs,
                ]);
            })
            ->dispatch();
    }

    /**
     * Recursively collect page URLs from a sitemap, following nested sitemap indexes.
     *
     * @return list<string>
     */
    private function collectUrls(string $sitemapUrl, int $depth = 0): array
    {
        if ($depth > self::MaxSitemapDepth) {
            return [];
        }

        // Nested sitemap-index <loc> URLs are attacker-controlled, so re-validate every
        // sitemap fetch (initial and recursed) against a public target before hitting it.
        if (! SafeUrl::isPublic($sitemapUrl)) {
            Log::warning('CrawlSiteJob refused to fetch an unsafe sitemap URL.', [
                'agent_id' => $this->agentId,
                'sitemap_url' => $sitemapUrl,
            ]);

            return [];
        }

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false)
            ->withOptions(['allow_redirects' => SafeUrl::guardedRedirects()])
            ->get($sitemapUrl);

        if ($response->failed()) {
            Log::warning('CrawlSiteJob could not fetch sitemap.', [
                'agent_id' => $this->agentId,
                'sitemap_url' => $sitemapUrl,
                'status' => $response->status(),
            ]);

            return [];
        }

        $xpath = $this->xpathFor($response->body());

        if ($xpath === null) {
            return [];
        }

        // SITEMAP INDEX handling: a <sitemapindex> lists nested <sitemap><loc> URLs that
        // point at further sitemaps. local-name() makes these queries work regardless of
        // the XML namespace the sitemap declares.
        $nested = $xpath->query("//*[local-name()='sitemap']/*[local-name()='loc']");

        if ($nested instanceof DOMNodeList && $nested->length > 0) {
            $urls = [];

            foreach ($this->locValues($nested) as $url) {
                $urls = array_merge($urls, $this->collectUrls($url, $depth + 1));
            }

            return $urls;
        }

        // Otherwise it's a flat <urlset> of page <url><loc> entries.
        $pages = $xpath->query("//*[local-name()='url']/*[local-name()='loc']");

        return $pages instanceof DOMNodeList ? $this->locValues($pages) : [];
    }

    /**
     * Extract trimmed, non-empty text values from a list of <loc> nodes.
     *
     * @param  DOMNodeList<DOMNode|DOMNameSpaceNode>  $nodes
     * @return list<string>
     */
    private function locValues(DOMNodeList $nodes): array
    {
        $values = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue; // skip namespace nodes
            }

            $value = trim($node->textContent);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Build the sitemap.xml URL for the seed URL's scheme + host.
     */
    private function sitemapUrlFor(string $seedUrl): string
    {
        $uri = Uri::of($seedUrl);
        $authority = $uri->host().($uri->port() !== null ? ':'.$uri->port() : '');

        return ($uri->scheme() ?: 'https').'://'.$authority.'/sitemap.xml';
    }

    /**
     * Safely parse a sitemap body into a DOMXPath, or null when it isn't valid XML.
     */
    private function xpathFor(string $body): ?DOMXPath
    {
        if (trim($body) === '') {
            return null;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // LIBXML_NONET disables network access during parsing (XXE hardening).
        $loaded = $dom->loadXML($body, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($dom) : null;
    }

    /**
     * Move this agent's claimed (processing) documents to 'failed'.
     */
    private function markProcessingAsFailed(): void
    {
        Document::query()
            ->where('agent_id', $this->agentId)
            ->where('status', DocumentStatus::Processing)
            ->update(['status' => DocumentStatus::Failed]);
    }

    /**
     * Fail the seed document that triggered this crawl (whether pending or processing) so
     * an un-crawlable site never leaves it stuck.
     */
    private function markSeedAsFailed(): void
    {
        // Match both forms: the seed row may predate normalization (stored raw) or have
        // been created by this job in its canonical form.
        $candidates = array_unique([trim($this->seedUrl), $this->normalizedSeedUrl()]);

        Document::query()
            ->where('agent_id', $this->agentId)
            ->whereIn('source_url', $candidates)
            ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Processing])
            ->update(['status' => DocumentStatus::Failed]);
    }

    /**
     * The seed URL in the same canonical form the crawl stores documents under.
     */
    private function normalizedSeedUrl(): string
    {
        return SafeUrl::normalize(trim($this->seedUrl));
    }

    /**
     * Handle a terminal job failure (all retries exhausted).
     */
    public function failed(?Throwable $exception): void
    {
        $this->markProcessingAsFailed();
        $this->markSeedAsFailed();
    }
}
