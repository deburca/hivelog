---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-18
branch: feature/0225-hivelog-entitlement-interface
release:
depends-on: ["[[0189-apiary-membership-access-adr]]"]
blocked-by:
---

# Task: Hivelog entitlement interface and default service

## Context
Part of [[shared-apiaries-and-team-roles]]. Decision in ADR-0109: gate apiary creation
for SaaS/hosted deployments without baking in a commerce dependency. This task implements
Layer 1 (can this user create apiaries at all). Layer 2 (membership roles) is handled by
[[0108-apiary-membership-entity-and-roles]].

## Acceptance criteria
- [ ] `src/Entitlement/EntitlementInterface.php` — single method:
      `canAddApiary(AccountInterface $account): bool`
  + optional `getAccountQuotas(AccountInterface $account): array` returning
    `['apiaries' => int, 'hives' => int]` (default PHP_INT_MAX)
- [ ] `src/Entitlement/DefaultEntitlement.php` — implements the interface, always returns
      TRUE for apiary creation and PHP_INT_MAX for quotas (friend/family default)
- [ ] Service registration in `hivelog.services.yml`:
      `hivelog.entitlement: '@Drupal\hivelog\Entitlement\DefaultEntitlement'`
  + tagged as a service that can be replaced by commerce implementations
- [ ] `ApiaryForm::form()` calls `$this->entitlement->canAddApiary($account)` in `buildForm()`.
      If FALSE, adds the "add apiary" button to the form with `#access => FALSE` and sets a
      status message explaining how the user can subscribe (site-admin configurable).
- [ ] No change to `"add apiary"` permission — the entitlement check *supplements* it.
      A user needs BOTH `"add apiary"` AND `canAddApiary() === TRUE`.
- [ ] Kernel test verifying: default service always allows; mock returning FALSE blocks creation.
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
### Service replacement pattern (no hard dependency)
Commerce modules provide their own `EntitlementInterface` implementation and register it as
the `hivelog.entitlement` service via their own `.services.yml`. Drupal's service container
will use whichever service ID is registered. Two approaches:

**A — Override the service ID.** Commerce module registers its service as `hivelog.entitlement`
(overriding core's definition). This works but requires the commerce module to be installed
first (or core's service to not exist yet, which causes a broken dependency if anything calls
it before the commerce module loads).

**B — Use an interface tag.** Core defines the service as `hivelog.entitlement`. Commerce modules
register their implementation with a different ID and use a compiler pass or a factory wrapper
(`EntitlementManager`) that checks whether a commerce entitlement is installed and active.
This is more robust but adds complexity.

**C — Hook-based (simpler fallback).** Core registers the default service. Commerce modules
use `hook_hivelog_entitlement_alter()` to register their own implementation. If a commerce
entitlement is present, it takes priority; otherwise the default is used. This follows the
Drupal pattern for optional subsystem integration (similar to how `hook_menu_alter` works).

Recommendation: **Option A with a safety check.** The service ID `hivelog.entitlement` is
registered in core's services.yml. If a commerce module is installed and also registers
`hivelog.entitlement`, Drupal 11's container builder will use the last-registered one (order
is deterministic based on module dependency weight — see `system.list_dependencies`). To be
explicit, the commerce submodule should declare a dependency on `hivelog`. No hard dep from
core to commerce.

### Quota enforcement
When `getAccountQuotas()` returns finite limits, ApiaryForm checks the user's current count:
```php
$quotas = $this->entitlement->getAccountQuotas($account);
$currentApiaries = Count::where(['uid' => $account->id()])->count()->execute();
if ($currentApiaries >= $quotas['apiaries']) {
  // Hide add button, show message.
}
```

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0109-entitlement-interface-commerce-gating]]
- Commits::
