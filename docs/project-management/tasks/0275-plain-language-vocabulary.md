---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: ui
created: 2026-11-14
branch: feature/0275-plain-language-vocabulary
release:
depends-on:
blocked-by:
---
# Task: Name things the way a beekeeper does

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

The interface is labelled with the data model's nouns. A beekeeper meets "Calendar Actions",
"Hive Action Logs", "Apiary Action Logs", "Inventory Items", "Inventory Purchases", "Queen
Observations", "Calendar Action Item Requirement" and an "Insights" hub whose children are API
Clients, AI Provider Configs and Sensor Devices, which are setup, not insight. None of these is a
word used at the hive.

## Acceptance criteria
- [ ] A glossary is agreed first, in this note: proposed Seasonal tasks (calendar actions), Task record (action log), Supplies or Equipment and supplies (inventory items), Purchases, Harvest (yields), Needs and Expected harvest (requirement, yield recipe), Setup (the `insights` hub; the hive's own Insights page keeps its name)
- [ ] Entity labels, `label_collection`, page titles, nav items, breadcrumb crumb labels and buttons follow the glossary; machine names, routes, permissions and the API contract do not change
- [ ] Help text on forms uses the same words
- [ ] Tests asserting old labels are updated; `AGENTS.md` keeps the machine name beside each new label
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: entity `label*` attributes in `src/Entity/`, `src/HivelogAppNavBuilder.php`, `src/Breadcrumb/`, `hivelog.routing.yml` titles
- Update hook needed? No. ADR-0105 renamed the hub once already; note the reason for a second rename there.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
