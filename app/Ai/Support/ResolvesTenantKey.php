<?php

namespace App\Ai\Support;

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Models\Agent;
use Closure;

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
