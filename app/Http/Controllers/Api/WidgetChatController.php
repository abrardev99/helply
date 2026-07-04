<?php

namespace App\Http\Controllers\Api;

use App\Actions\Chat\HandleChatMessage;
use App\Data\WidgetChatResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatMessageRequest;
use App\Models\Bot;

class WidgetChatController extends Controller
{
    /**
     * Handle a public widget chat message for a bot.
     */
    public function __invoke(ChatMessageRequest $request, Bot $bot, HandleChatMessage $action): WidgetChatResponse
    {
        return $action->handle(
            $bot,
            $request->validated('session_id'),
            $request->validated('message'),
        );
    }
}
