<?php

namespace App\Ai;

use App\Ai\Agents\SupportAgent;
use App\Ai\Support\ResolvesTenantKey;
use App\Data\GeneratedAnswer;
use App\Data\RetrievalResult;
use App\Models\Bot;
use App\Services\Retrieval\ContextPacker;
use Laravel\Ai\Enums\Lab;

class AnswerGenerator
{
    public function __construct(
        private ResolvesTenantKey $keys,
        private ContextPacker $packer,
    ) {
        //
    }

    /**
     * Produce a grounded answer to a question from the retrieved chunks, using the bot's
     * chat model and the tenant's OpenAI key (applied for this call's scope only).
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public function generate(Bot $bot, string $question, RetrievalResult $retrieval, array $history = []): GeneratedAnswer
    {
        $packed = $this->packer->pack($retrieval);

        $answer = $this->keys->withTenantKey($bot, fn (): string => (string) (new SupportAgent($bot, $packed->context, $history))
            ->prompt($question, provider: Lab::OpenAI, model: $bot->chat_model));

        return new GeneratedAnswer(answer: $answer, sources: $packed->sources);
    }
}
