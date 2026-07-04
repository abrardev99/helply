<?php

namespace App\Http\Controllers\Bots;

use App\Http\Controllers\Controller;
use App\Jobs\EmbedChunksJob;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReembedController extends Controller
{
    /**
     * Re-embed all of a bot's chunks from scratch (clears and recomputes vectors).
     */
    public function __invoke(string $currentTeam, Bot $bot): RedirectResponse
    {
        Gate::authorize('update', $bot);

        EmbedChunksJob::dispatch($bot->id, force: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Re-embedding started.')]);

        return back();
    }
}
