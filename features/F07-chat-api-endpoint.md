# F07 — Public Chat API Endpoint

**Phase:** 3 · **Status:** ⛔ todo · **Depends on:** F06 (also orchestrates F08/F09/F10)

## Goal

The public HTTP endpoint the embedded widget calls. Anonymous, origin-checked, rate-limited.
Orchestrates retrieval → guardrails → generation → persistence and returns a friendly reply.

## Scope / tasks

- **Route:** `POST /api/widget/{bot}/chat` (in `routes/` — add an `api` route file or group;
  not behind `auth`). Route-model-bind `Bot`.
- **`Api\WidgetChatController`** — thin; delegates to `Actions\Chat\HandleChatMessage`.
- **`ChatMessageRequest`** — validate `session_id` (string) and `message` (required string,
  max length). Reject oversized payloads.
- **`Middleware\VerifyWidgetOrigin`** — check request `Origin`/`Referer` against
  `bot.embed_origins`; set CORS headers reflecting only an allow-listed origin; `403`
  otherwise.
- **Rate limiting** — `RateLimiter` per `bot_id` + client IP.
- **`Actions\Chat\HandleChatMessage(Bot, sessionId, message)`** — the orchestration:
  1. find/create `Conversation` by `(bot_id, session_id)`; store the user `Message`.
  2. call retrieval (F08) → guardrail #1 (F10) → generation (F09) → guardrail #2 (F10).
  3. store the assistant `Message` (`content`, `sources`, `retrieval_score`, `flagged`).
  4. return a `Data` DTO → JSON `{ answer, sources?, conversation_id }`.
- **Response DTO** in `app/Data`.

## Data changes

- None (uses existing `conversations`/`messages`). `MessageRole` enum (`User`,`Assistant`)
  recommended.

## Acceptance criteria

- A POST from an allow-listed origin with a valid body returns an answer and persists both
  messages under one conversation keyed by `session_id`.
- Non-allow-listed origin → `403`; missing/oversized body → `422`; floods → `429`.
- The OpenAI key and system prompt never appear in the response.

## Tests

- Feature tests with the AI SDK fakes (`Embeddings::fake()`, `Reranking::fake()`,
  `SupportAgent::fake()`, `GroundingChecker::fake()`) so the endpoint makes no real provider
  calls: happy path, origin rejection (`403`), validation (`422`), throttle (`429`), and
  conversation reuse across turns with the same `session_id`.
