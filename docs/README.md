# Helply — Backend Architecture Docs

Helply is a multi-tenant RAG (Retrieval-Augmented Generation) chatbot platform. A
customer connects their own OpenAI key, points a **Bot** at their website and/or PDF
documents, and embeds a chat widget on their site. The widget answers visitor questions
using only the customer's ingested content, and politely refuses anything off-topic.

The system is split into three phases. Each phase is independently shippable and maps
to a set of features in [`/features`](../features).

| Phase | Name | What it does | Doc |
|-------|------|--------------|-----|
| 1 | **Ingestion Engine** | Accept website URLs + PDFs, crawl/parse, store raw text as chunks | [01-phase-1-ingestion-engine.md](01-phase-1-ingestion-engine.md) |
| 2 | **Processing** | Generate embeddings (BYO OpenAI key, `text-embedding-3-small`) and store in pgvector | [02-phase-2-processing-embeddings.md](02-phase-2-processing-embeddings.md) |
| 3 | **Chat + Guardrails** | Public chat endpoint, retrieval, answer generation, off-topic refusal | [03-phase-3-chat-guardrails.md](03-phase-3-chat-guardrails.md) |

## Reading order

1. [00-architecture-overview.md](00-architecture-overview.md) — the whole system on one page
2. [04-data-model.md](04-data-model.md) — tables, relationships, the vector column
3. [05-security-and-tenancy.md](05-security-and-tenancy.md) — BYO key storage, tenant isolation, widget origin allow-listing
4. Phase docs 01 → 03

## Current state of the codebase (2026-06-30)

This is **not** a greenfield design — some of Phase 1 already exists. The docs note status
inline. Summary:

- ✅ Data model exists: `Bot`, `Document`, `Chunk`, `Conversation`, `Message` models +
  migrations, all in the morph map.
- ✅ pgvector enabled, `chunks.embedding vector(1536)` column + HNSW cosine index.
- ✅ Website ingestion works: `CrawlSiteJob` (sitemap discovery) + `ProcessPageJob`
  (fetch + readable-text extraction → one chunk per page, NULL embedding).
- ⛔ PDF ingestion — not built.
- ⛔ OpenAI credential storage — not built.
- ⛔ Embedding generation — `embedding` column is always NULL today.
- ⛔ Chat endpoint, retrieval, generation, guardrails, widget — not built.

## Stack

- **Laravel 13** / PHP 8.3, **Inertia v3 + React 19** (dashboard), Fortify auth.
- **PostgreSQL** + **pgvector** (HNSW, cosine).
- Queue + cache on the **database** driver (see `.env`); jobs use `Bus::batch`.
- Multi-tenancy via **Teams** (`team_members` pivot, `current_team` route prefix).
- **AI** via the **[Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk)** (`laravel/ai`,
  to be added): `Embeddings` for vectors, **Agents** for chat + the grounding guardrail.
  The customer's BYO OpenAI key is applied to the SDK config at runtime per request/job.
