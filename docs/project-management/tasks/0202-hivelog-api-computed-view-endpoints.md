---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[ios-field-app]]"
area: integration
created: 2026-10-04
branch: feature/0202-hivelog-api-computed-view-endpoints
release:
depends-on: ["[[0201-hivelog-api-submodule-scaffold]]"]
blocked-by:
---
# Task: Computed-view endpoints for the app

## Context
Values the app shows that no entity stores
([[0107-mobile-client-api-and-native-ios-app]] context §4): alerts,
stat tiles, empty/net weight, item availability. Each endpoint must
call the same service the web page uses, never reimplement it.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] `GET` needs-attention alerts for the current user (same collector as
      the dashboard, including submodule `hook_hivelog_needs_attention_alerts()`
      contributions)
- [ ] `GET` hive stat tiles via `hivelog.stat_tile_builder` (includes
      nanoprobe's net-weight tile when enabled)
- [ ] `GET` latest `HiveInsight` per hive when `nexus` is enabled
- [ ] Each route under `/hivelog/api/...`, OAuth-authenticated, with
      entity access on the subject hive/apiary
- [ ] Cacheable metadata carried through (per-user)
- [ ] Kernel tests for each, including 403 on another user's hive
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Render arrays from the tile/alert builders may need a data-only method
  split out so the endpoint doesn't serialise markup.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
