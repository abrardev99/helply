<?php

namespace App\Ai\Support;

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Models\Agent;
use Closure;

class ResolvesTenantKey
{
    /**
     * Resolve the OpenAI key that a agent should use, preferring a per-agent override and
     * falling back to the team key. Throws when neither is set so callers can surface a
     * clear "needs key" state rather than making a doomed API call.
     */
    public function resolveOpenAiKey(Agent $agent): string
    {
        $key = $agent->openai_api_key ?: $agent->team->openai_api_key;

        if (blank($key)) {
            throw new MissingOpenAiKeyException($agent);
        }

        return $key;
    }

    /**
     * Run a callback with the tenant's OpenAI key applied to the AI SDK configuration.
     *
     * The override is set on the shared config for the duration of the callback. This is
     * only safe because each embed runs inside an isolated queue job and each chat inside
     * a single web request — one PHP process handles one tenant at a time — so the key
     * never bleeds across tenants. The previous value is always restored afterwards.
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

        try {
            return $callback();
        } finally {
            config(['ai.providers.openai.key' => $previous]);
        }
    }
}
