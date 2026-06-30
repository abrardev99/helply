# F11 — Conversations & Analytics

**Phase:** 3 · **Status:** ⛔ todo · **Depends on:** F07

## Goal

Persist chat history and give the customer a dashboard view of conversations — especially
the **flagged** (refused / couldn't-answer) ones, which reveal content gaps.

## Scope / tasks

- Persistence already happens in `HandleChatMessage` (F07). This feature is the **read
  side**:
  - `ConversationsController` (dashboard, team-scoped, behind `BotPolicy`): list a bot's
    conversations, show a conversation's messages.
  - Inertia pages: conversation list (with filters: flagged, date), conversation transcript
    (user/assistant turns, `sources`, `retrieval_score`).
- **Flagged review:** a filtered view of `messages.flagged = true` — "questions we couldn't
  answer" — so the customer knows what content to add.
- **Light analytics:** counts — total conversations/messages, % flagged, top
  unanswered questions (group flagged user messages). Keep it simple; no heavy BI.

## Data changes

- None required (existing `conversations`/`messages` cover it). The optional `ChatOutcome`
  enum from F10 enriches analytics if added.

## Acceptance criteria

- A customer can browse a bot's conversations and open a transcript with sources + scores.
- A customer can filter to flagged turns and see what the bot couldn't answer.
- All views are team-scoped; no cross-tenant access (policy + test).

## Tests

- Feature tests: list/show scoped to team (cross-team 403), flagged filter returns the right
  rows, transcript renders messages in order.
