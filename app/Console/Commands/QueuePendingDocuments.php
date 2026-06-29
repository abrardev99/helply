<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Jobs\CrawlSiteJob;
use App\Models\Document;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('documents:queue-pending')]
#[Description('Dispatch a crawl for every bot that has a pending document to ingest.')]
class QueuePendingDocuments extends Command
{
    /**
     * Execute the console command.
     *
     * DESIGN CHOICE — we key the crawl off the *Bot*, not off individual document rows.
     * For this stage one Bot == one website, and a website's pages are discovered by the
     * crawl (the sitemap), not known up front. So a single pending "seed" document is all
     * we need to trigger a crawl of the whole site. We therefore dispatch exactly ONE
     * CrawlSiteJob per bot that has pending work, rather than one job per pending row,
     * which would fan out duplicate crawls for the same site.
     *
     * A "seed" is a pending document whose content has never been fetched (it has no
     * chunks yet). The Bot model has no source_url column, so the URL used to locate the
     * sitemap is taken from the seed document itself.
     */
    public function handle(): int
    {
        $seeds = Document::query()
            ->where('status', DocumentStatus::Pending)
            ->whereNotNull('source_url')
            ->whereDoesntHave('chunks') // "never fetched" => no stored content yet
            ->get(['id', 'bot_id', 'source_url'])
            ->unique('bot_id'); // one crawl per website (bot)

        if ($seeds->isEmpty()) {
            $this->info(__('No pending documents to crawl.'));

            return self::SUCCESS;
        }

        foreach ($seeds as $seed) {
            // IDEMPOTENCY — claim this bot's pending seeds (pending -> processing) before
            // dispatching so the next five-minute tick does not re-dispatch a crawl for
            // the same bot while this one is still queued or running.
            Document::query()
                ->where('bot_id', $seed->bot_id)
                ->where('status', DocumentStatus::Pending)
                ->update(['status' => DocumentStatus::Processing]);

            CrawlSiteJob::dispatch($seed->bot_id, $seed->source_url);
        }

        $this->info(__('Dispatched :count crawl job(s).', ['count' => $seeds->count()]));

        return self::SUCCESS;
    }
}
