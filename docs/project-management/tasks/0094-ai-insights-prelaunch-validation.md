---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
branch: feature/0094-ai-insights-prelaunch-validation
release:
depends-on: ["[[0089-insight-agent-and-hive-insight-entities]]", "[[0090-insight-agent-api-endpoints]]"]
blocked-by:
---
# Task: Pre-launch validation — required gate before shipping to real data

## Context
[[0088-ai-insights-implementation]] §6 carries forward
[[0085-sensor-less-insight-prototype]]'s two flagged gaps as explicit,
non-optional checks. That ADR's own Consequences: "Phase 1 is not ready
to ship until §6's two checks actually pass — this ADR does not by
itself constitute permission to start building without doing them."
This task **is** running those checks — not another design task, and
not gated on the UI tasks ([[0092-hive-apiary-ai-insight-panel]],
[[0093-dashboard-ai-insights-section]]) existing, since it only needs
the entities and the context-read endpoint to produce real-shaped input.
It gates turning `ai_insights_enabled` on for any real beekeeper, not
the code tasks' own development.

## Acceptance criteria
- [ ] **Model-tier validation**: re-run
      [[0085-sensor-less-insight-prototype]]'s three scenarios (or
      close equivalents, pulled through
      [[0090-insight-agent-api-endpoints]]'s real context-read endpoint
      rather than hand-built JSON) against the actual production model
      tier — [[0087-ai-insights-hosting-and-privacy-model]] recommends
      starting at Haiku-class. Confirm the cheaper tier still produces
      correctly-cited, appropriately-hedged verdicts matching the
      quality the prototype demonstrated with a larger model. If it
      doesn't, this task's outcome is "move up a tier," not a silent
      downgrade in output quality.
- [ ] **Ambiguous/sparse-data behaviour**: construct and test at least
      one hive with a near-empty history (one inspection ever) and one
      with genuinely conflicting signals (e.g. strong population but
      also queen cells *and* a just-completed harvest that could
      explain a weight drop two different ways). Confirm the agent
      hedges appropriately per its own system prompt instruction,
      rather than overclaiming a confident verdict the data doesn't
      support — none of [[0085-sensor-less-insight-prototype]]'s three
      original scenarios were built to test this path.
- [ ] Write up both findings plainly, the same way
      [[0085-sensor-less-insight-prototype]] did — including if either
      check fails, and what that changes (a different model tier, a
      stronger hedging instruction in the system prompt, a "low
      confidence — insufficient history" verdict state that wasn't in
      the original three-verdict design).
- [ ] Only once both pass: `Apiary.ai_insights_enabled` is safe to
      recommend turning on for real use. Record that explicitly in this
      task's own outcome — it's the actual release gate for the whole
      project, not implied by the other tasks merging.

## Implementation notes
- No hivelog code changes from this task itself, same as
  [[0085-sensor-less-insight-prototype]] — this is validation, not a
  feature. If a finding here implies a schema/prompt change (e.g. a
  needed fourth verdict state for low-confidence cases), that becomes
  its own follow-up task, not scope creep into this one.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0085-sensor-less-insight-prototype]],
  [[0087-ai-insights-hosting-and-privacy-model]]
- Commits::
