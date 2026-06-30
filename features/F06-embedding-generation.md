# F06 — Embedding Generation Pipeline

**Phase:** 2 · **Status:** ⛔ todo · **Depends on:** F04, F05

## Goal

Turn chunk text into `text-embedding-3-small` (1536-dim) vectors using the customer's
OpenAI key and write them into `chunks.embedding`. Decoupled from ingestion so re-embedding
never requires re-crawling.

## Scope / tasks

Use the **Laravel AI SDK** `Embeddings` class — no hand-rolled HTTP client.

```php
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

$response = Embeddings::for($texts)                  // string[] (batched, ~96/call)
    ->dimensions(1536)
    ->generate(Lab::OpenAI, $bot->embedding_model);  // 'text-embedding-3-small'

$response->embeddings;                               // float[][], index-aligned with $texts
```

- **`Jobs\EmbedChunksJob(botId | documentId)`**:
  1. resolve + apply the tenant key via F04 (`withTenantKey()` / `config(['ai.providers.openai.key' => $key])`);
     if missing → flag + stop (no crash).
  2. select chunks `WHERE bot_id = ? AND embedding IS NULL` (batched).
  3. (if Option A from F05) re-chunk first.
  4. call `Embeddings::for(...)->dimensions(1536)->generate(Lab::OpenAI, $model)`, write
     vectors per chunk (index-aligned).
  5. only touch NULL embeddings (idempotent); support a `force` path that clears + recomputes.
- **Trigger:** dispatch after a document finishes ingesting (`done`), and/or a "re-embed
  bot" dashboard action, and/or a reconcile scheduler that finds NULL-embedding chunks.
- **Writing vectors:** insert in pgvector format (`[0.1,0.2,...]`). Confirm the chunk model's
  `embedding` cast handles array → vector correctly (currently cast `array`); adjust if the
  pgvector column needs a custom cast/raw update.
- **Failure handling:** the SDK throws on provider errors. On an auth/401 error → invalid
  key (flag team, stop); on transient/rate errors rely on the job `tries`/`backoff()`.
- **Progress:** expose "X / Y chunks embedded" per bot/document (count query or `embedded_at`).

## Data changes

- Optional `chunks.embedded_at` timestamp for progress/auditing.

## Failure handling

- 401 → invalid key: stop, flag the team, clear dashboard message. 429/5xx → backoff/retry.
- Partial failure: leave un-embedded chunks NULL so a re-run completes them.

## Acceptance criteria

- With a valid key, all of a bot's `done`-document chunks get non-NULL 1536-dim vectors.
- Re-running is a no-op for embedded chunks; `force` re-embeds.
- Missing/invalid key produces a clear, non-fatal state with no key leakage.
- A manual cosine query returns sensible nearest neighbours (sets up F08).

## Tests

- Job tests using the AI SDK embeddings fake (`Embeddings::fake()` auto-generates correctly
  dimensioned vectors; or `Embeddings::fake([[...]])` for fixed ones): NULL-only selection,
  idempotency, force path, missing-key path, provider-error handling. Assert vectors are
  stored and queryable.
- `Embeddings::assertGenerated(fn ($p) => $p->dimensions === 1536)` to assert the call shape;
  `Embeddings::fake()->preventStrayEmbeddings()` to catch accidental extra calls.
