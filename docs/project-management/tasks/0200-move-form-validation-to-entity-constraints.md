---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[ios-field-app]]"
area: entity
created: 2026-10-04
branch: feature/0200-move-form-validation-to-entity-constraints
release:
depends-on:
blocked-by:
---
# Task: Move form-only validation into entity constraints

## Context
The two-layer convention (`preSave()` throws, `validateForm()` gives the
friendly error) leaves any non-form caller — an API, a migration, a
script — with a 500 instead of a validation error.
[[0107-mobile-client-api-and-native-ios-app]] §2 replaces the form layer
with entity `Constraint` plugins, so forms and the API share one rule.
Useful to core whether or not the app ships.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] Inventory every `validateForm()` across core and submodules that
      enforces a data invariant (vs. pure UI concerns); list them here
- [ ] Each invariant becomes a field or entity `Constraint` plugin; at
      minimum `HiveComponent` (same apiary; quantity ≤ available, own row
      excluded on edit) and `CalendarActionItemRequirement` /
      `CalendarActionProductYield` (same apiary)
- [ ] Forms rely on `ContentEntityForm`'s constraint validation instead of
      repeating the rule; user-facing messages unchanged
- [ ] `preSave()` throws kept as the backstop
- [ ] Kernel tests: `$entity->validate()` reports each violation
- [ ] AGENTS.md's "two-layer convention" wording updated (HiveComponent
      section and wherever else it's described)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HiveComponent.php`,
  `src/Form/HiveComponentForm.php`, `src/Entity/CalendarAction*`,
  new `src/Plugin/Validation/Constraint/`
- No schema change, no update hook.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
