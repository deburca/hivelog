---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Data Export and Printable Records

## Goal
Let beekeepers get their records out of HiveLog: CSV export of any
collection list (respecting the active filters and row access), a
printable per-hive record, and an apiary-wide export bundle. Export is
near-universal in the market (Apiary Book PDF/CSV, ApiManager Excel/CSV,
BroodMinder) and HiveLog has none; it is also a prerequisite for printed
treatment registers (see [[treatment-register]]).

Proposal A from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - CSV export from every `HivelogListBuilder` collection, honouring filters, sort and access
  - A print-friendly per-hive record (inspections, queens, treatments, components)
  - An apiary-wide zip of CSVs
- Out of scope:
  - Import (round-trip) of CSV data
  - Server-side PDF rendering library — print CSS + browser 'Save as PDF' first
  - Sensor raw readings export (nanoprobe already has its own download routes)

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0174-collection-csv-export]]
- [[0175-printable-hive-record]]
- [[0176-apiary-export-bundle]]

## Open questions
- Is browser print-to-PDF enough, or is a generated PDF (e.g. dompdf) wanted?

## Related decisions
- 
