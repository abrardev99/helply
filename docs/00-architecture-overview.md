# 00 — Architecture Overview

## The whole system on one page

```
                        ┌──────────────────────────── HELPLY (Laravel 13) ────────────────────────────┐
                        │                                                                              │
  CUSTOMER (tenant)     │   DASHBOARD (Inertia + React, auth via Fortify, scoped to a Team)            │
  ───────────────       │   • create Bot                  • paste OpenAI key (encrypted)               │
   logs in, manages     │   • add website URL / upload    • view documents + ingest status            │
   bots & content  ───► │     PDFs                         • configure widget (origins, prompt)        │
                        │                                                                              │
                        ├──────────────────────────────────────────────────────────────────────────── │
                        │                                                                              │
                        │  PHASE 1: INGESTION ENGINE          PHASE 2: PROCESSING                       │
                        │  ┌────────────────────────┐         ┌──────────────────────────┐             │
                        │  │ CrawlSiteJob            │         │ EmbedChunksJob           │             │
                        │  │  └► ProcessPageJob ×N   │  text   │  • read chunks w/ NULL   │             │
                        │  │ ImportPdfJob            │ ──────► │    embedding             │             │
                        │  │  └► ProcessPdfJob       │ chunks  │  • OpenAI embeddings     │ ──► pgvector │
                        │  │ (Documents → Chunks)    │         │    text-embedding-3-small│   embedding  │
                        │  └────────────────────────┘         │  • write vector(1536)    │   column     │
                        │                                      └──────────────────────────┘             │
                        │                                                                              │
                        └──────────────────────────────────────────────────────────────────────────── │
                                                                                                       │
  VISITOR (anonymous)        PHASE 3: CHAT + GUARDRAILS                                                 │
  ───────────────            ┌─────────────────────────────────────────────────────────────────────┐  │
   on customer's website     │  POST /api/widget/{bot}/chat   (public, origin-checked, rate-limited) │  │
                             │                                                                       │  │
   ┌─────────────┐  question │   1. embed question  ─► AI SDK Embeddings                             │  │
   │ chat widget │ ────────► │   2. vector search   ─► whereVectorSimilarTo (wide candidate set)     │  │
   │  (JS embed) │           │   3. rerank          ─► Reranking → top-N most relevant chunks        │  │
   │             │           │   4. GUARDRAIL gate  ─► relevant enough? if not → canned refusal      │  │
   │             │ ◄──────── │   5. generate        ─► gpt-5.4 Agent: answer ONLY from context       │  │
   └─────────────┘  answer   │   6. GUARDRAIL check ─► answer on-topic & grounded? else → refusal    │  │
                             │   7. persist Conversation + Message (+ sources, score, flagged)       │  │
                             └─────────────────────────────────────────────────────────────────────┘  │
                                                                                                       │
                                                          OpenAI (customer's own key) ◄────────────────┘
```

## Request/data flow, narrated

**Ingestion (Phase 1).** From the dashboard a customer adds a website URL or uploads a
PDF. Each becomes a `Document` (`type = web | pdf`) owned by a `Bot`. Background jobs
fetch/parse the source, extract readable text, split it into `Chunk` rows, and set the
document `status` (`pending → processing → done | failed`). Embeddings are **not** created
here — chunks land with a NULL `embedding`.

**Processing (Phase 2).** A separate job picks up chunks with NULL embeddings, calls the
customer's OpenAI key with `text-embedding-3-small`, and writes the 1536-dim vector into
the `chunks.embedding` pgvector column. This is decoupled from ingestion so re-embedding
(e.g. model change) never requires re-crawling.

**Chat (Phase 3).** The embedded widget calls a **public** endpoint. The backend embeds
the visitor's question, runs a vector similarity search over that bot's chunks
(`whereVectorSimilarTo`) for a wide candidate set, **reranks** those candidates down to the
top-N most relevant (`Reranking`), applies a **guardrail** (is the top reranked chunk
relevant enough?), and only then asks the chat agent to answer *using the retrieved context
only*. A second guardrail validates the generated answer is grounded/on-topic. Off-topic or low-confidence requests get a canned
"I can only help with questions about <site>" reply. Everything is logged as a
`Conversation` + `Message`.

