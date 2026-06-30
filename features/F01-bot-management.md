# F01 — Bot Management (CRUD)

**Phase:** 1 · **Status:** ⛔ todo · **Depends on:** — (the `bots` table + model exist)

## Goal

Let an authenticated team member create and manage **Bots** from the dashboard. A bot is
the container that owns documents, chunks, conversations, and widget configuration.

## Scope / tasks

- `BotsController` (CRUD: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`)
  under the `{current_team}` prefix, behind `auth` + `verified` + `EnsureTeamMembership`.
- Route-model binding for `Bot` **scoped to the current team** (never resolve a bot from
  another team).
- `BotPolicy` — only members of the owning team can view/manage.
- FormRequests: `StoreBotRequest`, `UpdateBotRequest` (validate `name`, `embed_origins[]`).
- Inertia pages (minimal): bot list, create/edit form, bot detail (shows documents +
  ingest status). Frontend stays simple per the brief.
- Register routes; generate Wayfinder actions for the frontend.

## Data changes

- None required to ship CRUD. (Bot config columns — `chat_model`, `embedding_model`,
  `system_prompt`, `confidence_threshold`, per-bot `openai_api_key` — are added in
  **F04/F09**; keep this feature to name + origins.)
- Consider a `BotStatus` enum (`active`/`paused`) to replace the raw `status` string per
  project enum conventions.

## Acceptance criteria

- A team member can create, rename, and delete a bot; deletion cascades documents/chunks/
  conversations.
- A member of team A cannot see or mutate team B's bots (policy + scoped binding enforced;
  covered by a feature test).
- `embed_origins` is editable and validated as a list of origins.

## Tests

- Feature tests: CRUD happy paths, authorization (cross-team 403), validation failures.
