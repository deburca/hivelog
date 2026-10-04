---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: QR Hive Labels

## Goal
Printable QR labels for hives so a beekeeper standing at a hive can scan
it with a phone and land on a mobile-friendly page for that hive, with
one-tap Add Inspection / Add Observation / log an action. Offered by
Apiary Book, Bee Squared and Pocket Hive; cheap to build on HiveLog's
existing scoped add routes.

Proposal B from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - A per-hive quick-access landing page under `/hivelog/hive/{hive}/…`
  - A printable label sheet per apiary (one QR + hive name per label)
- Out of scope:
  - NFC tags
  - Public (anonymous) access — scanning still requires login and normal entity access
  - A native app or offline scanning

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0177-hive-quick-access-page]]
- [[0178-printable-qr-label-sheet]]

## Open questions
- Server-side QR generation (Composer dependency, e.g. `chillerlan/php-qrcode`) vs. a client-side JS library — decide in task 0177/0178.
- Which label stock sizes to support (e.g. Avery L7160 / 3×7)?

## Related decisions
- 
