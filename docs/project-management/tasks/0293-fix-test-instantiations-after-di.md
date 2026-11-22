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
# Task 0293: Fix controller instantiation in kernel/unit tests after \Drupal:: → DI refactor (task 0291)

## Summary

Task 0291 replaced `\Drupal::service('...')` calls in controllers with constructor injection. This broke multiple test classes that instantiate these controllers directly without providing the new required dependencies:

### Broken Controllers & Tests

| Controller | Required Deps | Affected Test Files |
|---|---|---|
| `ApiClientApiController` | `TimeInterface`, `KeyServiceInterface` | `modules/collective/tests/src/Kernel/ApiClientApiControllerTest.php` (10 tests) |
| `HivelogBreadcrumbBuilder` | `RequestStack`, `TitleResolverInterface`, `EntityTypeManagerInterface` | `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php` + kernel consumers (shard 2: ~1 test) |
| `SensorDeviceController` | `TimeInterface`, `RequestStack`, `SensorTrendChartBuilder`, `DateFormatterInterface` | `modules/nanoprobe/tests/src/Kernel/SensorDeviceControllerTest.php` (5 tests), `SensorDeviceReadingsTest.php` (11 tests) |
| `SensorIngestController` | `TimeInterface` | `modules/nanoprobe/tests/src/Kernel/SensorIngestControllerTest.php` (1 test, line 109), `SensorDeviceConfigDownloadFormTest.php` (2 tests, lines 216/242) |

## What to do

For each affected test file:
1. Add the required use statements for the controller's dependencies
2. Either: (a) add a `createController()` helper that passes `createStub()` mocks, or (b) instantiate with mocks inline at call sites
3. Run `composer lint` on each changed file before committing

### Priority order (by test count):
1. **SensorDeviceReadingsTest.php** — 11 tests to update (most impactful for CI runtime)
2. **SensorDeviceControllerTest.php** — 5 tests
3. **ApiClientApiControllerTest.php** — 10+ tests
4. **SensorIngestControllerTest.php** — 1 test + **SensorDeviceConfigDownloadFormTest.php** — 2 tests
5. **HivelogBreadcrumbBuilderTest.php** — already has stubs from task 0291, but needs checking for the breadcrumb builder itself (which is a service instantiation issue, not direct `new`)

### Key notes:
- For `SensorDeviceController`, the controller uses `$this->time()` in several methods — mock the `TimeInterface` to return predictable timestamps (e.g., `1700000000`).
- For `HivelogBreadcrumbBuilderTest.php`, check if it already has stubs from task 0291 or needs them added.
- Run `composer lint` after each file change.
