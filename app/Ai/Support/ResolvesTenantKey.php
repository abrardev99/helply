<?php

namespace App\Ai\Support;

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Models\Agent;
use Closure;
use Laravel\Ai\Ai;

class ResolvesTenantKey
{
    /**
     * Resolve the OpenAI key that an agent should use. Resolution order:
     * per-agent override → team key → platform key (config `ai.providers.openai.key`,
     * i.e. the OPENAI_API_KEY env var). Throws when none is set so callers can surface a
     * clear "needs key" state rather than making a doomed API call.
     */
    public function resolveOpenAiKey(Agent $agent): string
    {
        $key = $agent->openai_api_key
            ?: $agent->team->openai_api_key
            ?: config('ai.providers.openai.key');

        if (blank($key)) {
            throw new MissingOpenAiKeyException($agent);
        }

        return $key;
    }

    /**
     * Run a callback with the tenant's OpenAI key applied to the AI SDK configuration.
     *
     * The AiManager memoizes each provider instance with the key captured at construction,
     * so swapping the config key is not enough on its own — the cached "openai" instance
     * must be forgotten so it is rebuilt with the tenant key. Both are restored afterwards,
     * which keeps the swap correct even in a long-lived queue worker where one process
     * serves many tenants.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withTenantKey(Agent $agent, Closure $callback): mixed
    {
        $key = $this->resolveOpenAiKey($agent);

        $previous = config('ai.providers.openai.key');

        config(['ai.providers.openai.key' => $key]);
        Ai::forgetInstance('openai');

        try {
            return $callback();
        } finally {
            config(['ai.providers.openai.key' => $previous]);
            Ai::forgetInstance('openai');
        }
    }
}
