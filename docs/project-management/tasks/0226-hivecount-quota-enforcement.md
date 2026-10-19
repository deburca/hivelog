---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: routing
created: 2026-10-18
branch: feature/0226-hivecount-quota-enforcement
release:
depends-on: ["[[0225-hivelog-entitlement-interface]]"]
blocked-by:
---

# Task: Quota enforcement and subscription lifecycle hooks

## Context
Part of [[shared-apiaries-and-team-roles]]. Follows [[0225-hivelog-entitlement-interface]].
Once the entitlement interface exists, this task wires it into apiary creation and adds
the account-lifecycle hooks needed for hosted/SaaS use cases.

## Acceptance criteria
- [ ] `ApiaryForm::form()` checks quota via `$this->entitlement->getAccountQuotas()`:
      if user has reached their apiary limit, the "Add apiary" button is hidden and a
      site-configurable message is shown ("Upgrade your plan to add more apiaries").
- [ ] Same quota check applies to hive creation: `HiveForm::form()` checks hive count
      against entitlement quotas before allowing creation.
- [ ] The check uses the user's *own* account, not the entity being created — so it fires
      before any form state validation and cannot be bypassed by submitting an invalid form.
- [ ] Quota counts are cached per-user with a sensible TTL (e.g., 60 seconds) to avoid a
      database query on every page load. Invalidate cache when a new apiary/hive is saved.
- [ ] `hook_hivelog_subscription_change()` event hook fired whenever an entitlement
      implementation signals a status change:
      - `subscription_active` — user has valid subscription
      - `subscription_lapsed` — user's subscription expired (no auto-deletion of data)
      - `subscription_upgraded` — user moved to a higher tier
- [ ] When a user loses their subscription (`subscription_lapsed`):
      - Remove `"add apiary"` and `"add hive"` permissions from the user's own roles.
      - Show a status message on every page: "Your subscription has lapsed. You can still
        view your existing data but cannot add new apiaries or hives."
  - Status is restored automatically when `subscription_active` fires (the commerce module
    re-grants the role).
- [ ] Configurable grace period setting (`hivelog.settings`): number of days after which a
      lapsed subscription triggers permission revocation. Default: 0 (immediate). During grace
      period, user keeps `"add apiary"` but is shown a prompt to renew.
- [ ] Kernel test verifying quota enforcement blocks creation at the limit;
      functional test verifying status messages appear correctly.
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
### Grace period configuration
A site-wide setting in `hivelog.settings` (managed via an admin form on the HiveLog
configuration page):
```yaml
# File: hivelog.settings
entitlement_grace_period_days: 7
```
During the grace period, `canAddApiary()` returns TRUE but the form shows a "Renew your
subscription" banner above the add button. After grace period, it returns FALSE and hides
the button.

### Hook contract for commerce modules
Commerce modules implement this hook to notify HiveLog of subscription events:
```php
/**
 * React to a subscriber's subscription status changing.
 *
 * @param string $event
 *   'subscription_active', 'subscription_lapsed', or 'subscription_upgraded'.
 * @param \Drupal\user\UserInterface $account
 *   The user whose subscription changed.
 * @param string|null $tier
 *   The tier they moved to (or left), e.g. 'free', 'pro', 'enterprise'.
 */
function hook_hivelog_subscription_change(string $event, UserInterface $account, ?string $tier = NULL) {
  if ($event === 'subscription_lapsed') {
    // Revoke add permissions.
    foreach ($account->getRoles() as $rid) {
      \Drupal::service('user.role.storage')->load($rid)->revokePermission('add apiary');
    }
  }
}
```

This is the cleanest integration point because:
1. Commerce modules already fire their own payment/webhook hooks — they know when a
   subscription starts, ends, or changes tier.
2. HiveLog just needs to react; it doesn't need to understand how commerce works.
3. The hook is optional — self-hosted sites never call it and nothing breaks.

### Quota counting approach
Use entity queries with user filter:
```php
$count = \Drupal::entityQuery('apiary')
  ->condition('uid', $account->id())
  ->count()
  ->execute();
```
Cache in the user's cache context (invalidate on `entity.apiary.insert` and
`entity.hive.insert`).

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0109-entitlement-interface-commerce-gating]]
- Commits::
