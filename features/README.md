# Features

Requirements broken into independently pickable units. Pick one, build it, ship it. Each
file has: goal, phase, status, dependencies, scope/tasks, data changes, and acceptance
criteria. Architecture context lives in [`/docs`](../docs).

> **AI integration:** embeddings, retrieval, and chat use the **[Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk)**
> (`laravel/ai`):
> - `Embeddings` for vectors (F06) and question embedding (F08)
> - **Eloquent vector functions** (`whereVectorSimilarTo`/`orderByVectorDistance`) for
>   self-hosted pgvector search, plus `Reranking` (Cohere/Jina/Voyage) for precision (F08)
> - **Agents** for chat (F09) and the grounding guardrail (F10)
>
> The package is introduced in **F04** (needs dependency approval). BYO OpenAI keys are
> applied to the SDK config at runtime per request/job; the rerank-provider key is
> platform-level and reranking degrades gracefully if it's absent.
>
> **Testing:** all AI features must be tested with the SDK fakes — `Embeddings::fake()`,
> `Reranking::fake()`, `Agent::fake()`/`assertPrompted()` — so tests are deterministic and
> make no network calls. See [the SDK testing docs](https://laravel.com/docs/13.x/ai-sdk#testing).

## Status legend

- ✅ **done** — already in the codebase
- 🟡 **partial** — scaffolding/logic exists, needs finishing or exposing
- ⛔ **todo** — not started

## Phase 1 — Ingestion Engine

| ID | Feature | Status | Depends on |
|----|---------|--------|-----------|
| [F01](F01-bot-management.md) | Bot management (CRUD) | ⛔ todo | — |
| [F02](F02-website-ingestion.md) | Website ingestion (sitemap crawl) | 🟡 partial | F01 |
| [F03](F03-pdf-ingestion.md) | PDF ingestion | ⛔ todo | F01 |

## Phase 2 — Processing (Embeddings)

| ID | Feature | Status | Depends on |
|----|---------|--------|-----------|
| [F04](F04-openai-credentials.md) | OpenAI credential management (BYO key) | ⛔ todo | F01 |
| [F05](F05-chunking.md) | Chunking strategy | ⛔ todo | F02/F03 |
| [F06](F06-embedding-generation.md) | Embedding generation pipeline | ⛔ todo | F04, F05 |

## Phase 3 — Chat + Guardrails

| ID | Feature | Status | Depends on |
|----|---------|--------|-----------|
| [F07](F07-chat-api-endpoint.md) | Public chat API endpoint | ⛔ todo | F06 |
| [F08](F08-retrieval.md) | Retrieval (vector search + reranking) | ⛔ todo | F06 |
| [F09](F09-answer-generation.md) | Answer generation | ⛔ todo | F04, F08 |
| [F10](F10-guardrails.md) | Guardrails (relevance + grounding) | ⛔ todo | F08, F09 |
| [F11](F11-conversations-analytics.md) | Conversations & analytics | ⛔ todo | F07 |
| [F12](F12-widget-loader.md) | Embeddable widget loader | ⛔ todo | F07 |

## Suggested build order

```
F01 ─► F02 ─► F03        (Phase 1: get content in)
   │
   └─► F04 ─► F05 ─► F06  (Phase 2: make it searchable)
                     │
                     └─► F08 ─► F09 ─► F10 ─► F07 ─► F11 ─► F12   (Phase 3: answer & embed)
```

Retrieval (F08) and generation (F09) are the core; F07 wires them behind the public
endpoint; F10 wraps them in guardrails; F11/F12 finish the loop.
