---
type: task
tags: [hivelog/task]
status: done
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
- [x] `nexus.info.yml`: `hivelog:collective`. Verify `drush en nexus`
      still resolves the dependency on `cms2`.
- [x] Versions: decide between (a) submodule info files track the core
      version and the release process bumps all five, or (b) drop
      `version:` from all info files and let packaging supply it.
      Record the decision in the release process docs
      ([[0010-semantic-versioning-and-releases]]).
- [x] `drupal/key`: decide between keeping it as a hard requirement
      (simplest, and documented as "required by nexus") or moving it to
      `suggest` with a clear install note for nexus. Document the
      choice in README.md.
- [x] No functional change otherwise; kernel + unit suite green.

## Implementation notes

**1. Dependency namespace fixed.** `nexus.info.yml`'s `dependencies` now
reads `hivelog:collective`, matching the `hivelog:<module>` form every
other intra-project dependency already uses (`hivelog:hivelog`,
`hivelog:nanoprobe` in `assimilate.info.yml`). Verified live on `cms2`:
`drush en nexus -y` on a site with none of nexus/collective/key
installed correctly resolved and installed all three
(`nexus, collective, key`) in one pass, then `pml` confirmed all three
now show as Enabled.

**2. Versions: chose (a), submodules track core's version.** Recorded
as a 2026-09-25 amendment to
[[0010-semantic-versioning-and-releases]]. None of the four submodules
has an independent release cadence — they ship in this same repository
at the exact commit/tag core does — so a submodule-specific version
number would be fiction; the real problem was that all four were
frozen at `1.0.0` since creation and never bumped, which is actively
misleading in Drupal's own admin "Extend"/updates pages (a module
untouched in over a year, next to core's real `1.8.x`). Bumped all
four (`assimilate`, `collective`, `nanoprobe`, `nexus`) to `1.8.9`,
matching core's current version, and added a line to
`templates/release.md`'s release checklist so every future release
bumps all five `.info.yml` files together. Verified live: after
enabling, `drush pml` shows Nexus/Collective both at `1.8.9`.

**3. `drupal/key`: chose to move to `suggest`.** Kept as a hard
`require` would directly contradict
[[0098-nanoprobe-collective-locutus-submodule-split]]'s own stated
principle that core never depends on any submodule — `drupal/key` is
used exclusively by the optional `nexus` submodule (resolving AI
provider credentials at call time; see `AiProviderConfig`'s own
docblock), yet a hard require forced every HiveLog install, including
ones that never touch `nexus`, to pull it in. Moved to `composer.json`'s
`suggest` block with an inline note, and documented the install step
(`composer require drupal/key` before `drush en nexus -y`) in
README.md's Requirements section. Composer's `require`/`suggest` has no
bearing on Drupal's own module-dependency resolution (that's driven
entirely by each `.info.yml`'s `dependencies:` key, which `nexus`
already declares `key:key` in) — it only affects whether the `key`
module's *code* is present on disk before someone tries to enable
`nexus`, which is exactly the gap `suggest` plus the new README note
closes. Verified live on `cms2`: the `key` module's code happened to
already be present (installed for other reasons, disabled), so
`drush en nexus` still resolved cleanly — a fresh site without
`drupal/key` on disk at all would need the documented
`composer require drupal/key` step first, which is the intended,
documented behaviour change.

**Verification.** No PHP code changed — phpcs and phpstan both clean
(unaffected either way), full kernel/unit/functional suite green
against `cms2` (see this task's commit for the exact count). Live
end-to-end check on `cms2`'s `kragebaekgaard.ddev.site`: enabled
`nexus` fresh (pulling in `collective`/`key` via the fixed
dependency), confirmed all three show the correct `1.8.9` version and
the site renders normally, then uninstalled all three again to restore
the site's prior state.

- Key files: `modules/nexus/nexus.info.yml`,
  `modules/assimilate/assimilate.info.yml`,
  `modules/collective/collective.info.yml`,
  `modules/nanoprobe/nanoprobe.info.yml`, `composer.json`, `README.md`,
  `docs/project-management/decisions/0010-semantic-versioning-and-releases.md`,
  `docs/project-management/templates/release.md`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0010-semantic-versioning-and-releases]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Commits::
