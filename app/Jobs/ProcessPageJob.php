<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Services\Ingestion\TextChunker;
use App\Support\SafeUrl;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessPageJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    /**
     * HTML elements whose text is chrome, not page content.
     */
    private const NonContentTags = ['script', 'style', 'noscript', 'template', 'nav', 'header', 'footer', 'aside', 'form', 'svg'];

    /**
     * Largest page body we will parse (2 MiB). Anything beyond this is truncated before
     * DOM parsing to bound memory use on pathological pages.
     */
    private const MaxPageBytes = 2_097_152;

    public function __construct(
        public string $documentId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        // The crawl was cancelled (allowFailures() keeps it running on a job failure, but
        // a manual cancel still applies) — nothing to do.
        if ($this->batch()?->cancelled()) {
            return;
        }

        $document = Document::query()->findOrFail($this->documentId);

        try {
            $document->update(['status' => DocumentStatus::Processing]);

            // Defense-in-depth SSRF guard: refuse to fetch a page whose host is a
            // private/loopback/link-local IP literal or a non-http(s) scheme.
            if (! SafeUrl::hasSafeTarget((string) $document->source_url)) {
                throw new RuntimeException("Refusing to fetch unsafe URL: {$document->source_url}");
            }

            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->retry(2, 300, throw: false)
                ->withOptions(['allow_redirects' => SafeUrl::guardedRedirects()])
                ->get($document->source_url);

            // Surface a non-2xx response as an exception so it routes through the failure
            // handling below (status => failed, then rethrow for the batch).
            $response->throw();

            $body = substr($response->body(), 0, self::MaxPageBytes);
            $extracted = $this->extractReadableText($body);

            $chunks = (new TextChunker)->chunk($extracted['text']);

            // IDEMPOTENT delete-before-insert: a retry (or a later re-crawl) must not
            // duplicate chunks for this page. Text is split into retrieval-sized,
            // overlapping chunks (F05), each stored with a NULL embedding (added in F06).
            DB::transaction(function () use ($document, $extracted, $chunks): void {
                $document->chunks()->delete();

                foreach ($chunks as $position => $content) {
                    $document->chunks()->create([
                        'agent_id' => $document->agent_id,
                        'position' => $position,
                        'content' => $content,
                        'embedding' => null,
                    ]);
                }

                $document->update([
                    'title' => $extracted['title'] ?? $document->title,
                    'status' => DocumentStatus::Done,
                ]);
            });

            // Kick off embedding now that the page's chunks exist. Dispatched after the
            // transaction commits; a no-op when no OpenAI key is configured.
            EmbedChunksJob::dispatch($document->agent_id);

            // Follow internal links so the whole site is crawled, not just the seed /
            // sitemap URLs. Bounded by MaxPages.
            $this->crawlLinkedPages($document, $body);
        } catch (Throwable $exception) {
            // Mark failed and rethrow so the batch records the failure. failed() below is
            // the terminal safety net for cases where handle() is never reached.
            $document->update(['status' => DocumentStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * Extract the <title> and main readable text from an HTML document.
     *
     * Dependency-light: uses PHP's built-in ext-dom (DOMDocument/DOMXPath). No external
     * package (e.g. symfony/dom-crawler) is required.
     *
     * @return array{title: ?string, text: string}
     */
    private function extractReadableText(string $html): array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // Prefix forces UTF-8 interpretation; loadHTML otherwise assumes ISO-8859-1.
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);

        $titleNode = $this->firstNode($xpath, '//title');
        $title = $titleNode !== null ? Str::squish($titleNode->textContent) : null;

        // Strip chrome/non-content elements before reading the remaining text.
        $selector = implode(' | ', array_map(
            static fn (string $tag): string => "//{$tag}",
            self::NonContentTags,
        ));

        $chrome = $xpath->query($selector);

        if ($chrome instanceof \DOMNodeList) {
            foreach ($chrome as $node) {
                if ($node instanceof \DOMNode) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        $body = $this->firstNode($xpath, '//body');
        $text = Str::squish($body !== null ? $body->textContent : $dom->textContent);

        return [
            'title' => $title !== null && $title !== '' ? $title : null,
            'text' => $text,
        ];
    }

    /**
     * Return the first element node matching an XPath expression, or null.
     */
    private function firstNode(\DOMXPath $xpath, string $expression): ?\DOMNode
    {
        $nodes = $xpath->query($expression);
        $node = $nodes instanceof \DOMNodeList ? $nodes->item(0) : null;

        return $node instanceof \DOMNode ? $node : null;
    }

    /**
     * Discover same-host links on this page and queue any not-yet-seen ones for crawling,
     * so the whole site is ingested by following links. Idempotent (firstOrCreate) and
     * bounded by CrawlSiteJob::MaxPages.
     */
    private function crawlLinkedPages(Document $document, string $html): void
    {
        $agentId = $document->agent_id;

        $count = Document::query()
            ->where('agent_id', $agentId)
            ->where('type', DocumentType::Web)
            ->count();

        if ($count >= CrawlSiteJob::MaxPages) {
            return;
        }

        foreach ($this->discoverLinks($html, (string) $document->source_url) as $url) {
            if ($count >= CrawlSiteJob::MaxPages) {
                break;
            }

            $child = Document::query()->firstOrCreate(
                ['agent_id' => $agentId, 'source_url' => $url],
                ['type' => DocumentType::Web, 'status' => DocumentStatus::Pending],
            );

            if ($child->wasRecentlyCreated) {
                $count++;
                self::dispatch($child->id);
            }
        }
    }

    /**
     * Extract absolute, same-host, http(s) links from a page (fragments stripped, deduped,
     * SSRF-filtered).
     *
     * @return list<string>
     */
    private function discoverLinks(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $anchors = (new \DOMXPath($dom))->query('//a[@href]');

        if (! $anchors instanceof \DOMNodeList) {
            return [];
        }

        $base = Utils::uriFor($baseUrl);
        $baseHost = strtolower($base->getHost());
        $links = [];

        foreach ($anchors as $anchor) {
            if (! $anchor instanceof \DOMElement) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:'])) {
                continue;
            }

            try {
                $resolved = UriResolver::resolve($base, Utils::uriFor($href))->withFragment('');
            } catch (Throwable) {
                continue;
            }

            if (! in_array(strtolower($resolved->getScheme()), ['http', 'https'], true)) {
                continue;
            }

            // Same-host only, so a crawl stays within the customer's site.
            if (strtolower($resolved->getHost()) !== $baseHost) {
                continue;
            }

            $url = SafeUrl::normalize((string) $resolved);

            if (SafeUrl::hasSafeTarget($url)) {
                $links[$url] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * Handle a terminal job failure (all retries exhausted, or handle() never reached).
     */
    public function failed(?Throwable $exception): void
    {
        Document::query()
            ->whereKey($this->documentId)
            ->update(['status' => DocumentStatus::Failed]);
    }
}
