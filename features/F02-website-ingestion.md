# F02 — Website Ingestion (Sitemap Crawl)

**Phase:** 1 · **Status:** 🟡 partial · **Depends on:** F01

## Goal

Given a website URL, discover its pages via `sitemap.xml`, fetch each page, extract
readable text, and store it as chunks under web-type `Document`s — idempotently and with
retries.

## What already exists ✅

- `CrawlSiteJob(botId, seedUrl)` — sitemap discovery (incl. nested sitemap indexes to depth
  3), idempotent `firstOrCreate` per URL, claims pages → `processing` before dispatch,
  dispatches a named `Bus::batch` of `ProcessPageJob` with `allowFailures()`, XXE-hardened
  parsing, timeouts, retries, and a `failed()` handler.
- `ProcessPageJob(documentId)` — HTTP GET, DOM chrome-stripping, `<title>` + readable text
  extraction, idempotent delete-then-insert of one chunk (NULL embedding), status
  transitions, bounded retries/backoff.

## What's missing / tasks ⛔

- **Trigger path:** wire a way to start a crawl — dispatch `CrawlSiteJob` when a customer
  adds a URL (from F01's bot detail / a "sources" controller), and/or the scheduled command
  referenced in `CrawlSiteJob`'s comments (a 5-minute tick that picks up pending web seeds).
  Document and implement whichever is chosen (recommend: explicit dispatch on add **plus**
  a reconcile scheduler).
- **Dashboard surface:** form to add a website URL to a bot; list documents with status +
  retry/failed indicators; "re-crawl" action.
- **SSRF protection:** before fetching a user-supplied URL, reject non-`http(s)` schemes and
  resolve+block private/loopback/link-local IP ranges. (See
  [docs/05-security-and-tenancy.md](../docs/05-security-and-tenancy.md).)
- **Limits:** max pages per crawl, max page size, overall crawl timeout — guard against
  huge sites.
- **Tests:** the jobs likely need/deserve coverage (mock `Http`, fake sitemap + pages,
  assert documents/chunks + status, idempotency on re-run, failure handling).

## Data changes

- None (uses existing `documents`/`chunks`). Possibly a `source_url` on the bot or a
  lightweight "ingest source" record if a bot can have multiple seed sites (decide in F01).

## Acceptance criteria

- Adding a valid site URL crawls its sitemap and produces `done` web documents with stored
  chunk text; the seed page is always included.
- Re-crawling does not duplicate documents or chunks.
- A site with no sitemap fails gracefully (documents → `failed`, logged), not silently
  stuck in `processing`.
- User-supplied URLs targeting internal/private addresses are rejected.
