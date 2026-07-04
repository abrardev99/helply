<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Jobs\EmbedChunksJob;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReembedController extends Controller
{
    /**
     * Re-embed all of a agent's chunks from scratch (clears and recomputes vectors).
     */
    public function __invoke(string $currentTeam, Agent $agent): RedirectResponse
    {
        Gate::authorize('update', $agent);

        EmbedChunksJob::dispatch($agent->id, force: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Re-embedding started.')]);

        return back();
    }
}
