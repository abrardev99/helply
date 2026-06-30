# F12 — Embeddable Widget Loader (Minimal)

**Phase:** 3 · **Status:** ⛔ todo · **Depends on:** F07

## Goal

The thing a customer pastes into their site's `<body>` — a single `<script>` that mounts a
small chat UI and talks to the public chat endpoint (F07). Frontend stays intentionally
minimal per the brief; this feature is about the *embed contract*, not polish.

## Scope / tasks

- **Embed snippet:** a script tag carrying the bot id, e.g.
  ```html
  <script src="https://helply.app/widget.js" data-bot-id="{uuid}" defer></script>
  ```
- **`widget.js`:** a self-contained bundle (served from `public/` or a Vite-built asset)
  that:
  - injects a launcher button + chat panel (Shadow DOM to avoid host CSS bleed).
  - generates/persists a `session_id` (localStorage) for conversation continuity.
  - `POST`s `{ session_id, message }` to `/api/widget/{botId}/chat` and renders the
    `answer` (+ optional sources).
  - handles refusals, loading, and rate-limit/error states gracefully.
- **Origin contract:** the host page's origin must be in the bot's `embed_origins` (F07's
  `VerifyWidgetOrigin`); document how the customer registers their domain.
- **Dashboard:** show the copy-paste snippet on the bot detail page; let the customer manage
  `embed_origins`.

## Data changes

- None.

## Acceptance criteria

- Pasting the snippet on an allow-listed domain renders a working chat that answers via the
  bot and shows refusals for off-topic questions.
- `session_id` persists across page loads so a conversation continues.
- The widget doesn't leak host-page styles in/out (Shadow DOM) and degrades gracefully on
  error.

## Out of scope (later)

- Theming/branding, file attachments, streaming responses, multi-language UI. Note as
  future polish.

## Tests

- A Pest browser/smoke test on a stub host page asserting the widget mounts, sends a
  message, and renders a response (mock the endpoint or use a seeded bot).
