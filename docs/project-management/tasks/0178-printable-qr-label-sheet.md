---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[qr-hive-labels]]"
area: theme
created: 2026-10-04
branch: feature/0178-printable-qr-label-sheet
release:
depends-on: ["[[0177-hive-quick-access-page]]"]
blocked-by:
---
# Task: Printable QR label sheet per apiary

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal B.
Part of [[qr-hive-labels]].

## Acceptance criteria
- [ ] A Print hive labels button on the Apiary page; route lists every
      accessible hive as a label (QR + hive name + apiary)
- [ ] QR encodes the absolute URL of [[0177-hive-quick-access-page]]
- [ ] Decide and record QR generation approach (Composer library such as
      `chillerlan/php-qrcode` vs client-side JS from an allowed CDN); if
      a Composer dependency, update `composer.json` and AGENTS.md
- [ ] Print layout for at least one common A4 label stock; option to select a
      subset of hives
- [ ] Kernel test for access and hive listing
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- The QR helper should be reusable by [[honey-sales-and-batch-provenance]]

## Related
- Project:: [[qr-hive-labels]]
- Decisions:: 
- Commits:: 
