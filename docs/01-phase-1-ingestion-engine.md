# 01 — Phase 1: Ingestion Engine

**Goal:** accept content sources (website URLs and PDF documents), fetch/parse them into
plain readable text, and persist that text as `Chunk` rows under a `Document` under a
`Bot`. **No embeddings in this phase** — chunks are stored with a NULL `embedding`.

## Scope

| In scope | Out of scope (later phase) |
|----------|----------------------------|
| Bot CRUD (dashboard) | Embedding generation (Phase 2) |
| Website ingestion via sitemap crawl | Retrieval / chat (Phase 3) |
| PDF upload + text extraction | Re-embedding |
| Document status lifecycle, retries, idempotency | Token-aware chunking (Phase 2, F05) |

## Pipeline

```
                          ┌────────────────────────── WEBSITE ──────────────────────────┐
  customer adds URL  ───► │ CrawlSiteJob(botId, seedUrl)                                  │
                          │   • derive {scheme}://{host}/sitemap.xml                      │
                          │   • parse sitemap (follows <sitemapindex> up to depth 3)      │
                          │   • firstOrCreate Document per URL  (type=web, idempotent)    │
                          │   • claim all → status=processing                             │
                          │   • Bus::batch( ProcessPageJob ×N ).allowFailures()           │
                          │                                                               │
                          │ ProcessPageJob(documentId)         ── ~10 concurrent via      │
                          │   • HTTP GET page                     queue workers           │
                          │   • strip chrome (nav/header/footer/script/style…)            │
                          │   • extract <title> + readable body text                      │
                          │   • delete-then-insert ONE Chunk (idempotent), embedding=NULL │
                          │   • Document → status=done  (or failed on error)              │
                          └───────────────────────────────────────────────────────────────┘

                          ┌────────────────────────── PDF ──────────────────────────────┐
  customer uploads PDF ─► │ ImportPdfJob(documentId)            ⛔ TO BUILD (F03)          │
                          │   • read stored file                                          │
                          │   • extract text per page (smalot/pdfparser or pdftotext)     │
                          │   • split into multiple Chunks                                │
                          │   • Document → status=done | failed                           │
                          └───────────────────────────────────────────────────────────────┘
```

## What already exists ✅

- `Bot`, `Document`, `Chunk` models + migrations; `DocumentStatus` enum
  (`pending/processing/done/failed`).
- `CrawlSiteJob` — sitemap discovery (incl. nested sitemap indexes), idempotent
  `firstOrCreate` per URL, claims pages to `processing` before dispatch (so the scheduler
  doesn't double-crawl), dispatches a named `Bus::batch` with `allowFailures()`.
- `ProcessPageJob` — fetch, DOM-based readable-text extraction (built-in ext-dom, no extra
  package), idempotent delete-then-insert of one chunk, status transitions, `failed()`
  safety net, bounded retries/backoff.
- XXE hardening (`LIBXML_NONET`), timeouts, retries.

## What's missing ⛔

1. **Dashboard surface** — there's no controller/route to create a bot or kick off a
   crawl, and no UI to upload a PDF. (F01, F02, F03)
2. **PDF path** — no `ImportPdfJob`, no PDF text extraction, no upload handling/validation.
   (F03)
3. **Triggering** — `CrawlSiteJob` references a "five-minute scheduler tick" picking up
   pending web seeds; that scheduled command / explicit dispatch-on-create needs to be
   wired and documented. (F02)
4. **SSRF / safety limits** on user-supplied URLs and PDF uploads. (F02/F03, see
   [05-security-and-tenancy.md](05-security-and-tenancy.md))
5. **Current chunking is coarse** — one chunk per web page. Good enough to store text;
   real chunking (size/overlap, token-aware) is deferred to F05 in Phase 2, because it's
   tightly coupled to the embedding step.

## Idempotency & retries (design rules)

- A document is keyed on `(bot_id, source_url)` for web pages → re-crawl never duplicates.
- Chunk writes are **delete-then-insert within a transaction** per document → retries and
  re-crawls never duplicate chunks.
- Pages are claimed to `processing` *before* batch dispatch to avoid concurrent
  re-triggering.
- Every job has bounded `tries` + `backoff()` and a `failed()` terminal handler that marks
  the document `failed`.

## Acceptance criteria for "Phase 1 done"

- A customer can create a bot, add a website URL, and see its pages become `done` with
  stored chunk text.
- A customer can upload a PDF and see it become `done` with multiple stored chunks.
- Failures are visible (`failed` status) and retryable; nothing is silently lost or
  duplicated.
- No embeddings are attempted yet; `chunks.embedding` is NULL across the board.

## Features in this phase

- **F01** — Bot management (CRUD)
- **F02** — Website ingestion (crawl) — *harden + expose existing jobs*
- **F03** — PDF ingestion
