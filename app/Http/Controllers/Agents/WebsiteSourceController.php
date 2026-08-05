<?php

namespace App\Http\Controllers\Agents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StoreWebsiteSourceRequest;
use App\Jobs\CrawlSiteJob;
use App\Models\Agent;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WebsiteSourceController extends Controller
{
    public function store(StoreWebsiteSourceRequest $request, string $currentTeam, Agent $agent): RedirectResponse
    {
        // Normalize so the seed dedupes with the same page discovered via link-following.
        $url = SafeUrl::normalize($request->validated('url'));

        // Idempotent on (agent_id, source_url): re-adding the same URL re-uses the seed
        // document and simply re-affirms it as pending so the crawl runs again.
        $agent->documents()->updateOrCreate(
            ['source_url' => $url],
            ['type' => DocumentType::Web, 'status' => DocumentStatus::Pending],
        );

        CrawlSiteJob::dispatch($agent->id, $url);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Crawl started.')]);

        return back();
    }
}
