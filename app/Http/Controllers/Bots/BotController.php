<?php

namespace App\Http\Controllers\Bots;

use App\Enums\BotStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bots\StoreBotRequest;
use App\Http\Requests\Bots\UpdateBotRequest;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BotController extends Controller
{
    /**
     * Display a listing of the current team's bots.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bot::class);

        $user = $request->user();
        $team = $user->currentTeam;

        return Inertia::render('bots/index', [
            'bots' => $team->bots()
                ->withCount('documents')
                ->latest()
                ->get()
                ->map(fn (Bot $bot) => [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'status' => $bot->status->value,
                    'status_label' => $bot->status->label(),
                    'documents_count' => $bot->documents_count,
                ]),
            'permissions' => [
                'canManageBots' => $user->hasTeamPermission($team, TeamPermission::ManageBots),
            ],
        ]);
    }

    /**
     * Show the form for creating a new bot.
     */
    public function create(): Response
    {
        Gate::authorize('create', Bot::class);

        return Inertia::render('bots/create');
    }

    /**
     * Store a newly created bot.
     */
    public function store(StoreBotRequest $request): RedirectResponse
    {
        $bot = $request->user()->currentTeam->bots()->create([
            'name' => $request->validated('name'),
            'embed_origins' => $request->validated('embed_origins', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bot created.')]);

        return to_route('bots.show', ['bot' => $bot]);
    }

    /**
     * Display the specified bot.
     */
    public function show(Request $request, string $currentTeam, Bot $bot): Response
    {
        Gate::authorize('view', $bot);

        return Inertia::render('bots/show', [
            'bot' => [
                'id' => $bot->id,
                'name' => $bot->name,
                'status' => $bot->status->value,
                'status_label' => $bot->status->label(),
                'embed_origins' => $bot->embed_origins ?? [],
            ],
            'documents' => $bot->documents()
                ->latest()
                ->get()
                ->map(fn (Document $document) => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->type->value,
                    'type_label' => $document->type->label(),
                    'source_url' => $document->source_url,
                    'status' => $document->status->value,
                    'status_label' => $document->status->label(),
                ]),
            'embedding' => [
                'total' => $bot->chunks()->count(),
                'embedded' => $bot->chunks()->whereNotNull('embedding')->count(),
            ],
            'permissions' => [
                'canManageBots' => $request->user()->hasTeamPermission($bot->team, TeamPermission::ManageBots),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified bot.
     */
    public function edit(string $currentTeam, Bot $bot): Response
    {
        Gate::authorize('update', $bot);

        return Inertia::render('bots/edit', [
            'bot' => [
                'id' => $bot->id,
                'name' => $bot->name,
                'status' => $bot->status->value,
                'embed_origins' => $bot->embed_origins ?? [],
                'embedding_model' => $bot->embedding_model,
                'chat_model' => $bot->chat_model,
                'system_prompt' => $bot->system_prompt,
                'confidence_threshold' => $bot->confidence_threshold,
            ],
            'statusOptions' => BotStatus::options(),
        ]);
    }

    /**
     * Update the specified bot.
     */
    public function update(UpdateBotRequest $request, string $currentTeam, Bot $bot): RedirectResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'embed_origins' => $request->validated('embed_origins', []),
        ];

        // Only overwrite optional settings that were actually submitted, so a partial
        // update (e.g. a rename) leaves the model config and status untouched.
        foreach (['status', 'embedding_model', 'chat_model', 'system_prompt', 'confidence_threshold'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->validated($field);
            }
        }

        $bot->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bot updated.')]);

        return to_route('bots.edit', ['bot' => $bot]);
    }

    /**
     * Remove the specified bot.
     */
    public function destroy(string $currentTeam, Bot $bot): RedirectResponse
    {
        Gate::authorize('delete', $bot);

        $bot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bot deleted.')]);

        return to_route('bots.index');
    }
}
