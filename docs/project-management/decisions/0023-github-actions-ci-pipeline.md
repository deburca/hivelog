---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-25
supersedes:
---
# ADR-0023: GitHub Actions CI pipeline

## Status
accepted

## Context
The module's release process (see [[0010-semantic-versioning-and-releases]])
requires the `--group hivelog` PHPUnit suite to be green before tagging, and
ADR-0007 ([[0007-coding-standards-and-static-analysis]]) calls for `phpcs`/
`phpstan` to run in CI alongside tests. Both requirements are currently checked
manually. There is no automated gate on push or pull request, so regressions can
land undetected between manual runs.

**Structural constraint — bare module repository.** This repo is a standalone
Drupal module, not a full Drupal project checkout. PHPUnit kernel tests require
a real Drupal installation (database, container bootstrap). The module also ships
two local geofield patches (`patches/`) whose paths in `composer.json` are
hard-coded to the install-time location (`web/modules/contrib/hivelog/patches/…`)
for `cweagans/composer-patches` — those paths do not exist during CI unless the
surrounding project tree is reconstructed.

**Recommended approach — `drupal/drupal` Docker image with `path` repository.**
The canonical strategy used by Drupal contrib modules is to build the surrounding
Drupal project inside the CI job itself:

1. Start from the official `drupal/drupal:11` Docker image (or equivalent
   `drupal/recommended-project` scaffold), which pre-installs Drupal core,
   PHPUnit, and a MySQL service.
2. Copy the module source into `web/modules/hivelog/` inside the container.
3. Register the module directory as a Composer `path` repository and
   `composer require hivelog/hivelog:@dev` — this resolves the module's
   `require` entries (geofield, leaflet) and applies patches with
   correct paths relative to the project root.
4. Run `drush si --db-url=...` to install Drupal with the module enabled.
5. Execute PHPUnit with `SIMPLETEST_DB` and `SIMPLETEST_BASE_URL` set,
   targeting `web/modules/hivelog/tests/` with `--group hivelog`.

Functional tests (`tests/src/Functional/`) require a Chrome/ChromeDriver
process. They should be included in the matrix but are acceptable to mark as
allowed-to-fail (`continue-on-error: true`) if the driver setup proves unstable,
provided kernel + unit tests remain hard gates.

**Trigger strategy.** Run on:
- Every push to `main` (or any branch, to catch PRs early).
- Every published release tag (same test matrix, now with the tagged commit).

This satisfies both the "push" and "release" scenarios described in the
motivation and keeps the feedback loop as short as possible.

**Static analysis.** The same workflow should run `phpcs` (Drupal coding
standards via `drupal/coder`) and `phpstan` (with `mglaman/phpstan-drupal`) as
separate jobs or steps, consistent with [[0007-coding-standards-and-static-analysis]].
These are faster than PHPUnit and can be run without a database, so they can
run in a lightweight job in parallel with the test job.

## Decision
Add a `.github/workflows/ci.yml` GitHub Actions workflow to the repository.
The workflow:

1. **Triggers** on push to any branch and on `release` events (published).
2. **`lint` job** — runs `phpcs` (Drupal + DrupalPractice standards) and
   `phpstan` (mglaman/phpstan-drupal, level agreed in [[0007-coding-standards-and-static-analysis]])
   against the module source without needing a database. Runs on PHP 8.3 only
   (style/deprecation analysis is not PHP-version-sensitive; running it once
   avoids tripling lint minutes for no benefit).
3. **`test` job** — runs across a `strategy.matrix` of **PHP 8.3, 8.4, and
   8.5**, verifying the full supported range declared in `composer.json`
   (`>=8.3`) and catching deprecations introduced between minor PHP releases.
   Each matrix cell spins up a MySQL service container, builds the surrounding
   Drupal 11 project via `drupal/recommended-project`, copies the module in as
   a `path` repository dependency, installs Drupal, and runs:
   ```
   phpunit -c web/core web/modules/hivelog/tests/ --group hivelog
   ```
   Kernel + unit tests are hard gates (`continue-on-error: false`). Functional
   tests run in the same job; failures are allowed (`continue-on-error: true`)
   if ChromeDriver stability is an issue, but the intent is to make all tests
   hard gates once the driver setup is proven stable.
4. **Patch paths** — a CI-specific `composer.json` overlay (or `COMPOSER_PATCHES`
   environment variable / inline patches array) must remap the geofield patch
   paths from the install-time location to the module source checkout path.
   See implementation note in [[0016-implement-github-actions-ci]].
5. **Caching** — Composer dependencies are cached by `composer.lock` hash to
   keep job times manageable.
6. **Status badge** — `README.md` displays the workflow badge once the workflow
   is live.

The workflow file is the sole source of truth for the CI steps; this ADR records
the rationale and constraints. When the implementation diverges from this ADR
(e.g. the functional test gate changes), update the ADR status or supersede it.

## Consequences
- Positive: regressions in entity logic, access control, and breadcrumb building
  are caught on every push; release tagging is reinforced by an automated gate;
  aligns with the manual checklist in [[0010-semantic-versioning-and-releases]].
- Negative / trade-offs:
  - Initial setup effort: the bare-module → full-project bootstrap is ~30–50 lines
    of workflow YAML and requires testing to confirm patch path remapping works.
  - Functional tests depend on ChromeDriver availability in the runner; accepted
    as allowed-to-fail initially.
  - CI minutes cost (GitHub free tier: 2 000 min/month for private repos;
    effectively free for a public repo).
  - The geofield patch path workaround adds fragility — if patch file paths or
    the `cweagans/composer-patches` plugin version change, the CI setup must be
    updated in lockstep with `composer.json`.
- Follow-up tasks: [[0016-implement-github-actions-ci]]
