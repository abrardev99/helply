# F03 — PDF Ingestion

**Phase:** 1 · **Status:** ⛔ todo · **Depends on:** F01

## Goal

Let a customer upload PDF documents to a bot; extract their text and store it as chunks
under pdf-type `Document`s — mirroring the web ingestion lifecycle.

## Scope / tasks

- **Upload:** controller + `StorePdfRequest` validating mime (`application/pdf`), max size,
  and ownership. Store the file (private disk, e.g. `storage/app/private/pdfs/{bot}/...`).
- **Document record:** create a `Document` with `type = pdf`, `source_url` = stored path
  (or a separate `path` column), `status = pending`.
- **`ImportPdfJob(documentId)`** — read the stored PDF, extract text per page, split into
  multiple `Chunk`s (delete-then-insert for idempotency), set `title` (from filename or PDF
  metadata), transition `done` / `failed`, bounded retries + `failed()` handler. Mirror the
  patterns in `ProcessPageJob`.
- **Extraction library:** choose one (needs approval per CLAUDE.md — don't add deps
  silently). Candidates: `smalot/pdfparser` (pure PHP) or shelling to `pdftotext`
  (poppler). Recommend `smalot/pdfparser` for portability.
- **Enum:** promote `documents.type` to a `DocumentType` enum (`Web`, `Pdf`) with
  `label()`; cast on the model; use in factory/migration default.

## Data changes

- `DocumentType` enum + cast on `Document::casts()`.
- Optionally a dedicated `path` column on `documents` (vs reusing `source_url`) — decide
  for clarity; `source_url` nullable already exists.

## Acceptance criteria

- Uploading a valid PDF creates a `pdf` document that becomes `done` with multiple chunks
  of extracted text.
- Re-processing the same document does not duplicate chunks.
- Non-PDF / oversized uploads are rejected with a clear validation error.
- A corrupt/unreadable PDF marks the document `failed`, not stuck.

## Out of scope

- OCR for scanned/image-only PDFs (note as a future feature).
- Token-aware chunk sizing — that's F05 (this feature can do simple page/paragraph splits,
  which F05 may refine).

## Tests

- Feature/job tests with a fixture PDF: extraction → chunks, idempotency, validation,
  failure path (fake a bad file).
