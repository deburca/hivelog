---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[ai-apiary-insights]]"
area: integration
created: 2026-09-22
completed: 2026-09-22
branch: feature/0109-nexus-anthropic-call-and-response-parsing-fixes
release:
depends-on:
blocked-by:
---
# Task: Fix two live-only bugs blocking every real `nexus_cron()` insight

## Context
Caught by the user while reviewing [[0107-assimilate-mock-sensor-data-module]]'s
live verification: the dashboard's "AI Insights" section showed "0 hives
all clear today" with no action rows either, which reads ambiguously —
it could mean "everything's fine" or "everything's wrong." Investigating
found neither: `nexus_cron()` was failing outright, so no insight had
ever been written for any hive. Two independent bugs, both invisible to
the existing kernel test suite because it mocks `ProviderCallerInterface`
rather than calling a real provider — this is the first time this
project's own pipeline was run against the real Anthropic API.

## Acceptance criteria
- [x] `DirectApiProviderCaller::ANTHROPIC_MODEL` updated from
      `claude-3-5-haiku-latest` (retired by Anthropic — every call was
      failing with a 404 `model: claude-3-5-haiku-latest` error) to a
      current valid Haiku-tier model id.
- [x] `NexusResponseParser::parse()` strips a wrapping ` ```json ... ``` `
      (or plain ` ``` ... ``` `) markdown code fence before attempting
      `json_decode()` — a real Anthropic response wrapped its JSON in one
      despite the system prompt explicitly saying not to; the parser was
      rejecting an otherwise entirely valid response as "not valid JSON."
      A response with no fence passes through unchanged.
- [x] Kernel/unit tests for both: the fence-stripping tests cover a
      labelled fence, a plain fence, and surrounding whitespace.
- [x] Verified live against `cms2`: `drush cron` now completes with no
      error, and the dashboard's "AI Insights" section shows a real,
      specific, context-grounded recommendation for the demo hive
      (`[[0107-assimilate-mock-sensor-data-module]]`'s own demo data) —
      not a mock/stubbed response, an actual Anthropic API round trip.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- Neither bug was reachable by the existing test suite:
  `NexusInsightGeneratorTest`/`AiProviderConfigManagementUiTest` and
  friends all construct `ProviderCallerInterface` test doubles returning
  clean, pre-shaped JSON directly — nothing in this project's kernel
  tests had ever exercised a real HTTP round trip to Anthropic before
  [[0107-assimilate-mock-sensor-data-module]]'s own live verification
  gave `nexus_cron()` real opted-in data to run against for the first
  time. Both are exactly the kind of gap
  [[0094-ai-insights-prelaunch-validation]] (still backlog) exists to
  catch before real beekeepers hit them.
- `NexusResponseParser::stripMarkdownCodeFence()` only strips a fence
  when the trimmed response actually starts with ` ``` ` — a genuinely
  fence-free response is returned unchanged, so this is additive
  tolerance, not a behavior change for the (until now, untested-against-
  reality) common case.
- The model id is still the same fixed `ANTHROPIC_MODEL` constant
  `DirectApiProviderCaller`'s own docblock already documented as a
  deliberate Phase 1 simplification (not yet a configurable
  `AiProviderConfig` field) — this task only corrects its value, it
  doesn't revisit that design decision.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0100-nexus-in-process-ai-synthesis]]
- Tasks:: [[0107-assimilate-mock-sensor-data-module]] (the demo data
  that first exercised a real Anthropic call), [[0103-nexus-cron-and-provider-calling]]
  (`DirectApiProviderCaller`/`NexusResponseParser`'s own origin),
  [[0094-ai-insights-prelaunch-validation]] (the release gate this was
  caught ahead of)
- Commits::
