---
type: task
tags: [hivelog/task]
status: review
priority: medium
project:
area: tests
created: 2026-10-07
branch: ci/faster-workflow
release:
depends-on:
blocked-by:
---
# Task: a faster CI workflow: run the tests where they can still stop a release

## Context
Every one of the last twelve CI runs took about 31 minutes, docs-only pushes included. Lint takes
under a minute; the PHPUnit step (kernel and unit) takes 26 to 33 minutes, run three times in
parallel on PHP 8.3, 8.4 and 8.5. Releasing 2.11.2 made three full runs of the same code (the push of
the release commit, the tag push and the release event), and most pushes were docs.

The first idea was lint on push and PHPUnit only on release. Rejected: the release event fires
*after* the release is published (and Packagist has the tag), so a test failure there is too late.
The tests are the gate that catches real regressions; three releases (2.8.1, 2.9.0, 2.10.0) went out
red because lint was checked after the fact. So the tests stay on pushes to `main`, and the saving
comes from not running them where they gate nothing.

## Acceptance criteria
- [x] A push that changes only `docs/`, `demo/`, `*.md` or `.gitattributes` runs nothing
- [x] A tag push does not run CI (branches only)
- [x] A newer push to a branch cancels the run it supersedes (never a release run)
- [x] A push to a branch other than `main` runs lint and PHP 8.3 only; `main` runs 8.3, 8.4 and 8.5
- [x] A published release runs no tests: `release-check` verifies that every `.info.yml` carries the
      tag as its version and that the commit's push run has not failed
- [x] `AGENTS.md` describes the new rules, including "wait for `main` to go green before releasing"
- [ ] Observed on a real branch push and on `main`, and on the next release
- [ ] Sharding PHPUnit across parallel jobs (a separate follow-up; the biggest saving for code pushes)

## Implementation notes
- `.github/workflows/ci.yml`: `on.push.branches: ['**']` (excludes tags) with `paths-ignore`; a
  `concurrency` group per ref whose `cancel-in-progress` is true for pushes only; the matrix is
  `fromJSON(github.ref == 'refs/heads/main' && '["8.3","8.4","8.5"]' || '["8.3"]')`; `lint` and `test`
  have `if: github.event_name != 'release'`; a new `release-check` job runs only for a release.
- `.github/scripts/check-release.sh <tag> <sha> <owner/repo>` is a script, not inline YAML, so it can
  be run by hand. It fails on a version that differs from the tag (the check that would have caught a
  forgotten submodule bump) and on a failed push run for that commit; **a run still in progress, or
  none (a docs-only commit), is a warning, not a failure**, because the release is usually created
  while the push run is still going.
- **Not in `paths-ignore`:** `*.info.yml`, `composer.json`, the workflow and everything under `src/`,
  `modules/` and `tests/`, so a release commit (which bumps the `.info.yml` files) always runs.
- A consequence worth knowing: a docs-only commit has no CI run at all. If a repository rule ever
  requires a status check on `main`, a docs-only push would wait for one that never starts.

## Verification
- The workflow parses (Ruby's YAML: three jobs, the trigger as intended).
- `check-release.sh` run by hand against the real 2.11.2 tag: all six versions pass and the commit's
  CI is found green; with the tag name changed to 2.11.3 it reports the three `.info.yml` files that
  differ and exits non-zero.
- **A real branch push** (`ci/faster-workflow`): jobs were `Release check` (skipped), `Lint (PHP 8.3)` and
  `Test (PHP 8.3)` only; no 8.4 or 8.5.

## Not verified
- The release-check job inside Actions (the `release` event can only be tested by a release).
- The failing-CI branch of the script (needs a red commit).

## Related
- Project:: 
- Decisions:: 
- Commits::
