---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[page-structure-consistency]]"
area: install
created: 2026-09-23
branch: feature/0139-submodule-packaging-hygiene
release:
depends-on:
blocked-by:
---
# Task: Submodule packaging hygiene (dependencies, versions, composer)

## Context
From the 2026-09-23 gap analysis. Three small packaging
inconsistencies:

1. **Dependency namespace.** `nexus.info.yml` declares
   `collective:collective`. Every other intra-project dependency uses the
   `hivelog:<module>` form (`hivelog:hivelog`, `hivelog:nanoprobe` in
   `assimilate.info.yml`). `collective` ships inside the `hivelog`
   project, so `collective:collective` names a project that doesn't
   exist, which matters to Drupal.org packaging and dependency tooling.
2. **Versions.** `hivelog.info.yml` is `1.8.8`, while all four
   submodules' info files are pinned at `1.0.0` and never bumped by
   releases.
3. **Composer requirement.** `drupal/key` is a hard `require` in the
   root `composer.json`, so every HiveLog install pulls it in, although
   only the optional `nexus` submodule uses it.

## Acceptance criteria
- [ ] `nexus.info.yml`: `hivelog:collective`. Verify `drush en nexus`
      still resolves the dependency on `cms2`.
- [ ] Versions: decide between (a) submodule info files track the core
      version and the release process bumps all five, or (b) drop
      `version:` from all info files and let packaging supply it.
      Record the decision in the release process docs
      ([[0010-semantic-versioning-and-releases]]).
- [ ] `drupal/key`: decide between keeping it as a hard requirement
      (simplest, and documented as "required by nexus") or moving it to
      `suggest` with a clear install note for nexus. Document the
      choice in README.md.
- [ ] No functional change otherwise; kernel + unit suite green.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0010-semantic-versioning-and-releases]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Commits::
