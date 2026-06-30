# F08 — Retrieval (Vector Search + Reranking)

**Phase:** 3 · **Status:** ⛔ todo · **Depends on:** F06

## Goal

Given a visitor question, find the most relevant chunks for a bot in two stages: a **vector
similarity search** (high recall) over our own pgvector column using the Laravel AI SDK's
Eloquent vector functions, then a **rerank** (high precision) that collapses the candidate
set to the top-N chunks actually fed to the model.

## Scope / tasks

- **`Services\Retrieval\ChunkRetriever`** —
  `retrieve(Bot $bot, string $question, int $candidates = 25, int $topN = 6): RetrievalResult`.

  1. **Embed the question** via the AI SDK (tenant key applied, F04) — same model the chunks
     were embedded with:
     ```php
     $queryEmbedding = Embeddings::for([$question])->dimensions(1536)
         ->generate(Lab::OpenAI, $bot->embedding_model)->embeddings[0];
     ```
  2. **Vector search** with the SDK's Eloquent vector functions over `chunks.embedding`
     (bot-scoped, wide candidate set):
     ```php
     $candidates = Chunk::query()
         ->where('bot_id', $bot->id)
         ->whereNotNull('embedding')
         ->orderByVectorDistance('embedding', $queryEmbedding)  // HNSW cosine index
         ->limit($candidates)
         ->get();
     ```
     (`whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: …)` is an
     alternative/addition to pre-filter weak matches.)
  3. **Rerank** the candidates and keep the top-N (collection `rerank` macro):
     ```php
     $ranked = $candidates->rerank(
         by: 'content', query: $question, limit: $topN, provider: Lab::Cohere,
     );
     ```
     Each result carries a rerank `->score` and `->index`. **If no rerank provider key is
     configured, skip reranking** and keep the vector order (log that it was skipped).
  4. Return the top-N chunks + their rerank scores + the **top rerank score** (feeds
     Guardrail #1).

- **DTO:** `Data\RetrievalResult { hits: RetrievalHit[], topScore: float, reranked: bool }`,
  `RetrievalHit { chunkId, content, documentId, score }`.
- **Tenant safety:** always filter `bot_id` first — non-negotiable.
- **Why not `Laravel\Ai\Stores`:** the SDK's provider-hosted vector stores upload files to
  OpenAI/Gemini. We deliberately keep embeddings self-hosted in Postgres (data residency,
  tenant isolation, BYO key) and use the Eloquent vector query functions instead. See
  [docs/00](../docs/00-architecture-overview.md).

## Reranking provider (important)

Reranking uses **Cohere / Jina / VoyageAI** — a *different* provider from the customer's
OpenAI key (which covers embeddings + chat only).

- Treat the rerank key as **platform-level** config (e.g. `COHERE_API_KEY` in `.env`,
  surfaced via `config/ai.php`), not customer BYO.
- Make reranking **optional and degrade gracefully**: when the key is absent, fall back to
  raw vector order and set `reranked = false`. The relevance gate (F10) then thresholds on
  vector similarity instead of rerank score.

## Data changes

- None. (Confirm `chunks.embedding` works with `orderByVectorDistance`/`whereVectorSimilarTo`;
  the column + HNSW index already exist. Adjust the `embedding` cast if the SDK expects a
  specific format.)

## Acceptance criteria

- For an embedded bot, an on-topic question returns the top-N reranked chunks with a
  meaningful `topScore`.
- Results are strictly limited to the queried bot's chunks (cross-tenant isolation, tested).
- With no rerank key, retrieval still works (vector order, `reranked = false`).
- Returns an empty result + low/zero `topScore` when nothing relevant exists (feeds
  guardrail #1).

## Tests

Use the AI SDK fakes so tests are deterministic and make no network calls:

```php
Embeddings::fake();   // question embedding
Reranking::fake([     // control the ranked order/scores
    [new RankedDocument(index: 0, document: '…', score: 0.95), /* … */],
]);
```

- Feature test: seed a bot with embedded chunks, query, assert top-N ordering follows the
  faked rerank scores and that `bot_id` scoping holds (cross-bot isolation).
- Test the **no-rerank-key** fallback: assert `reranked = false` and vector ordering.
- `Reranking::assertReranked(fn ($p) => $p->limit === 6)` to assert the rerank was invoked
  with the expected query/limit.
