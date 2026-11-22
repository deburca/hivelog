---
type: task
tags: [hivelog/task, tech-debt]
status: done
priority: medium
project:
area: code-quality
created: 2026-11-17
branch:
release: "4.4.1"
depends-on:
blocked-by:
---
# Task: Fix t() calls in classes → $this->t() (task 0291)

## Context
`phpcs` reports ~50 instances of procedural `t()` inside PHP classes across production code. In Drupal, classes should use `$this->t()` via `StringTranslationTrait`. **However**, the scope is much larger than expected — many are in entity `baseFieldDefinitions()` where `t()` is standard Drupal practice for field labels/descriptions that exist before any `$this` context (field definitions are built statically).

## Recommended approach: break into focused sub-tasks

### Phase A: Controllers & Services (straightforward)
Add `StringTranslationTrait`, replace `t(` → `$this->t(`.

| File | Instances |
|------|-----------|
| `src/Controller/HiveController.php` | 1 |
| `src/Breadcrumb/HivelogBreadcrumbBuilder.php` | 2 |
| `src/HivelogAlertCollector.php` | 1 |
| `modules/collective/src/Controller/ApiClientController.php` | 1 |
| `modules/collective/src/Controller/ApiClientApiController.php` | 1 |
| `modules/nanoprobe/src/Controller/SensorDeviceController.php` | ~3 |
| `modules/nanoprobe/src/Controller/SensorIngestController.php` | 1 |
| `modules/nexus/src/Controller/AiProviderConfigController.php` | ~2 |
| `modules/nexus/src/ProviderCaller/AiModuleProviderCaller.php` | 1 |

**Sub-task**: Create a smaller "fix t() in controllers/services" task (~15 instances, easy wins)

### Phase B: Entity classes — consider accepting as-is
Entity field definitions use `t()` to label/describe fields. This is **the documented Drupal pattern**. Many warnings are for core entity base fields that exist outside any `$this` context.

**Recommendation**: Accept these as-is. Don't split into individual PRs just to satisfy a linter warning that conflicts with Drupal conventions.

### Phase C: Forms — verify they already use `$this->t()`
Forms extending `FormBase` or `ContentEntityForm` get `StringTranslationTrait` automatically. Warnings in `ApiaryForm.php` and `SensorDeviceConfigDownloadForm.php` need inspection — may be false positives or the trait is missing on one.

### Phase D (highly recommended): Suppress line-length noise
The 190+ line-length warnings are the real source of CI noise. Consider adding a `.phpcs.xml` exclusion for lines >85 chars, which would dramatically reduce warning count without any code changes.

## Summary
This task is **too large to tackle as one PR**. Recommend:
1. Create a smaller "fix t() in controllers/services" sub-task
2. Accept `t()` in entity field definitions as standard Drupal practice
3. Suppress line-length warnings >85 chars via config instead of fixing them all
