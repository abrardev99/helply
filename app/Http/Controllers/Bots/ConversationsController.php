<?php

namespace App\Http\Controllers\Bots;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationsController extends Controller
{
    /**
     * List a bot's conversations, with light analytics and an optional flagged filter.
     */
    public function index(Request $request, string $currentTeam, Bot $bot): Response
    {
        Gate::authorize('view', $bot);

        $onlyFlagged = $request->boolean('flagged');

        $conversations = $bot->conversations()
            ->withCount([
                'messages',
                'messages as flagged_count' => fn ($query) => $query->where('flagged', true),
            ])
            ->when($onlyFlagged, fn ($query) => $query->whereHas('messages', fn ($q) => $q->where('flagged', true)))
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'session_id' => $conversation->session_id,
                'messages_count' => $conversation->messages_count,
                'flagged' => $conversation->flagged_count > 0,
                'updated_at' => $conversation->updated_at?->toISOString(),
            ]);

        return Inertia::render('bots/conversations/index', [
            'bot' => ['id' => $bot->id, 'name' => $bot->name],
            'conversations' => $conversations,
            'filters' => ['flagged' => $onlyFlagged],
            'analytics' => $this->analytics($bot),
        ]);
    }

    /**
     * Show a single conversation transcript with sources and retrieval scores.
     */
    public function show(string $currentTeam, Bot $bot, string $conversation): Response
    {
        Gate::authorize('view', $bot);

        // Resolve through the bot relation so a conversation from another bot/team is 404.
        $conversation = $bot->conversations()->whereKey($conversation)->firstOrFail();

        return Inertia::render('bots/conversations/show', [
            'bot' => ['id' => $bot->id, 'name' => $bot->name],
            'conversation' => [
                'id' => $conversation->id,
                'session_id' => $conversation->session_id,
                'created_at' => $conversation->created_at?->toISOString(),
            ],
            'messages' => $conversation->messages()
                ->oldest()
                ->get()
                ->map(fn (Message $message) => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'role_label' => $message->role->label(),
                    'content' => $message->content,
                    'sources' => $message->sources ?? [],
                    'retrieval_score' => $message->retrieval_score,
                    'flagged' => $message->flagged,
                    'created_at' => $message->created_at?->toISOString(),
                ]),
        ]);
    }

    /**
     * Simple counts for the conversations dashboard.
     *
     * @return array{conversations: int, messages: int, flagged: int, flaggedPercent: int}
     */
    private function analytics(Bot $bot): array
    {
        $conversationIds = $bot->conversations()->pluck('id');

        $messages = Message::query()->whereIn('conversation_id', $conversationIds)->count();
        $flagged = Message::query()->whereIn('conversation_id', $conversationIds)->where('flagged', true)->count();

        return [
            'conversations' => $conversationIds->count(),
            'messages' => $messages,
            'flagged' => $flagged,
            'flaggedPercent' => $messages > 0 ? (int) round($flagged / $messages * 100) : 0,
        ];
    }
}
