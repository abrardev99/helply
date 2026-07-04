<?php

namespace App\Ai\Support;

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Models\Bot;
use Closure;

class ResolvesTenantKey
{
    /**
     * Resolve the OpenAI key that a bot should use, preferring a per-bot override and
     * falling back to the team key. Throws when neither is set so callers can surface a
     * clear "needs key" state rather than making a doomed API call.
     */
    public function resolveOpenAiKey(Bot $bot): string
    {
        $key = $bot->openai_api_key ?: $bot->team->openai_api_key;

        if (blank($key)) {
            throw new MissingOpenAiKeyException($bot);
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
    public function withTenantKey(Bot $bot, Closure $callback): mixed
    {
        $key = $this->resolveOpenAiKey($bot);

        $previous = config('ai.providers.openai.key');

        config(['ai.providers.openai.key' => $key]);

        try {
            return $callback();
        } finally {
            config(['ai.providers.openai.key' => $previous]);
        }
    }
}
