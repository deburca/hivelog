# HiveLog conventions for a coding agent (digest)

A short digest of the rules a change most often breaks. `AGENTS.md` is the authority; when this and
`AGENTS.md` disagree, `AGENTS.md` wins. Read the section of `AGENTS.md` named in brackets before
touching that area.

## Where things are

- The repo root **is** the module root: `src/`, `tests/`, `modules/<submodule>/`. Submodules (`nanoprobe`,
  `collective`, `nexus`, `assimilate`, `hivelog_api`) have their own `src/` and `tests/`.
- Services are in `hivelog.services.yml` (core) and `<module>.services.yml` (a submodule). [Services]
- Entity fields live in code, in `baseFieldDefinitions()`. [Content entities]

## Code rules

- Drupal 11, PHP 8.3 minimum. Code must run on 8.3, 8.4 and 8.5: no implicit nullable parameter types
  (`Foo $x = NULL` is deprecated in 8.4; write `?Foo $x = NULL`), no features newer than 8.3.
- Coding standard is `Drupal` + `DrupalPractice` (`phpcs.xml.dist`): doc comments on every class,
  method and property that PHPCS names; 2-space indent; `array()` is wrong, `[]` is right.
- **Inject services**; do not call `\Drupal::database()`, `\Drupal::entityTypeManager()` or
  `\Drupal::currentUser()` inside a class that can take them in its constructor.
- Use the entity query / database API with placeholders. Never build SQL by string interpolation.
- Output: render arrays and Twig auto-escape. Never `#markup` a variable, `Markup::create()` or `|raw`
  user-controlled text.
- `->accessCheck(FALSE)` needs a one-line reason in a comment beside it.
- **A data-integrity rule is an entity constraint**, attached to the field in `baseFieldDefinitions()`;
  `preSave()` only backstops it. A new child entity type must carry `HivelogParentAccess` on its parent
  reference. [Validation: constraints first]
- A change to `baseFieldDefinitions()` needs an update hook in `hivelog.install`: take the next number after
  the highest `hivelog_update_N`; copy an existing hook's pattern. Do not rely on `entup`. [Entity schema changes]
- Cache metadata: a controller or builder that depends on user, entity or config must bubble its
  cacheability (`ControllerCacheMetadataTest` checks this).
- Do not edit `patches/`, and do not change patch paths in `composer.json`. [Patches]

## Tests

- Every test class carries `#[Group('hivelog')]`. Kernel tests extend `KernelTestBase` and list every module
  they need in `$modules` (missing ones give "Unavailable module" or "no such table" errors); install entity
  schemas and config explicitly with `installEntitySchema()` / `installConfig()`.
- Pure logic with no Drupal bootstrap: a Unit test (fast). Entities, services, access, cache metadata,
  constraints: a Kernel test (about 25 s per file).
- A new public method or branch needs a test that would fail without it. For a bug fix the test must fail
  before the fix and pass after.
- Test the access rules, not only the happy path: another beekeeper's apiary must be refused.
- Do not run Functional tests (browser) or the whole suite; CI does that. [Tests]

## Never

- Add `@phpstan-ignore`, `phpcs:ignore` or edit `phpstan-baseline.neon` to make a gate pass. The baseline is
  a ratchet: fix a new finding, do not baseline it. If a finding is a false positive, say so to the user.
- Commit, push, or change `.github/`. The user does that.
- Weaken or delete an existing test to get green.
