# 02 — Phase 2: Processing (Embeddings)

**Goal:** turn stored chunk text into vector embeddings using the customer's own OpenAI
key (`text-embedding-3-small`, 1536 dims) and write them into the `chunks.embedding`
pgvector column.

This phase is **deliberately decoupled** from ingestion: ingestion stores text with a NULL
embedding; processing fills in embeddings. So you can re-embed (model change, key change,
backfill) without re-crawling, and ingestion never blocks on OpenAI availability.

## Pipeline

```
   trigger: chunks created (event/after ingest)  OR  manual "re-embed bot"  OR  scheduler
        │
        ▼
   EmbedChunksJob(botId | documentId)                                   ⛔ TO BUILD (F06)
        │
        ├─ resolve key:  bot.openai_api_key ?? team.openai_api_key       (F04)
        │     └─ no key? → mark needs_key, surface in dashboard, stop
        │
        ├─ select chunks WHERE bot_id = ? AND embedding IS NULL          (only un-embedded)
        │
        ├─ batch them (e.g. 96 inputs/call) ─► Laravel AI SDK Embeddings
        │     Embeddings::for($texts)->dimensions(1536)->generate(Lab::OpenAI, 'text-embedding-3-small')
        │     • SDK handles the HTTP/retry; on 401 → invalid key, stop + flag team
        │
        └─ UPDATE chunks SET embedding = vector(...) per chunk           (idempotent)
                 (re-running only touches NULL ones, unless force re-embed)
```

### Embedding call (Laravel AI SDK)

```php
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

// $key resolved per-tenant (F04); set it on the SDK for THIS job's scope only:
config(['ai.providers.openai.key' => $key]);

$response = Embeddings::for($texts)              // string[] of chunk contents (batched)
    ->dimensions(1536)
    ->generate(Lab::OpenAI, $bot->embedding_model); // default 'text-embedding-3-small'

$response->embeddings; // float[][] — index-aligned with $texts
```

## Components to build (F04, F05, F06)

| Component | Responsibility |
|-----------|----------------|
| `Team.openai_api_key` (encrypted col) + dashboard form | Store BYO key securely (F04) |
| `Bot` config cols: `embedding_model`, optional `openai_api_key` | Per-bot model + key override (F04) |
| `App\Ai\Support\ResolvesTenantKey` | Resolve the BYO key and set it on the SDK config at runtime (F04) |
| `Services\Ingestion\TextChunker` | Split `content` into token-aware, overlapping chunks **before** embedding (F05) |
| `Jobs\EmbedChunksJob` | Resolve key → `Embeddings::for(...)->generate(...)` → store, idempotent on NULL (F06) |

### Chunking note (F05)

Today ingestion stores **one chunk per web page** — too coarse for good retrieval. F05
introduces real chunking (e.g. ~500–800 tokens with ~10–15% overlap, split on sentence/
paragraph boundaries). Where it runs is a choice:

- **Option A (recommended):** keep Phase-1 ingestion storing one "raw" chunk/document, then
  F05 re-chunks into the final `chunks` right before embedding. Clean separation; ingestion
  stays dumb.
- **Option B:** move chunking into the ingestion jobs so `chunks` are already
  embedding-sized. Fewer rows rewritten, but couples chunking to crawling.

The data model supports either (a document has many chunks). Decide in F05; default to A.

## Embedding strategy

- **Model:** `text-embedding-3-small`, `dimensions(1536)` (matches the column + HNSW
  index). Configurable per bot for future models, but changing dims needs a migration.
- **Batching:** pass up to ~96 chunk texts to a single `Embeddings::for([...])` call.
- **Idempotency:** only embed `embedding IS NULL` chunks by default; a `--force` / "re-embed"
  path clears + recomputes for a bot.
- **Failure handling:** the SDK surfaces provider errors — 401 → invalid key (stop, flag
  team, dashboard message); rate/transient errors → rely on the job's `tries`/`backoff()`.
  Partial failure → leave the un-embedded chunks NULL so a re-run finishes them.
- **Caching:** the SDK can cache embeddings (`config/ai.php` `caching.embeddings`), but for
  chunk ingestion the NULL-only selection already avoids re-embedding — leave SDK caching
  off for this path to avoid double-caching.
- **Progress:** dashboard should show "X / Y chunks embedded" per bot/document. Consider an
  `embedded_at` timestamp or a counted query (`COUNT(embedding IS NOT NULL)`).

## Acceptance criteria

- With a valid team OpenAI key, every `done` document's chunks get a non-NULL 1536-dim
  embedding.
- Re-running the job is a no-op for already-embedded chunks (unless forced).
- Invalid/missing key produces a clear, non-fatal dashboard state — no crashes, no leaked
  key in logs.
- A vector cosine query over a bot's chunks returns sensible nearest neighbours (sanity
  test for Phase 3).

## Features in this phase

- **F04** — OpenAI credential management (BYO key)
- **F05** — Chunking strategy
- **F06** — Embedding generation pipeline
