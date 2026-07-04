<?php

namespace App\Http\Controllers\Bots;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Jobs\CrawlSiteJob;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RecrawlBotController extends Controller
{
    /**
     * Re-crawl every website source already attached to a bot. Existing web documents
     * are reset to pending so the crawl re-fetches them idempotently (delete-then-insert
     * of chunks per page), rather than creating duplicates.
     */
    public function __invoke(string $currentTeam, Bot $bot): RedirectResponse
    {
        Gate::authorize('update', $bot);

        $seed = $bot->documents()
            ->where('type', DocumentType::Web)
            ->whereNotNull('source_url')
            ->first();

        if ($seed === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('No website sources to re-crawl.')]);

            return back();
        }

        $bot->documents()
            ->where('type', DocumentType::Web)
            ->update(['status' => DocumentStatus::Pending]);

        CrawlSiteJob::dispatch($bot->id, $seed->source_url);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Re-crawl started.')]);

        return back();
    }
}
