---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
completed: 2026-09-20
branch: feature/0085-sensor-less-insight-prototype
release:
depends-on:
blocked-by:
---
# Task: Prototype — does inspection/calendar-only reasoning work without sensors?

## Context
[[0083-ai-assisted-apiary-insights]] argues the feature's value doesn't
strictly require [[sensor-data-collection]] to ship first — a
sensor-less hive with just `HiveInspection`/`CalendarAction`/
`HiveActionLog` history is already a plausible, if thinner, input. That's
an untested hypothesis, not a decision — this task is a throwaway
prototype to find out before committing either way, not a request to
build any real hivelog feature.

## Acceptance criteria
- [x] Assemble a realistic-but-synthetic "hive context" — a plausible
      set of `HiveInspection` records, `CalendarAction`/`HiveActionLog`
      status, and queen history for one fictional hive over one season
      (**not** a real beekeeper's live data, to sidestep
      [[0084-ai-insights-hosting-and-privacy-decision]] entirely for
      this exploratory step). **Done — three scenarios built directly
      from the real `HiveInspection`/`CalendarAction`/`Queen` field
      schema (population, brood_pattern, honey_stores, queen_cells,
      varroa_count, weight, supers, queen_year), using only the fields
      [[0087-ai-insights-hosting-and-privacy-model]]'s default payload
      includes — no free-text notes, no location, no real names.**
- [x] Feed that context to a model (any convenient one for this
      throwaway spike — the real hosting/model choice is
      [[0084-ai-insights-hosting-and-privacy-decision]]'s job, not
      this task's) and see whether it produces a recommendation in the
      shape [[0083-ai-assisted-apiary-insights]] describes (act now /
      inspect soon / all clear, with cited signals). **Done — no API
      credentials are configured in this environment, so the reasoning
      was performed directly by the assistant model in this session
      (the same class of hosted model [[0087-ai-insights-hosting-and-privacy-model]]
      settled on), given a production-shaped system prompt. Full
      prompt, all three contexts, and full outputs are in the session
      scratchpad; the distilled findings are below.**
- [x] Try at least one scenario per verdict (an obviously-overdue
      calendar action → act now; a pattern that should read as swarm
      risk from inspection notes alone → inspect soon; a
      clean/uneventful season → all clear) to see whether inspection/
      calendar data alone carries enough signal for all three, or only
      some. **Done, adapted to test the user's own three original
      examples directly: (A) act_now = "add a super" (honey stores
      trending abundant + strong population + only 1 super + climbing
      weight, no calendar action involved at all); (B) inspect_soon =
      swarm risk (`queen_cells: true` this week, false 2 weeks prior,
      corroborated by the calendar's own "Weekly Swarm Checks" item
      being due this exact week); (C) all_clear = a stable 3-inspection
      season with everything reported done, including a
      would-look-concerning weight dip correctly explained away by a
      recorded harvest rather than flagged as an anomaly.**
- [x] Write up the finding plainly: does sensor-less reasoning produce
      genuinely useful output, or is it too thin to be worth shipping
      before sensor data exists? This determines whether
      [[ai-apiary-insights]] should be sequenced independently of
      [[sensor-data-collection]] or effectively wait for it.

      **Finding: yes, clearly, for all three verdict types.**
      `HiveInspection`'s structured fields alone — no sensors, no
      free-text notes — already carry enough signal for well-grounded,
      correctly-cited, appropriately-hedged recommendations.
      `queen_cells` in particular is a near-perfect direct swarm signal
      that needs zero sensor data at all. The multi-signal
      cross-referencing seen in scenario C (explaining a weight dip via
      a recorded harvest) is real value a single fixed-threshold rule
      ([[0074-sensor-data-ingestion-architecture]] §7) can't produce
      without a hand-coded special case per combination — this is
      genuine evidence the feature is worth more than the existing
      simpler mechanism, not just a restatement of it. In both scenario
      A and B the model also appropriately hedged rather than
      overclaiming (declining to specify how many supers; naming
      *two* plausible causes for queen cells rather than picking one),
      which is exactly what [[0083-ai-assisted-apiary-insights]] §4's
      explainability requirement is meant to produce in practice, not
      just in principle.

      **Caveats, honestly, not swept under the rug:**
      - All three scenarios were deliberately built with fairly clear
        signal. None tested ambiguous, sparse, or conflicting data —
        the actual hard case for a real beekeeper's messier history — so
        this prototype validates the best case, not the median case.
      - The reasoning here was done by a large, capable model in this
        session — **not** at whatever cheaper tier
        [[0087-ai-insights-hosting-and-privacy-model]] actually
        recommends starting at (Haiku-class). This spike shows the
        underlying data *can* support good output; it does not show a
        cost-optimised production model *will* produce equally good
        output. That's a real open gap, not closed here.
      - No scenario tested a near-empty history (e.g. a brand-new hive
        with one inspection ever) — a realistic and likely common case
        this prototype didn't check.

      **Conclusion for sequencing**: supports treating sensor-less
      reasoning as in-scope for the *first* real implementation, not
      something to wait on [[sensor-data-collection]] for — but
      [[0086-ai-insights-implementation-adr]] should treat the two
      caveats above (cheaper-model-tier validation, ambiguous/sparse-data
      behaviour) as pre-launch checks to run before shipping, not as
      blockers to starting the design.

## Implementation notes
- Explicitly a spike: no hivelog code, no tests, no entity changes.
  Full prompt/context/output detail lives in the session scratchpad,
  not this repo — this task file carries the distilled, verifiable
  finding, matching how other completed tasks in this project record
  outcomes without dumping raw working data into version control.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0083-ai-assisted-apiary-insights]],
  [[0087-ai-insights-hosting-and-privacy-model]]
- Commits::
