---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[queen-observation-enhancements]]"
area: routing
created: 2026-06-16
branch: feature/0002-breadcrumb-queen-canonical
release: v1.1.0
---
# Task: Breadcrumb trail on the queen canonical page

## Context
`hivelog.breadcrumb` (priority 100) builds the Apiary → Hive → Inspection
trail, but the queen canonical route currently falls back to the default
breadcrumb. Because hives outlive queens, the trail should resolve the queen's
linked hive and render Apiary → Hive → Queen. Part of
[[queen-observation-enhancements]].

## Acceptance criteria
- [ ] `applies()` in the breadcrumb builder matches `entity.queen.canonical`.
- [ ] Trail renders Apiary → Hive → Queen, using the queen's `hive` reference.
- [ ] Gracefully handles a queen with no `hive` (inactive queen): stop at the
      last resolvable parent rather than erroring.
- [ ] Unit test extended in
      `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`.

## Implementation notes
- Key file: `src/Breadcrumb/` builder + `hivelog.services.yml` (no new service,
  just logic).
- Watch the `applies()` matching so existing inspection/hive trails don't
  regress — see the note in `AGENTS.md` about keeping `applies()` in sync when
  routes change.
- No schema change → no update hook.

## Related
- Project:: [[queen-observation-enhancements]]
- Decisions:: 
- Commits:: 
