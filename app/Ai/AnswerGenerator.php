<?php

namespace App\Ai;

use App\Ai\Agents\SupportAgent;
use App\Ai\Support\ResolvesTenantKey;
use App\Data\GeneratedAnswer;
use App\Data\RetrievalResult;
use App\Models\Agent;
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
     * Produce a grounded answer to a question from the retrieved chunks, using the agent's
     * chat model and the tenant's OpenAI key (applied for this call's scope only).
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public function generate(Agent $agent, string $question, RetrievalResult $retrieval, array $history = []): GeneratedAnswer
    {
        $packed = $this->packer->pack($retrieval);

        $answer = $this->keys->withTenantKey($agent, fn (): string => (string) (new SupportAgent($agent, $packed->context, $history))
            ->prompt($question, provider: Lab::OpenAI, model: $agent->chat_model));

        return new GeneratedAnswer(answer: $answer, sources: $packed->sources);
    }
}
