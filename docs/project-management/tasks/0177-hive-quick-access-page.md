---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[qr-hive-labels]]"
area: routing
created: 2026-10-04
branch: feature/0177-hive-quick-access-page
release:
depends-on:
blocked-by:
---
# Task: Hive quick-access page for scanned labels

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal B. The QR target must be a phone-first page, not the full hive
canonical page.
Part of [[qr-hive-labels]].

## Acceptance criteria
- [ ] Route `/hivelog/hive/{hive}/quick` with route-level entity access (same
      as canonical)
- [ ] Shows hive name, apiary, active queen, last inspection date, and large
      buttons: Add Inspection, Add Observation (if active queen), Log
      calendar action, View full page
- [ ] Usable at ≤480px with no horizontal scroll; buttons use `hivelog:button`
      / `hivelog.buttons.css`
- [ ] Breadcrumb via `TERMINAL_CRUMB_PARAM`; kernel access test
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[qr-hive-labels]]
- Decisions:: 
- Commits:: 
