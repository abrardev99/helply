<?php

namespace App\Http\Controllers\Bots;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bots\StoreWebsiteSourceRequest;
use App\Jobs\CrawlSiteJob;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WebsiteSourceController extends Controller
{
    /**
     * Add a website URL to a bot and kick off a crawl of its sitemap.
     */
    public function store(StoreWebsiteSourceRequest $request, string $currentTeam, Bot $bot): RedirectResponse
    {
        $url = $request->validated('url');

        // Idempotent on (bot_id, source_url): re-adding the same URL re-uses the seed
        // document and simply re-affirms it as pending so the crawl runs again.
        $bot->documents()->updateOrCreate(
            ['source_url' => $url],
            ['type' => 'web', 'status' => DocumentStatus::Pending],
        );

        CrawlSiteJob::dispatch($bot->id, $url);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Crawl started.')]);

        return back();
    }
}
