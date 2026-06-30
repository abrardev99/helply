# F05 — Chunking Strategy

**Phase:** 2 · **Status:** ⛔ todo · **Depends on:** F02 / F03

## Goal

Split stored document text into retrieval-sized, slightly overlapping chunks so embeddings
and retrieval (Phase 3) work well. Today ingestion stores **one chunk per web page**, which
is too coarse.

## Approach

- `Services\Ingestion\TextChunker` — pure, testable service:
  `chunk(string $text): array<int, string>`.
- Target ~**500–800 tokens** per chunk with ~**10–15% overlap**, split preferentially on
  paragraph/sentence boundaries (avoid cutting mid-sentence). Use a token estimate
  (chars/4 heuristic is fine to start; precise tokenizer optional).
- Where it runs (pick one — see [docs/02](../docs/02-phase-2-processing-embeddings.md)):
  - **Recommended (Option A):** ingestion keeps storing one raw chunk/document; a re-chunk
    step runs right before embedding (in/just ahead of F06), replacing the raw chunk with
    properly sized chunks.
  - **Option B:** move `TextChunker` into the ingestion jobs (`ProcessPageJob`/`ImportPdfJob`)
    so `chunks` are already embedding-sized.
- Idempotency: re-chunking a document is delete-then-insert within a transaction (same
  pattern already used in `ProcessPageJob`).

## Data changes

- None required. (Optional: `chunks.position`/`ordinal` int to preserve chunk order within a
  document for nicer citations/context assembly.)

## Acceptance criteria

- A long page/PDF produces multiple coherent chunks within the target token range, with
  overlap, not split mid-sentence where avoidable.
- Re-chunking is idempotent (no duplicates).
- Chunk count and sizes are sane on representative fixtures (unit tests over the service).

## Tests

- Unit tests for `TextChunker`: short text → 1 chunk; long text → N chunks with overlap;
  boundary behavior; empty/whitespace input.
