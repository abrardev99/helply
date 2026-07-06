<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Ingestion\TextChunker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class ImportPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $documentId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Mirrors ProcessPageJob: idempotent delete-then-insert of chunks, status
     * transitions, and a failure handler so a document never stays stuck in
     * 'processing'. One chunk is produced per page of extractable text; token-aware
     * re-chunking is refined later in F05.
     */
    public function handle(): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        try {
            $document->update(['status' => DocumentStatus::Processing]);

            $path = (string) $document->source_url;

            if (! Storage::disk('local')->exists($path)) {
                throw new RuntimeException("Stored PDF is missing: {$path}");
            }

            $text = $this->extractText(Storage::disk('local')->get($path));

            $chunks = (new TextChunker)->chunk($text);

            if ($chunks === []) {
                throw new RuntimeException('No extractable text found in PDF.');
            }

            DB::transaction(function () use ($document, $chunks): void {
                $document->chunks()->delete();

                foreach ($chunks as $position => $content) {
                    $document->chunks()->create([
                        'agent_id' => $document->agent_id,
                        'position' => $position,
                        'content' => $content,
                        'embedding' => null,
                    ]);
                }

                $document->update(['status' => DocumentStatus::Done]);
            });

            // Kick off embedding now that the PDF's chunks exist. Dispatched after the
            // transaction commits; a no-op when no OpenAI key is configured.
            EmbedChunksJob::dispatch($document->agent_id);
        } catch (Throwable $exception) {
            $document->update(['status' => DocumentStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * Extract the full readable text of a PDF, concatenating pages. Retrieval-sized
     * chunking is applied afterwards by the TextChunker (F05).
     */
    private function extractText(string $contents): string
    {
        $pdf = (new Parser)->parseContent($contents);

        $pages = [];

        foreach ($pdf->getPages() as $page) {
            $text = Str::squish($page->getText());

            if ($text !== '') {
                $pages[] = $text;
            }
        }

        return $pages === [] ? Str::squish($pdf->getText()) : implode(' ', $pages);
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
