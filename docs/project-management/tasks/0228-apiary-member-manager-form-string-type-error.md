---
type: task
tags: [hivelog/task]
status: dropped
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: routing
created: 2026-10-22
branch: feature/0223-apiary-membership-entity-and-ui
release:
depends-on: ["[[0223-apiary-membership-entity-and-ui]]"]
blocked-by:
---
# Task: Fix ApiaryMemberManagerForm string type error and empty apiary field

## Context
Dropped, not completed as its own piece of work: it was an ad-hoc bug note written
during [[0223-apiary-membership-entity-and-ui]], and every problem it describes was
fixed, and the fixes since reworked, inside 0223 itself (commits 07ed65b, c8a9da7 and
34b722b on `feature/0223-apiary-membership-entity-and-ui`). It was first filed as 0225,
a number [[0225-hivelog-entitlement-interface]] already held; it was renumbered to 0228
before ever being committed.

The two problems it recorded, both gone:
- `ApiaryMemberManagerForm::render()` took a strict `int`, but route parameters are
  strings. The method now accepts `int|string`.
- The add-member form's hidden apiary widget submitted an empty value over the
  route-provided apiary. `ApiaryMembershipForm::buildEntity()` now re-applies the route's
  apiary, and the Members section was rebuilt on the shared entity-table.

The "Fix" snippet this note used to carry (setting the apiary only in `form()`) did not
fix the empty-apiary case on its own, so it is not reproduced here.

## Acceptance criteria
- [ ] Nothing outstanding: superseded by 0223.
