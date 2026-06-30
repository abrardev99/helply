# 05 — Security & Multi-Tenancy

Helply is multi-tenant (Teams) and exposes a **public** chat endpoint that is called from
arbitrary customer websites. Three concerns dominate: tenant isolation, BYO API-key
handling, and protecting the public widget endpoint.

## 1. Tenant isolation

```
User ──member of──► Team ──owns──► Bot ──owns──► Document ──owns──► Chunk
                                    │
                                    └──owns──► Conversation ──owns──► Message
```

- Every RAG row traces back to a `Bot`, which belongs to a `Team`. `Chunk` carries a
  denormalized `bot_id` so vector search is always tenant-scoped in a single `WHERE`.
- **Dashboard** routes are already behind `auth` + `verified` + `EnsureTeamMembership`
  under the `{current_team}` prefix. Add `BotPolicy` / `DocumentPolicy` so a member can
  only touch bots in their current team. Resolve bots via route-model binding scoped to
  the team, never by raw id from the request body.
- **Never** trust a `team_id`/`bot_id` from the client for dashboard actions — derive it
  from the authenticated team context.

## 2. Bring-Your-Own OpenAI key

The customer supplies their own OpenAI key. It is used server-side for embeddings (Phase
2) and chat completions (Phase 3).

**Storage**
- Stored on the **Team** as an `encrypted`-cast column (`openai_api_key`). Laravel's
  `encrypted` cast uses `APP_KEY`; the value is encrypted at rest in Postgres.
- Optional per-**Bot** override column for customers who want to isolate billing per bot.
  Resolution order: `bot.openai_api_key ?? bot.team.openai_api_key`.

**Handling rules**
- The key is **write-only from the browser's perspective**: accept it on save, never send
  it back. Once saved, the dashboard shows only a run of stars (`••••••••`) — no part of
  the key, not even a last-4 hint — plus a "key is set" indicator and a "replace" control.
  The Inertia/JSON payload that renders the page must never include the key value.
- Never log the key. Never include it in exception payloads, job payloads serialized to
  the `jobs` table, or `sources`/debug output.
- The key is applied to the **Laravel AI SDK** via `config(['ai.providers.openai.key' => $key])`
  at runtime, scoped to a single queue job or web request (both are isolated in PHP, so the
  override never bleeds across tenants). Do **not** persist it into `config/ai.php` or `.env`.
- On save, optionally validate the key with a cheap OpenAI call (e.g. list models) and
  surface a clear error if invalid — before the customer waits on a failed embed run.
- Rate-limit / handle OpenAI 401/429 gracefully: mark the relevant documents/chunks as
  `failed` (or leave un-embedded) with a ret..able status and a clear dashboard message.

## 3. Public widget endpoint protection

`POST /api/widget/{bot}/chat` is unauthenticated (visitors are anonymous). Defenses:

```
 request ─► [ CORS ] ─► [ VerifyWidgetOrigin ] ─► [ RateLimiter ] ─► [ validate ] ─► chat
                │              │                       │
                │              │                       └─ per-bot + per-IP throttle
                │              └─ Origin/Referer ∈ bot.embed_origins (else 403)
                └─ Access-Control-Allow-Origin reflects an allow-listed origin only
```

- **Origin allow-list**: each bot has `embed_origins` (json). A middleware
  (`VerifyWidgetOrigin`) checks the request `Origin`/`Referer` against it and sets CORS
  headers to reflect only an allow-listed origin. Requests from other origins get `403`.
  - Note: `Origin` is browser-enforced, not a hard security boundary (a script can forge
    it). It stops casual cross-site embedding and ties usage to the customer's domain; the
    rate limiter is the real abuse control.
- **Rate limiting**: throttle per `bot_id` and per client IP (Laravel `RateLimiter`).
  Protects the customer's OpenAI spend and the app from floods.
- **Input limits**: cap question length; reject oversized payloads in the FormRequest.
- **Cost guardrail**: the relevance gate (Phase 3) short-circuits off-topic questions
  *before* calling the expensive chat model — this is a security/cost control as much as a
  quality one.
- **No secret leakage**: the endpoint never returns raw chunk metadata beyond what the
  widget needs (answer + optional citations). The OpenAI key never leaves the server.

## 4. Ingestion-side hardening (already partly in place)

- Sitemap/HTML parsing uses `LIBXML_NONET` to disable network access during XML parsing
  (XXE hardening) — see `CrawlSiteJob`/`ProcessPageJob`.
- Outbound fetches have connect/read timeouts and bounded retries.
- **To add**: SSRF protection for user-supplied URLs (block private/loopback/link-local IP
  ranges and non-http(s) schemes before fetching), max page size, max pages per crawl, and
  PDF size/type validation on upload (F02/F03).

## 5. Data lifecycle

- Cascade deletes flow Team → Bot → Documents/Conversations → Chunks/Messages, so deleting
  a bot fully removes its content and its embeddings.
- Consider (later) a "purge & re-ingest" action and per-team data export for compliance.
