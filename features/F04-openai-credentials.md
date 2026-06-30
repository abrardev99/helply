# F04 — OpenAI Credential Management (BYO Key)

**Phase:** 2 · **Status:** ⛔ todo · **Depends on:** F01

> **Dependency:** introduces the **Laravel AI SDK** (`composer require laravel/ai`) and a
> published `config/ai.php`. Adding a dependency needs approval per project guidelines.

## Goal

Let a customer securely store their **own** OpenAI API key, used server-side for embeddings
(F06) and chat (F09). The key must never reach the browser after being saved and never be
logged.

## Scope / tasks

- **Storage:** add an `encrypted`-cast `openai_api_key` column to **teams** (one key per
  customer account). Optionally a nullable per-bot `openai_api_key` override; resolution
  order `bot.openai_api_key ?? team.openai_api_key`.
- **Dashboard form:** a team settings section to set/replace the key. Once a key is saved,
  the field renders as a fixed run of stars (`••••••••••••`) — **no characters of the real
  key are ever revealed**, not even a last-4 hint. Show a "key is set" indicator + a
  "replace" control; never echo the stored value back to the browser.
- **Validation:** on save, optionally make a cheap OpenAI call (e.g. list models) to verify
  the key works; surface a clear error if not.
- **Resolver:** `App\Ai\Support\ResolvesTenantKey` with `resolveOpenAiKey(Bot $bot): string`
  (override → team → throw typed "missing key"). Since embeddings (F06) and chat (F09) go
  through the **Laravel AI SDK**, the resolver also applies the key to the SDK for the
  current scope: `config(['ai.providers.openai.key' => $key])`. This runs inside an isolated
  queue job (F06) or a single web request (F09), so the runtime override never bleeds across
  tenants. Provide a helper like `withTenantKey(Bot $bot, Closure $callback)` to make the
  scoping explicit.
- **Per-bot model config** (group here since it lives next to the key): add `embedding_model`
  (default `text-embedding-3-small`), `chat_model` (default `gpt-5.4`), `system_prompt`
  (nullable text), `confidence_threshold` (float, default e.g. `0.75`) to `bots`.

## Data changes

- `teams.openai_api_key` — encrypted, nullable.
- `bots`: `openai_api_key` (encrypted, nullable), `embedding_model`, `chat_model`,
  `system_prompt`, `confidence_threshold`.
- Cast `openai_api_key` as `encrypted` in model `casts()`.

## Security rules (see docs/05)

- Write-only from the UI; masked on read; never in logs, job payloads, or exception data.
- Encrypted at rest via Laravel's `encrypted` cast (`APP_KEY`).

## Acceptance criteria

- A team can save a key; reloading the page shows only stars (`••••••••`), never any part
  of the secret. The API/Inertia payload that hydrates the page contains no key material.
- An invalid key is rejected at save time (if validation enabled) or produces a clear
  non-fatal state downstream.
- `resolveOpenAiKey()` returns the bot override when set, else the team key, else signals
  "needs key"; `withTenantKey()` sets `ai.providers.openai.key` for the callback scope and
  the AI SDK then uses the tenant's key.
- Grep of logs after an embed/chat run shows no key material.

## Tests

- Encrypted round-trip; masked serialization to the frontend; resolution precedence;
  missing-key path.
