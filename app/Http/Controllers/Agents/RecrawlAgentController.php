<?php

namespace App\Http\Controllers\Agents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Jobs\CrawlSiteJob;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RecrawlAgentController extends Controller
{
    /**
     * Re-crawl every website source already attached to a agent. Existing web documents
     * are reset to pending so the crawl re-fetches them idempotently (delete-then-insert
     * of chunks per page), rather than creating duplicates.
     */
    public function __invoke(string $currentTeam, Agent $agent): RedirectResponse
    {
        Gate::authorize('update', $agent);

        $seed = $agent->documents()
            ->where('type', DocumentType::Web)
            ->whereNotNull('source_url')
            ->first();

        if ($seed === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('No website sources to re-crawl.')]);

            return back();
        }

        $agent->documents()
            ->where('type', DocumentType::Web)
            ->update(['status' => DocumentStatus::Pending]);

        CrawlSiteJob::dispatch($agent->id, $seed->source_url);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Re-crawl started.')]);

        return back();
    }
}
