<?php

namespace App\Http\Controllers\Bots;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DocumentController extends Controller
{
    /**
     * Remove an ingested document (and its chunks, via cascade) from a bot.
     */
    public function destroy(string $currentTeam, Bot $bot, string $document): RedirectResponse
    {
        Gate::authorize('update', $bot);

        // Resolve the document through the bot relation so a document id from another
        // bot/team can never be deleted through this route.
        $bot->documents()->whereKey($document)->firstOrFail()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document removed.')]);

        return back();
    }
}
