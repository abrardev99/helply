# Tasks Summary

Progress log for the Helply build. Terminology note: the product's core entity was
renamed **Bot → Agent** (model, `agents` table, `agent_id` FKs, routes, UI). Feature files
below keep their original `F0x-bot-*` filenames for historical continuity.

_Last updated: 2026-07-04._

## ✅ Completed

### Features (all shipped, tested, on `main`)

| ID | Feature | Notes |
|----|---------|-------|
| F01 | Agent management (CRUD) | Team-scoped CRUD, policy, origins, status enum |
| F02 | Website ingestion (sitemap crawl) | Trigger + reconcile scheduler, SSRF guard, crawl/page limits |
| F03 | PDF ingestion | `smalot/pdfparser`, private-disk upload, `ImportPdfJob`, `DocumentType` enum |
| F04 | OpenAI credentials (BYO key) | `laravel/ai` SDK, encrypted keys, `ResolvesTenantKey`, masked UI, per-agent model config |
| F05 | Chunking strategy | `TextChunker` (~750-token chunks, ~13% overlap), `chunks.position` |
| F06 | Embedding generation | `EmbedChunksJob` via `Embeddings` SDK, idempotent + force, reconcile scheduler, progress |
| F07 | Public chat API endpoint | `POST /api/widget/{agent}/chat`, origin verify, rate limit, conversation persistence |
| F08 | Retrieval (vector + rerank) | `ChunkRetriever`: pgvector search + rerank, graceful no-key fallback |
| F09 | Answer generation | `SupportAgent` (context-only, injection-resistant), `ContextPacker`, `AnswerGenerator` |
| F10 | Guardrails | Relevance gate + `GroundingChecker`, `ChatPipeline`, `ChatOutcome` enum |
| F11 | Conversations & analytics | Team-scoped dashboard, flagged filter, light analytics |
| F12 | Embeddable widget loader | `GET /widget.js` (Shadow DOM), `session_id` persistence, copy-paste snippet |

Test suite: **177 passing / 700 assertions.**

### Additional work (post-features)

- **Sidebar navigation** — surfaced all top-level destinations in `app-sidebar.tsx`:
  Dashboard, Agents, Teams. (Conversations are per-agent; Settings/Logout live in the user menu.)
- **Bot → Agent rename** — renamed across the whole codebase and database:
  model, `agents` table, `agent_id` foreign keys (migrations edited directly for a fresh
  migrate — no new migrations), enums, policies, controllers/requests namespaces, routes and
  route names (`agents.*`), the public widget route (`/api/widget/{agent}/chat`), all
  services/jobs/actions/DTOs, and the frontend (pages, generated routes, types, components,
  `data-agent-id`). `SupportAgent` aliases the SDK's `Agent` contract to avoid colliding
  with the new `Agent` model.

## ⏳ Pending / Deferred

- **F12 browser smoke test** — a real Playwright browser test of the mounted widget is
  deferred (no served app + browser driver available in CI). The embed contract is covered
  deterministically by a feature test against the served `widget.js` and the snippet on the
  agent page.
- **Fresh migration** — the DB rename relies on `php artisan migrate:fresh` (no down/rename
  migrations were written, per the rename request).
- **Historical docs** — `features/F01-bot-management.md` and other `F0x` filenames retain the
  old "bot" naming; content still describes the feature accurately.

## Out of scope (noted in feature specs)

- OCR for scanned/image-only PDFs (F03).
- Widget theming/branding, streaming responses, file attachments, multi-language UI (F12).
- Heavy BI / top-unanswered-question grouping beyond simple counts (F11).
