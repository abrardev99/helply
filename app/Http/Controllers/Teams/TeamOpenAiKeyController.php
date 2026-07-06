<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateOpenAiKeyRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamOpenAiKeyController extends Controller
{
    /**
     * The key is assigned explicitly (never mass-assigned from request input) and stored
     * through the model's `encrypted` cast, so it is encrypted at rest and never echoed
     * back to the browser.
     */
    public function update(UpdateOpenAiKeyRequest $request, Team $team): RedirectResponse
    {
        $team->openai_api_key = $request->validated('openai_api_key');
        $team->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('OpenAI key saved.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->openai_api_key = null;
        $team->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('OpenAI key removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
