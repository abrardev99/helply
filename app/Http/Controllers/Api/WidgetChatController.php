<?php

namespace App\Http\Controllers\Api;

use App\Actions\Chat\HandleChatMessage;
use App\Data\WidgetChatResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatMessageRequest;
use App\Models\Agent;

class WidgetChatController extends Controller
{
    public function __invoke(ChatMessageRequest $request, Agent $agent, HandleChatMessage $action): WidgetChatResponse
    {
        return $action->handle(
            $agent,
            $request->validated('session_id'),
            $request->validated('message'),
        );
    }
}
