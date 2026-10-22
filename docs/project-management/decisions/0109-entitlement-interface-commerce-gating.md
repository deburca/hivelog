---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-10-18
supersedes:
---

# ADR-0109: Entitlement interface for commerce-subscription gating

## Status
proposed — designing how HiveLog gates apiary creation for hosted/SaaS deployments without baking in a specific commerce solution.

## Context

HiveLog will be used in two distinct deployment models that must not conflict:

**Use case 1 — Friend/family (self-hosted):** A beekeeper installs HiveLog on their own
Drupal site and manages apiaries with friends and family. The `"add apiary"` permission
is granted to any authenticated user (or a custom role). No entitlement check is needed
beyond the standard Drupal permission system.

**Use case 2 — Hosted SaaS:** The site owner hosts HiveLog as a service where beekeepers
subscribe (via any commerce solution) and get their own apiaries. In this model:
- Only users with an active subscription may create apiaries.
- Different tiers grant different quotas (number of apiaries, number of hives).
- Lapsed subscriptions should be handled gracefully (no new apiaries; existing data
  preserved but frozen at the owner level).
- A successful payment flow may need to auto-create a Drupal user account and their
  first apiary.

The membership role system from [[0108-apiary-membership-entity-and-roles]] handles Layer 2
(what members can do *within* an apiary). This ADR addresses **Layer 1** (whether a user
may create any apiaries at all).

### Existing patterns HiveLog follows for optional subsystems

HiveLog already handles optional features without hard dependencies:
- `hivelog_api` submodule depends on contrib `simple_oauth` via Composer (ADR-0107).
- `nexus` submodule depends on contrib `key` via Composer.
- Both are optional submodules under `modules/`; core never `depends()` on them.
- Uninstall hooks handle cases where the contrib module is not installed.

The question for entitlement gating: should the interface live in core as a hook, an
interface + default service, or somewhere else?

### Options evaluated

#### Option A: Drupal hook (`hook_hivelog_can_add_apiary`)
A procedural hook every commerce module implements. Simple but untyped, no caching
integration, and hooks are considered legacy from Drupal 12 onward.

#### Option B: Optional interface + default null service (recommended)
Core defines an `EntitlementInterface` with a single method (`canAddApiary`, optionally
more for quotas). Core registers a `default_entitlement` that always returns TRUE. A
commerce module provides its own service implementing the interface and tagged to replace
the default via Drupal's service decoration/replacement mechanism (or via a separate
`hivelog_entitlement_commerce` submodule). Zero hard dependencies.

#### Option C: Commerce submodule included in core repo
A `modules/hivelog_commerce/` folder with Stripe / Drupal Commerce integration shipped
with the module. The commerce-specific install file is what ties things to that platform,
not core. This follows HiveLog's existing submodule pattern (`nanoprobe`, `collective`,
etc.).

#### Option D: Rely on Drupal's existing subscription systems
Modules like `commerce_subscription` or `user_access` already handle this for other
sites. A site configures those separately and uses `"add apiary"` permission gating
(only subscribers get the role). HiveLog adds nothing.

### Recommendation: Option B (interface + default service) layered on Option D

Option B provides a clean contract; Option D handles the practical case where most
Drupal sites already have a subscription/subscription-like system (e.g., Stripe module,
Ubercart subscriptions, or manual role assignment). The interface lets a commerce module
automate what would otherwise be manual permission assignment.

**Why not ship a commerce submodule (Option C)?** A full commerce integration is a large
surface area (payment gateways, webhooks, webhook verification, idempotency, fraud checks,
refund handling) that should live in its own project and be maintained by people who know
commerce ecosystems. HiveLog's value is the beekeeping domain model; tying it to a specific
commerce stack would constrain future maintenance and create update-dependency risks.

Instead, ship **the integration skeleton** — an optional `hivelog_commerce` submodule
with a reference implementation for one commerce platform (Drupal Commerce or Stripe) —
in its own repository. The core repo provides the interface; the submodule repo provides
the integration. This follows ADR-0098's module split philosophy.

## Decision

1. **Core defines an entitlement contract as an interface + default null service.**
   `EntitlementInterface::canAddApiary(AccountInterface $account): bool` — returns TRUE
   by default (friend/family always works). A commerce module implements this to gate
   creation on subscription status. No hard dependency added to core.

2. **Quota enforcement is a separate, extensible method.** The interface includes
   `getAccountQuotas(AccountInterface $account): array` returning `(apiaries => int, hives =>
   int)`. Default is `(PHP_INT_MAX, PHP_INT_MAX)` (no quota). Commerce implementations
   return tier-specific limits.

3. **The apiary form checks entitlement before allowing creation.** `ApiaryForm::form()`
   calls the entitlement service. If it returns FALSE, the "Add apiary" button is hidden
   and a status message explains how to subscribe (configurable per site). The check
   fires *before* `$form_state->isValidationFailed()` so invalid forms don't consume a
   quota slot.

4. **A commerce submodule lives in its own repo.** Provides `hivelog_commerce` with:
   - Stripe / Drupal Commerce webhook handlers for subscription lifecycle events
   - Auto-create Drupal account + apiary on successful payment
   - Revoke `"add apiary"` permission when subscription lapses
   - Tier-based quota enforcement via the entitlement interface
   This submodule is a separate Packagist package, not included in core's distribution.

5. **Account lifecycle hooks.** When a subscription ends:
   - User keeps access to existing apiaries (data is never auto-deleted).
   - `"add apiary"` permission is revoked on the user's own roles.
   - A status message appears on their dashboard explaining their account status.
   - An `hivelog` event (or Drupal hook) fires so other modules can react if needed.

## Consequences

- Positive:
  - Self-hosted sites work exactly as today — zero configuration changes.
  - SaaS hosts get a clean, typed contract for commerce integration without HiveLog
    depending on any specific commerce module.
  - Entitlement check is separate from membership roles, so the two systems stay orthogonal.
  - Following ADR-0098: optional code stays in submodules; core has no commerce dependency.

- Negative / trade-offs:
  - SaaS site operators must set up their own commerce integration (or use the reference
    implementation in a separate repo). There's no plug-and-play "HiveLog Commerce" package.
  - The interface is minimal by design — if sites need richer entitlement logic
    (grace periods, proration, team plans), they implement it in the commerce submodule,
    not core.
  - Must be careful that the entitlement check doesn't leak into membership/role logic:
    an apiary owner who loses their subscription still owns their existing apiary;
    their members' access is unaffected.

- Follow-up tasks: [[0225-hivelog-entitlement-interface]],
  [[0226-hivecount-quota-enforcement]],
  [[0227-hivelog-commerce-submodule-repo]] (separate repository).