## Layer / namespace map

```
app/
├─ Models/            Bot, Document, Chunk, Conversation, Message   (✅ exist)
├─ Enums/             DocumentStatus (✅), + DocumentType, MessageRole, ChatOutcome (⛔ add)
├─ Jobs/              CrawlSiteJob (✅), ProcessPageJob (✅),
│                     ImportPdfJob, ProcessPdfJob, EmbedChunksJob              (⛔ add)
├─ Ai/
│  ├─ Agents/         (⛔ add) SupportAgent (chat), GroundingChecker (guardrail #2)
│  │                  — Laravel AI SDK agents (php artisan make:agent)
│  └─ Support/        (⛔ add) ResolvesTenantKey — sets the BYO key on the SDK at runtime
├─ Services/          (⛔ add) Ingestion/TextChunker,
│                     Retrieval/ChunkRetriever (vector search via whereVectorSimilarTo + Reranking),
│                     Chat/Guardrail (relevance gate + grounding orchestration)
├─ Actions/           (⛔ add, e.g. Chat/HandleChatMessage) — orchestration
├─ Data/              (⛔ add) typed DTOs for chat request/response, retrieval hits
├─ Http/
│  ├─ Controllers/    Dashboard CRUD (Bots, Documents) + Api/WidgetChatController (⛔ add)
│  ├─ Requests/       FormRequests for the above
│  └─ Middleware/     EnsureTeamMembership (✅), VerifyWidgetOrigin (⛔ add)
└─ Policies/          BotPolicy, DocumentPolicy (⛔ add)
```

> **Embeddings + chat go through the [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk)**
> (`laravel/ai`), not hand-rolled OpenAI HTTP clients. Embeddings use the `Embeddings`
> class; chat uses **Agents**. This package must be added (`composer require laravel/ai`) —
> a dependency change requiring approval. See F04/F06/F09.

## Key design decisions

| Decision | Choice | Why |
|----------|--------|-----|
| Embeddings decoupled from ingestion | Separate `EmbedChunksJob` keyed on NULL embeddings | Re-embed without re-crawl; survives key/model changes |
| BYO OpenAI key storage | Encrypted column on **Team** (`encrypted` cast), optional per-bot override | One key per customer account; never exposed to the browser |
| AI provider integration | **Laravel AI SDK** — `Embeddings` for vectors, **Agents** for chat, `Reranking` for retrieval precision | First-class Laravel API, structured output, conversation/tooling primitives |
| Per-tenant key + the SDK | Override `config(['ai.providers.openai.key' => $key])` at runtime, inside the isolated job/request scope | SDK reads keys from app config; PHP request/queue isolation makes runtime override safe |
| Vector search | SDK's **Eloquent vector query functions** (`whereVectorSimilarTo` / `orderByVectorDistance`) over our own `chunks.embedding` pgvector column — **not** provider-hosted `Laravel\Ai\Stores` | Keep embeddings self-hosted: data residency, tenant isolation via `WHERE bot_id`, BYO key, no per-provider file upload |
| Retrieval precision | Two-stage: vector search for a wide candidate set → **rerank** to top-N (`Reranking`, Cohere/Jina/Voyage) | Vector recall is fuzzy; a cross-encoder rerank sharply improves which chunks reach the model |
| Vector store | Postgres + pgvector (already enabled), HNSW + cosine | No extra infra; co-located with relational data; tenant filtering is a normal `WHERE bot_id` |
| Guardrails | Two-stage: pre-retrieval relevance gate + post-generation grounding check | Cheap retrieval-score gate catches most off-topic; LLM check catches subtle drift |
| Widget auth | Public endpoint + per-bot **origin allow-list** + rate limit (no user auth) | Visitors are anonymous; the bot id is public by design, origin + throttle limit abuse |
| Tenant isolation | Every RAG row carries `bot_id` (→ `team_id`); queries always scope by bot | Hard isolation at the query layer, enforced by policies in the dashboard |

See [04-data-model.md](04-data-model.md) and [05-security-and-tenancy.md](05-security-and-tenancy.md)
for the details behind these.
