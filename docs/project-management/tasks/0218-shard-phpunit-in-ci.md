---
type: task
tags: [hivelog/task]
status: review
priority: medium
project:
area: tests
created: 2026-10-07
branch: ci/shard-phpunit
release:
depends-on: "[[0217-faster-ci-workflow]]"
blocked-by:
---
# Task: shard PHPUnit across parallel CI jobs

## Context
After [[0217-faster-ci-workflow]] CI runs only where it gates something, but a code push still waits
for one PHPUnit step of 26 to 33 minutes per PHP version (1,175 test methods in 131 files, run in a
single process). The runs are independent, so the step can be split.

## Acceptance criteria
- [x] The Kernel and Unit tests are split into 4 shards that run as parallel matrix jobs
- [x] Every test file runs in exactly one shard, including a new submodule's, without editing the
      workflow; a test file PHPUnit would never run is an error
- [x] That property is checked in CI (lint job), not trusted
- [x] phpstan and the advisory functional tests no longer run once per shard
- [x] `AGENTS.md` describes it and how to reproduce one shard
- [x] Observed on a real run: the whole run is substantially faster than 28 to 35 minutes (9.3)
- [ ] Observed on `main` (the full PHP 8.3 / 8.4 / 8.5 matrix, 12 jobs)

## Implementation notes
- `.github/scripts/shard-tests.py <module root> <i>/<n>` prints that shard's test files;
  `--check` proves the partition for 1 to 8 shards. It walks the tree for `*/tests/src/Kernel` and
  `/Unit` (skipping `demo`, `docs`, `vendor`, `.git`), as the old `find` did.
- **Balancing is by measured time, with an estimate as the fallback.** The first version used an
  estimate (test methods per file, doubled for `#[RunTestsInSeparateProcesses]`, x 0.05 for a unit
  test) and left shard 1 at 11.1 minutes of PHPUnit against 6.1 to 7.9 for the others, because the
  estimate was wrong about which classes are slow (`DashboardTest` and `ListBuilderAccessFilterTest`
  are heavy for their method count). Each shard now uploads its `junit.xml` (kept 14 days);
  `update-test-times.py` turns them into `.github/scripts/test-times.json` (seconds per file, 131
  files, 19.9 minutes of tests), and `shard-tests.py` deals the files out largest first to the
  lightest shard, shard 1 starting with a 100 s head start for phpstan and the functional tests. A
  file not in the JSON (a new test) uses the estimate, so the split is always complete; only the
  balance drifts. Refresh the times after adding large tests.
- Shards are matrix entries `'1/4'` ... `'4/4'`, so the count lives in one place per entry. phpstan
  runs in shard 1 of PHP 8.3 (it was once per run already); the functional step in shard 1 of each
  PHP version (it was once per PHP version).
- **Cost:** each shard job rebuilds the Drupal scaffold and installs the site (a few minutes), so
  four shards use more runner minutes in total (about 12 jobs on `main` instead of 3) to cut the
  wait. Free for a public repository; revisit if the repository goes private.
- Job names changed to `Test (PHP 8.3, shard 1/4)`. A branch-protection rule that requires the old
  names would need updating (none is known).

## Verification
- `shard-tests.py . --check`: 131 files, none lost or repeated for 1 to 8 shards.
- PHPUnit accepts the file list: two unit files from different shards ran, 19 tests passed.
- **Runs on the branch (PHP 8.3, 4 shards):** estimated weights, 14 minutes in total (shard PHPUnit
  steps 11.1 / 6.2 / 6.1 / 7.9 min); measured weights, **9.3 minutes in total** (PHPUnit steps 5.5 / 7.6 /
  5.3 / 7.1 min, jobs 6.4 to 8.8 min), against 28 to 35 before sharding. The split is not
  perfectly even: junit counts test time, not each class's own setup, and the two slower shards
  hold more of the separate-process classes.

## Not verified
- The full three-version matrix (12 jobs) until it runs on `main`.
- Whether a second refresh of the times, now from a run with the measured split, evens the shards
  further (likely a minute or two; not worth chasing yet).

## Related
- Project:: 
- Decisions:: 
- Commits::
