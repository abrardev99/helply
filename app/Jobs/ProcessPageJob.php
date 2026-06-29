<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProcessPageJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * HTML elements whose text is chrome, not page content.
     */
    private const NON_CONTENT_TAGS = ['script', 'style', 'noscript', 'template', 'nav', 'header', 'footer', 'aside', 'form', 'svg'];

    public function __construct(
        public string $documentId,
    ) {}

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
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

            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->retry(2, 300, throw: false)
                ->get($document->source_url);

            // Surface a non-2xx response as an exception so it routes through the failure
            // handling below (status => failed, then rethrow for the batch).
            $response->throw();

            $extracted = $this->extractReadableText($response->body());

            // IDEMPOTENT delete-before-insert: a retry (or a later re-crawl) must not
            // duplicate chunks for this page. For this stage one page == exactly one
            // chunk, stored with a NULL embedding (embeddings come in a later phase).
            DB::transaction(function () use ($document, $extracted): void {
                $document->chunks()->delete();

                $document->chunks()->create([
                    'bot_id' => $document->bot_id,
                    'content' => $extracted['text'],
                    'embedding' => null,
                ]);

                $document->update([
                    'title' => $extracted['title'] ?? $document->title,
                    'status' => DocumentStatus::Done,
                ]);
            });
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
            self::NON_CONTENT_TAGS,
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
     * Handle a terminal job failure (all retries exhausted, or handle() never reached).
     */
    public function failed(?Throwable $exception): void
    {
        Document::query()
            ->whereKey($this->documentId)
            ->update(['status' => DocumentStatus::Failed]);
    }
}
