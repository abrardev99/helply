<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Jobs\CrawlSiteJob;
use App\Models\Document;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('documents:queue-pending')]
#[Description('Dispatch a crawl for every agent that has a pending document to ingest.')]
class QueuePendingDocuments extends Command
{
    /**
     * DESIGN CHOICE — we key the crawl off the *Agent*, not off individual document rows.
     * For this stage one Agent == one website, and a website's pages are discovered by the
     * crawl (the sitemap), not known up front. So a single pending "seed" document is all
     * we need to trigger a crawl of the whole site. We therefore dispatch exactly ONE
     * CrawlSiteJob per agent that has pending work, rather than one job per pending row,
     * which would fan out duplicate crawls for the same site.
     *
     * A "seed" is a pending document whose content has never been fetched (it has no
     * chunks yet). The Agent model has no source_url column, so the URL used to locate the
     * sitemap is taken from the seed document itself.
     */
    public function handle(): int
    {
        $seeds = Document::query()
            ->where('status', DocumentStatus::Pending)
            ->whereNotNull('source_url')
            ->whereDoesntHave('chunks') // "never fetched" => no stored content yet
            ->get(['id', 'agent_id', 'source_url'])
            ->unique('agent_id'); // one crawl per website (agent)

        if ($seeds->isEmpty()) {
            $this->info(__('No pending documents to crawl.'));

            return self::SUCCESS;
        }

        foreach ($seeds as $seed) {
            // IDEMPOTENCY — claim this agent's pending seeds (pending -> processing) before
            // dispatching so the next five-minute tick does not re-dispatch a crawl for
            // the same agent while this one is still queued or running.
            Document::query()
                ->where('agent_id', $seed->agent_id)
                ->where('status', DocumentStatus::Pending)
                ->update(['status' => DocumentStatus::Processing]);

            CrawlSiteJob::dispatch($seed->agent_id, $seed->source_url);
        }

        $this->info(__('Dispatched :count crawl job(s).', ['count' => $seeds->count()]));

        return self::SUCCESS;
    }
}
