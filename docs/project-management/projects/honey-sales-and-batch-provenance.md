---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Honey Sales and Batch Provenance

## Goal
Turn harvests into traceable batches (lots) and record actual sales
against them, with an optional public provenance page reached by a QR on
the jar label. Builds on the existing backlog placeholder [[0046-real-
sales-ledger]]. Pocket Hive and Honey Chain offer provenance QRs;
ApiManager, Bee Squared and BeeKeepPal track sales and finances.

Proposal G from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - The ADR [[0046-real-sales-ledger]] already requires (revisiting ADR-0034)
  - A batch entity built from `HarvestYield` rows
  - A public, opt-in provenance page + QR per batch
- Out of scope:
  - Point-of-sale, invoicing or payment processing
  - Customer CRM beyond a buyer name/notes field

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0192-sales-and-batch-adr]]
- [[0193-honey-batch-entity]]
- [[0194-public-batch-provenance-page]]

## Open questions
- Public provenance pages expose apiary location — what level of detail (region only?) is acceptable?

## Related decisions
- 
