<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DocumentController extends Controller
{
    /**
     * Remove an ingested document (and its chunks, via cascade) from a agent.
     */
    public function destroy(string $currentTeam, Agent $agent, string $document): RedirectResponse
    {
        Gate::authorize('update', $agent);

        // Resolve the document through the agent relation so a document id from another
        // agent/team can never be deleted through this route.
        $agent->documents()->whereKey($document)->firstOrFail()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document removed.')]);

        return back();
    }
}
