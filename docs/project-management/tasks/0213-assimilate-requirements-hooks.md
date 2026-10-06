---
type: task
tags: [hivelog/task]
status: review
priority: medium
project:
area: install
created: 2026-10-06
branch: feature/0213-assimilate-requirements-hooks
release:
depends-on:
blocked-by:
---
# Task: assimilate: move its requirements to the hooks Drupal 11.3+ runs

## Context
Found while fixing the missing status-report entry in `hivelog_api` (release 2.10.1): from
Drupal 11.3 a procedural `hook_requirements()` marked `#[LegacyRequirementsHook]` is skipped,
and Drupal 12 removes the procedural hook altogether. `assimilate` had the same procedural
function, without the marker. It still worked on 11.4 (with a deprecation), but on Drupal 12
**its production guardrail would stop running without any error**: the install-time refusal
to put the demo module on a site with real AI-insight data (the guardrail asked for
by [[0107-assimilate-mock-sensor-data-module]]: "a real, working guardrail... not documentation alone"). Losing that quietly is the
worst way for it to fail. The same kind of gap as 2.10.1's, and the existing tests had the same blind spot: they
called `assimilate_requirements()` directly, which keeps passing after Drupal stops calling it.

## Acceptance criteria
- [x] Install phase: a class in `src/Install/Requirements/` (self-contained, as core loads it
      before the module is installed) that refuses the install on a site with a real
      AI-insight apiary, and ignores assimilate's own demo apiary
- [x] Runtime phase: an object-oriented `#[Hook('runtime_requirements')]` giving the standing
      reminder (a warning) and an error once a real apiary has opted in later
- [x] The procedural function stays for Drupal before 11.3 (marked `#[LegacyRequirementsHook]`),
      with no deprecated `REQUIREMENT_*` constants
- [x] Tests that go through Drupal's own paths (`drupal_check_module()`, the status report's
      list), and fail without the new classes
- [x] `AGENTS.md` updated (the convention, for any later submodule)
- [x] phpcs (CI's coder) and phpstan clean
- [ ] Released (2.11.1)

## Implementation notes
- `Drupal\assimilate\Install\Requirements\AssimilateRequirements` (`InstallRequirementsInterface`,
  static `getRequirements()`): core `require_once`s every file in that directory, so the
  directory holds only that class, and it cannot rely on the module's autoloading or services.
  The apiary count is repeated in it, as it already was inline in the old function, and it first
  checks the `apiary` type exists: with hivelog installed in the same batch it may not yet,
  and then no real apiary can exist.
- `Drupal\assimilate\Hook\AssimilateRequirementsHooks` takes `assimilate.production_guardrail`
  (autowired by service id) and shows its new `realApiaryCount()`; `blockingReason()` uses the
  same method.
- `assimilate.install`: `assimilate_requirements()` is marked and its severities come from
  `_assimilate_requirement_severity()` (the enum where it exists, else the constant by name),
  which also keeps phpstan's deprecation rules quiet.
- **Tests:** `AssimilateRequirementsTest` (6): `drupal_check_module('assimilate')` is TRUE on an
  empty site, FALSE with the reason when a real apiary has insights on, TRUE for a real apiary
  with insights off and for the demo apiary; the status report carries `assimilate_dev_only` as a
  warning, and as an error once a real apiary opts in. **Run against the tree with the two new
  classes removed, 3 of the 6 fail** (the refusal and both status entries): the state a
  Drupal 12 site would be in. The older tests in `ProductionGuardrailTest` still call the
  procedural function (the path for Drupal before 11.3), now comparing severities as numbers.
  All 26 `assimilate` kernel tests pass on Drupal 11.4.8.

## Not verified
- Not run on a real install through the module page or Drush (that would put the demo module,
  which fabricates sensor data, on the dev site); `drupal_check_module()` is what both call.
- Drupal 11.2 (which runs the procedural and the new forms together) and 11.0/11.1 (procedural
  only) were not run; the code takes the enum only where it exists.

## Related
- Project:: 
- Decisions:: 
- Commits:: 
