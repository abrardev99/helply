# 04 — Data Model

All RAG tables use **UUID** primary keys (`HasUuids`) and are registered in the morph map
in `AppServiceProvider::configureModels()`. Models are unguarded globally (`Model::unguard()`),
so no `$fillable`/`$guarded`.

## ER diagram

```
┌──────────────┐        ┌──────────────┐
│    teams     │        │ team_members │      (existing multi-tenancy)
│──────────────│ 1    * │──────────────│
│ id (bigint)  │───────<│ team_id      │
│ name         │        │ user_id      │
│ slug         │        │ role         │
│ openai_api_  │        └──────────────┘
│   key (enc)* │  *= to be added (Phase 2 / F04)
└──────┬───────┘
       │ 1
       │
       │ *
┌──────▼───────────────────────┐
│            bots               │
│───────────────────────────────│
│ id (uuid)                     │
│ team_id (fk)                  │
│ name                          │
│ embed_origins (json)          │  ← allow-listed widget origins
│ status                        │
│ chat_model*  embedding_model* │  *= F04/F09 config
│ system_prompt*  confidence_*  │
└───┬───────────────────┬───────┘
    │ 1               1 │
    │ *               * │
┌───▼──────────────┐ ┌──▼───────────────────────┐
│    documents     │ │      conversations        │
│──────────────────│ │───────────────────────────│
│ id (uuid)        │ │ id (uuid)                 │
│ bot_id (fk)      │ │ bot_id (fk)               │
│ type (web|pdf)   │ │ session_id (visitor)      │
│ source_url       │ └──────────┬────────────────┘
│ title            │            │ 1
│ status (enum)    │            │ *
└───┬──────────────┘ ┌──────────▼────────────────┐
    │ 1              │        messages            │
    │ *              │───────────────────────────│
┌───▼──────────────┐ │ id (uuid)                 │
│     chunks       │ │ conversation_id (fk)      │
│──────────────────│ │ role (user|assistant)     │
│ id (uuid)        │ │ content                   │
│ bot_id (fk)      │ │ sources (json)            │ ← chunk ids cited
│ document_id (fk) │ │ retrieval_score (float)   │ ← top similarity
│ content (text)   │ │ flagged (bool)            │ ← guardrail refusal
│ embedding        │ └───────────────────────────┘
│   vector(1536)   │
│   + HNSW cosine  │
└──────────────────┘
```

## Tables as they exist today

### `bots`
| column | type | notes |
|--------|------|-------|
| id | uuid | PK |
| team_id | fk → teams | cascade delete |
| name | string | |
| embed_origins | json (nullable) | cast `array` — widget origin allow-list |
| status | string | default `active` |

> **To add (F04/F09):** `embedding_model`, `chat_model`, `system_prompt`,
> `confidence_threshold`, optional per-bot `openai_api_key`. Plus a `BotStatus` enum.

### `documents`
| column | type | notes |
|--------|------|-------|
| id | uuid | PK |
| bot_id | fk → bots | cascade delete |
| type | string | `web` \| `pdf` — **promote to `DocumentType` enum (F03)** |
| source_url | string (nullable) | the page URL, or stored path for PDFs |
| title | string (nullable) | |
| status | string | `DocumentStatus` enum, indexed, default `pending` |

### `chunks`
| column | type | notes |
|--------|------|-------|
| id | uuid | PK |
| bot_id | fk → bots | denormalized for fast tenant-scoped vector search |
| document_id | fk → documents | cascade delete |
| content | text | the chunk text |
| embedding | **vector(1536)** nullable | NULL until Phase 2; HNSW `vector_cosine_ops` index |

> 1536 dims matches `text-embedding-3-small`. The column is nullable so ingestion can store
> text before embeddings exist. Changing dimensions later requires a fresh migration.

### `conversations`
| column | type | notes |
|--------|------|-------|
| id | uuid | PK |
| bot_id | fk → bots | |
| session_id | string | indexed — anonymous visitor/session correlation |

### `messages`
| column | type | notes |
|--------|------|-------|
| id | uuid | PK |
| conversation_id | fk → conversations | cascade delete |
| role | string | `user` \| `assistant` — **promote to `MessageRole` enum** |
| content | text | |
| sources | json (nullable) | cast `array` — cited chunk ids / urls |
| retrieval_score | float (nullable) | best cosine similarity for the turn |
| flagged | boolean | indexed — true when a guardrail produced a refusal |

## Document status lifecycle

```
       create Document
            │
            ▼
       ┌─────────┐  job claims it   ┌────────────┐  text stored   ┌──────┐
       │ pending │ ───────────────► │ processing │ ─────────────► │ done │
       └─────────┘                  └─────┬──────┘                └──────┘
                                          │ fetch/parse error
                                          ▼
                                     ┌────────┐
                                     │ failed │
                                     └────────┘
```

Embedding state is tracked separately and implicitly: a chunk is "embedded" when
`embedding IS NOT NULL`. (F06 may add an explicit `embedded_at` / per-document embedding
counter for dashboard progress — see that feature.)

## Indexing notes

- `chunks` HNSW cosine index already created:
  `CREATE INDEX ON chunks USING hnsw (embedding vector_cosine_ops)`.
- Retrieval queries **must** filter `WHERE bot_id = ?` before/with the vector search to
  keep tenants isolated and the candidate set small.
- `documents.status` and `messages.flagged` are indexed for dashboard list/filter views.
