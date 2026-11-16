---
type: task
tags: [hivelog/task, tech-debt]
status: done
priority: low
project: beekeeper-centred-simplification-and-gaps
area: code-quality
created: 2026-11-17
branch:
depends-on:
blocked-by:
---
# Task: Fix pre-existing phpcs warnings/errors

## Context
A full `composer lint` (phpcs) run produces 0 errors and ~50+ warnings across the codebase. None are from newly written code — these are legacy issues accumulated over time. Fixing them would bring the project to a clean state for future work. The count is dominated by 80-char line-length warnings in existing files; most are just under the limit (81–106 chars).

## Current warning/error summary (per file, from latest full run)

### Errors (fixable with phpcbf)
| File | Line | Issue | Auto-fixable |
|------|------|-------|:---:|
| `modules/hivelog_api/tests/.../HivelogApiInstallTest.php` | 142 | Array closing paren on wrong line | Yes |
| `hivelog.install` | 1429 | Extra blank line after function | Yes |

### Warnings (manual fix) — grouped by type

**80-char line-length** (~45 instances across the codebase):
- `hivelog.install`: lines 851, 1131, 1255
- `src/Entity/HiveReminder.php`: line 57 (pre-existing in this task)
- `src/Form/HiveReminderForm.php`: lines 41, 44 (pre-existing in this task)
- `tests/src/Kernel/HiveReminderTest.php`: all clean ✅
- `modules/collective/collective.install`: line 17
- `modules/nanoprobe/src/Entity/SensorDevice.php`: line 34
- `modules/nexus/src/Entity/HiveInsight.php`: lines 89, 90
- `modules/hivelog_api/hivelog_api.install`: 14 lines (275–313)
- `modules/hivelog_api/src/...`: ~5 lines across multiple files
- Various test files in `tests/src/Kernel/` and `modules/*/tests/`: ~15 instances

**\Drupal calls should be avoided** (~8 instances):
- `modules/collective/src/Controller/ApiClientController.php`: 1
- `modules/nanoprobe/src/Controller/SensorDeviceController.php`: 4 (lines 243, 253, 263, 325)
- `modules/nexus/src/Controller/AiProviderConfigController.php`: 2 (lines 112, 155)
- `modules/hivelog_api/src/ProviderCaller/AiModuleProviderCaller.php`: 1

**t() calls should be avoided in classes** (~6 instances):
- `tests/src/Kernel/HivelogStatTileBuilderTest.php`: lines 122, 124, 148–150 (test file)
- `modules/nanoprobe/src/Entity/SensorReading.php`: lines 76, 78
- `modules/nexus/src/Entity/HiveInsight.php`: lines 89, 90

**Blank line after inline comment** (~20 instances):
- Spread across: `tests/src/Kernel/HivelogDeleteBlockRelationshipsTest.php` (6), `tests/src/Kernel/ApiaryScopedAccessTest.php` (11), `hivelog.install` (3 in old hooks), `tests/src/Kernel/DashboardTest.php` (4), `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php` (2)

**Useless method overriding** (~2 instances):
- `src/Entity/HiveReminder.php`: line 57 (empty preSave — intentional extension point, accept or remove)
- Possibly others across submodules

**Avoid backslash escaping in translatable strings** (~4 instances):
- `modules/collective/collective.module`: 1
- `modules/nanoprobe/src/Form/SensorDeviceConfigDownloadForm.php`: 1
- `modules/nanoprobe/src/Entity/SensorDevice.php`: 1
- `modules/hivelog_api/...`: 0 (none in latest run)

**Unused use / variable** (~3 instances):
- `tests/src/Kernel/ApiaryTest.php`: line 169 unused variable `$original_changed`
- `modules/hivelog_api/tests/.../HivelogApiLeafDeleteTest.php`: line 64 unused `$module`
- Various old test files

**Multiline array trailing comma** (~1 instance):
- `modules/nanoprobe/tests/src/Kernel/SensorHiveMoveTest.php`: line 63

## Acceptance criteria
- [ ] All phpcs errors fixed (0 errors from `composer lint`)
- [ ] All phpcs warnings fixed (0 warnings) — or explicitly excluded via a `.phpcs-exclude.xml` / standard config if justified
- [ ] `composer lint` passes with exit code 0 on CI

## Implementation approach
Recommended: fix incrementally, one file at a time per PR. Priority order:
1. Auto-fixable errors first (`phpcbf`) — these are trivial
2. Backslash escaping in translatable strings (simple string changes)
3. t() → $this->t() in classes (add StringTranslationTrait)
4. \Drupal:: calls → dependency injection (larger refactor, do per-file)
5. Blank-line-after-inline-comment (mechanical, batch across files)
6. 80-char line-length (the bulk — many are just 1–3 chars over; acceptable for now if CI tolerates warnings as non-blocking, or fix selectively)

## Related
- Depends on: Task 0274 (which introduced one new warning — accepted as-is)
- Affects: all submodules, not just core
