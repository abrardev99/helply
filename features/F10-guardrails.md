# F10 — Guardrails (Relevance Gate + Grounding Check)

**Phase:** 3 · **Status:** ⛔ todo · **Depends on:** F08, F09

## Goal

Ensure the bot only answers questions related to the ingested content. Off-topic questions
(e.g. "help me write Python") get a friendly canned refusal — never a hallucinated answer.
This is the headline requirement of Phase 3.

## Two-stage design (see [docs/03](../docs/03-phase-3-chat-guardrails.md))

```
question ─► retrieve (F08) ─► GUARDRAIL #1 (relevance gate, pre-LLM) ─┐
                                                                       │ pass
                                                                       ▼
                                                      generate (F09) ─► GUARDRAIL #2
                                                                       (grounding check, post-LLM)
   fail #1 or fail #2 ─► canned refusal, message.flagged = true, (no/again no further LLM)
```

### Guardrail #1 — Relevance gate (cheap, before generation)

- Compare `RetrievalResult.topScore` against `bot.confidence_threshold` (F04 config). When
  reranking ran (F08), `topScore` is the **top rerank score** (a stronger relevance signal);
  otherwise it's the top vector similarity.
- If below threshold or no usable chunks → **return canned refusal immediately, skip the
  chat model** (saves cost + blocks off-topic). This is the primary guard. (Note: the rerank
  in F08 still runs before this gate, but it is far cheaper than generation.)

### Guardrail #2 — Grounding/on-topic check (after generation)

- Validate the generated answer is supported by the retrieved context. Options:
  - cheap heuristics first (model emitted an "I don't know"/refusal marker; no citations);
  - then a small **structured-output Agent** (`php artisan make:agent GroundingChecker
    --structured`) that returns a typed verdict — far more reliable than parsing free text:
    ```php
    class GroundingChecker implements Agent, HasStructuredOutput
    {
        use Promptable;

        public function schema(JsonSchema $schema): array
        {
            return [
                'grounded' => $schema->boolean()->required(),
                'reason'   => $schema->string(),
            ];
        }
        // instructions(): "Decide if the ANSWER is fully supported by the CONTEXT..."
    }
    // $verdict = (new GroundingChecker)->prompt($contextAndAnswer); $verdict['grounded']
    ```
  - Use a cheap model here (`#[UseCheapestModel]` or a small `model:`), same tenant key.
- If it fails → replace with the canned refusal.

## Scope / tasks

- **`Services\Chat\Guardrail`** —
  - `passesRelevanceGate(RetrievalResult, Bot): bool` — pure score check, no LLM.
  - `answerIsGrounded(string $answer, RetrievalResult): bool` — heuristics, then delegate to
    the `App\Ai\Agents\GroundingChecker` structured-output agent.
  - `refusalMessage(Bot): string` — friendly canned copy (translatable via `__()`), e.g.
    *"I can only help with questions about {bot name} — I couldn't find that in our content."*
- **`App\Ai\Agents\GroundingChecker`** — the structured-output agent above (F10).
- Wire both into `HandleChatMessage` (F07). On either failure, store the assistant message
  with `flagged = true` and the (low/none) `retrieval_score`.
- Make the threshold and refusal copy configurable per bot (F04 columns / `system_prompt`).
- Optional: a `ChatOutcome` enum (`Answered`, `RefusedLowRelevance`, `RefusedUngrounded`)
  stored or logged for analytics (feeds F11).

## Acceptance criteria

- Off-topic question (code help, unrelated trivia) → canned refusal, `flagged = true`, and
  **no chat-agent call** when caught by guardrail #1 (assert `SupportAgent` is never
  prompted).
- A question that retrieves marginally-similar-but-unhelpful context, where the model would
  drift, is caught by guardrail #2 and refused.
- On-topic question passes both gates and returns the real answer.
- Refusal copy is translatable and references the bot.

## Tests

Use the AI SDK fakes (`Embeddings::fake()`, `Reranking::fake([...])`, `SupportAgent::fake()`,
`GroundingChecker::fake([...])`):

- Unit: relevance gate threshold logic; grounding heuristic.
- Feature: end-to-end refusal path — fake `Reranking` to return a low top score, assert
  `SupportAgent::assertNeverPrompted()` (no chat call) and `flagged = true`.
- Feature: end-to-end pass path — high rerank score, `GroundingChecker` fakes `grounded:true`,
  assert the real answer is returned.
- Feature: grounding-failure path — `GroundingChecker` fakes `grounded:false`, assert the
  answer is replaced by the refusal and `flagged = true`.
