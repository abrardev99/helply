<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
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

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

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
     *
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

            $chunks = $this->extractChunks(Storage::disk('local')->get($path));

            if ($chunks === []) {
                throw new RuntimeException('No extractable text found in PDF.');
            }

            DB::transaction(function () use ($document, $chunks): void {
                $document->chunks()->delete();

                foreach ($chunks as $content) {
                    $document->chunks()->create([
                        'bot_id' => $document->bot_id,
                        'content' => $content,
                        'embedding' => null,
                    ]);
                }

                $document->update(['status' => DocumentStatus::Done]);
            });
        } catch (Throwable $exception) {
            $document->update(['status' => DocumentStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * Extract one text chunk per page, falling back to the whole-document text when a
     * PDF exposes no per-page structure.
     *
     * @return list<string>
     */
    private function extractChunks(string $contents): array
    {
        $pdf = (new Parser)->parseContent($contents);

        $chunks = [];

        foreach ($pdf->getPages() as $page) {
            $text = Str::squish($page->getText());

            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        if ($chunks === []) {
            $text = Str::squish($pdf->getText());

            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        return $chunks;
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
