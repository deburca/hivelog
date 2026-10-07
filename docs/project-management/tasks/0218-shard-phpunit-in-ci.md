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
- [ ] Observed on a real run: the shards finish in roughly equal time, and the whole run is
      substantially faster than 28 to 35 minutes

## Implementation notes
- `.github/scripts/shard-tests.py <module root> <i>/<n>` prints that shard's test files;
  `--check` proves the partition for 1 to 8 shards. It walks the tree for `*/tests/src/Kernel` and
  `/Unit` (skipping `demo`, `docs`, `vendor`, `.git`), as the old `find` did.
- **Balancing is by estimated cost, not by file count or name:** test methods per file, doubled for a
  class marked `#[RunTestsInSeparateProcesses]` (the `hivelog_api` kernel tests took about 2.8 s a
  test against 1.3 s for the rest), and x 0.05 for a unit test (no Drupal). Files are dealt out
  largest first to the lightest shard (a deterministic greedy fit). For four shards the estimate is
  544 / 543 / 543 / 543 units, 31 to 35 files each.
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
- A real run: see below.

## Not verified
- The estimate's accuracy until the shards' real times are seen; the weights may need adjusting.

## Related
- Project:: 
- Decisions:: 
- Commits::
