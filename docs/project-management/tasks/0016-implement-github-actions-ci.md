---
type: task
tags: [hivelog/task]
status: done
released: main
priority: high
project:
area: tests
created: 2026-06-25
branch: feature/0016-github-actions-ci
release:
depends-on: []
blocked-by:
---
# Task: Implement GitHub Actions CI pipeline

## Context
ADR-0023 ([[0023-github-actions-ci-pipeline]]) decided to add automated CI that
runs on every push and on release publication. This task implements the workflow
file and any supporting changes required to make it function correctly.

The key structural challenge: this is a standalone module repo (no surrounding
Drupal checkout), and the local geofield patches have paths in `composer.json`
that only resolve inside a full Drupal project tree. The CI job must build that
tree itself and remap the patch paths.

## Acceptance criteria
- [x] `.github/workflows/ci.yml` exists and runs without errors on push to `main`.
- [x] `lint` job passes `phpcs --standard=Drupal,DrupalPractice` against `src/`
      and `tests/`. phpstan runs but is `continue-on-error: true` pending a clean
      phpstan config with Drupal stubs (see implementation notes below).
- [x] `test` job runs on a `strategy.matrix` of PHP **8.3, 8.4, and 8.5**;
      each cell builds a `drupal/recommended-project` scaffold, installs the
      module (including geofield + leaflet + drush + drupal/core-dev via Composer),
      installs Drupal, and runs `phpunit --group hivelog`.
- [x] Kernel + unit tests are hard gates (`continue-on-error: false`).
- [x] Functional tests run in the same job; `continue-on-error: true`
      until ChromeDriver stability is confirmed.
- [x] Composer dependency cache keyed on `composer.json` hash.
- [x] Workflow badge added to `README.md`.
- [x] `AGENTS.md` updated with CI section documenting trigger, jobs, and
      the patch-path constraint.
- [x] `--group hivelog` suite still passes locally in DDEV (178/178).

## Implementation notes

### Key files to create / edit
- `.github/workflows/ci.yml` — new (the primary deliverable).
- `README.md` — add badge.
- `AGENTS.md` — document CI setup and the patch-path constraint.

### Workflow structure (sketch)
```yaml
name: CI
on:
  push:
  release:
    types: [published]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'   # lint once; style analysis is not PHP-version-sensitive
          tools: composer
      - run: composer require --dev drupal/coder mglaman/phpstan-drupal phpstan/phpstan-deprecation-rules
      - run: vendor/bin/phpcs --standard=Drupal,DrupalPractice src/ tests/ *.php *.module *.install
      - run: vendor/bin/phpstan analyse src/ --level=...

  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.3', '8.4', '8.5']
      fail-fast: false   # report failures on all PHP versions, not just the first
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_DATABASE: drupal
          MYSQL_USER: drupal
          MYSQL_PASSWORD: drupal
          MYSQL_ROOT_PASSWORD: root
        options: --health-cmd="mysqladmin ping" ...
    steps:
      - uses: actions/checkout@v4
        with:
          path: hivelog-module   # check out the module source separately
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          tools: composer
      # Build a Drupal project scaffold
      - run: composer create-project drupal/recommended-project drupal-project --no-install
      # Add the module as a path repository (points to the checked-out source)
      - run: |
          cd drupal-project
          composer config repositories.hivelog '{"type":"path","url":"../hivelog-module","options":{"symlink":false}}'
          composer require hivelog/hivelog:@dev --no-interaction
      # Install Drupal
      - run: |
          cd drupal-project
          vendor/bin/drush si standard --db-url=mysql://drupal:drupal@127.0.0.1/drupal -y
          vendor/bin/drush en hivelog -y
      # Run PHPUnit
      - run: |
          cd drupal-project
          SIMPLETEST_DB=mysql://drupal:drupal@127.0.0.1/drupal \
          SIMPLETEST_BASE_URL=http://localhost \
          vendor/bin/phpunit \
            -c web/core \
            web/modules/hivelog/tests/src/Kernel \
            web/modules/hivelog/tests/src/Unit \
            --group hivelog
      # Functional tests (allowed to fail initially)
      - run: |
          ...
        continue-on-error: true
```

### Patch path problem and solution
The module's `composer.json` `extra.patches` block references:
```
"web/modules/contrib/hivelog/patches/geofield-drupal11-attribute-discovery.patch"
```
This path only resolves when the module is installed at
`web/modules/contrib/hivelog/` inside a Drupal project, which is where
`cweagans/composer-patches` is applied.

When the module is installed via the `path` repository approach above, Composer
copies the module source to `web/modules/hivelog/` — the path diverges. Options:
1. **Override the patches in the root `composer.json`** of the CI scaffold
   project, using the actual path after the `path` repository install resolves.
   Since Composer copies the source to `web/modules/hivelog/`, the correct path
   in the CI project root would be
   `web/modules/hivelog/patches/geofield-drupal11-attribute-discovery.patch`.
   Add this as a `scripts.post-install-cmd` or a CI step that patches
   `drupal-project/composer.json` before `composer install`.
2. **Symlink** the module source (set `"symlink": true` in the path repo
   options) so the module lands at exactly `web/modules/hivelog/` and the
   existing paths resolve unchanged. Test this first — it is simpler if it
   works.

Option 2 (symlink) should be tried first. If symlink is unreliable in the
runner environment, fall back to option 1.

### Update hook needed?
No — this task adds only workflow YAML and documentation; no entity schema
changes.

## Implementation notes

### Patch-path solution
The module's `composer.json` records geofield patch paths at the Packagist
install-time location (`web/modules/contrib/hivelog/patches/…`).
`drupal/recommended-project` installs `drupal-module` packages to
`web/modules/contrib/{name}/` so the original paths resolve correctly
without any override. No path remapping step is needed.

### phpstan status
phpstan runs in the PHP 8.3 test cell (after Drupal core is in vendor/) but is
`continue-on-error: true`. The `mglaman/phpstan-drupal` extension causes
"file included multiple times" conflicts because `drupal/core-dev` also ships
`phpstan-deprecation-rules`. Bare phpstan level 2 without the extension
produces ~119 Drupal-specific false positives (`EntityInterface::get()`,
`new static()`, etc.). A clean phpstan config with proper Drupal stubs is a
follow-up task. phpcs and PHPUnit are the hard gates per ADR-0023.

### Module discovery
The module is located in the test job using:
`find web/modules -maxdepth 3 -name "hivelog.info.yml" | head -1 | xargs dirname`
This handles both `web/modules/hivelog/` (standalone) and
`web/modules/contrib/hivelog/` (recommended-project) layouts.

## Related
- Project::
- Decisions:: [[0023-github-actions-ci-pipeline]], [[0008-testing-strategy]], [[0007-coding-standards-and-static-analysis]]
- PRs:: merged to main via multiple fix commits (see git log)
