<?php

namespace App\Http\Controllers\Agents;

use App\Enums\AgentStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    /**
     * Display a listing of the current team's agents.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Agent::class);

        $user = $request->user();
        $team = $user->currentTeam;

        return Inertia::render('agents/index', [
            'agents' => $team->agents()
                ->withCount('documents')
                ->latest()
                ->get()
                ->map(fn (Agent $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'status' => $agent->status->value,
                    'status_label' => $agent->status->label(),
                    'documents_count' => $agent->documents_count,
                ]),
            'permissions' => [
                'canManageAgents' => $user->hasTeamPermission($team, TeamPermission::ManageAgents),
            ],
        ]);
    }

    /**
     * Show the form for creating a new agent.
     */
    public function create(): Response
    {
        Gate::authorize('create', Agent::class);

        return Inertia::render('agents/create');
    }

    /**
     * Store a newly created agent.
     */
    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = $request->user()->currentTeam->agents()->create([
            'name' => $request->validated('name'),
            'embed_origins' => $request->validated('embed_origins', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agent created.')]);

        return to_route('agents.show', ['agent' => $agent]);
    }

    /**
     * Display the specified agent.
     */
    public function show(Request $request, string $currentTeam, Agent $agent): Response
    {
        Gate::authorize('view', $agent);

        return Inertia::render('agents/show', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $agent->status->value,
                'status_label' => $agent->status->label(),
                'embed_origins' => $agent->embed_origins ?? [],
            ],
            'documents' => $agent->documents()
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
                'total' => $agent->chunks()->count(),
                'embedded' => $agent->chunks()->whereNotNull('embedding')->count(),
            ],
            'widgetScriptUrl' => url('/widget.js'),
            'permissions' => [
                'canManageAgents' => $request->user()->hasTeamPermission($agent->team, TeamPermission::ManageAgents),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified agent.
     */
    public function edit(string $currentTeam, Agent $agent): Response
    {
        Gate::authorize('update', $agent);

        return Inertia::render('agents/edit', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $agent->status->value,
                'embed_origins' => $agent->embed_origins ?? [],
                'embedding_model' => $agent->embedding_model,
                'chat_model' => $agent->chat_model,
                'system_prompt' => $agent->system_prompt,
                'confidence_threshold' => $agent->confidence_threshold,
            ],
            'statusOptions' => AgentStatus::options(),
        ]);
    }

    /**
     * Update the specified agent.
     */
    public function update(UpdateAgentRequest $request, string $currentTeam, Agent $agent): RedirectResponse
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

        $agent->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agent updated.')]);

        return to_route('agents.edit', ['agent' => $agent]);
    }

    /**
     * Remove the specified agent.
     */
    public function destroy(string $currentTeam, Agent $agent): RedirectResponse
    {
        Gate::authorize('delete', $agent);

        $agent->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agent deleted.')]);

        return to_route('agents.index');
    }
}
