<?php

namespace App\Services\Chat;

use App\Ai\Agents\QueryTriage;
use App\Ai\Support\ResolvesTenantKey;
use App\Models\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

class QueryClassifier
{
    public function __construct(private ResolvesTenantKey $keys) {}

    /**
     * Whether a message should be answered from the agent's documents.
     *
     * Fails open: anything we cannot confidently classify as small talk is treated as a
     * question, so a triage failure costs a retrieval rather than a missed answer. The
     * relevance gate and grounding check still apply on that path.
     */
    public function needsDocumentLookup(Agent $agent, string $question): bool
    {
        if (trim($question) === '') {
            return false;
        }

        $verdict = $this->keys->withTenantKey($agent, fn () => (new QueryTriage)
            ->prompt($question, provider: Lab::OpenAI, model: $agent->chat_model));

        if (! $verdict instanceof StructuredAgentResponse) {
            return true;
        }

        return (bool) ($verdict->structured['needs_lookup'] ?? true);
    }
}
