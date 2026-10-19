---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-18
---
# Project: Hivelog Commerce Submodule (Separate Repository)

## Goal
Provide a separate, standalone Drupal module that integrates HiveLog with commerce platforms
(Drupal Commerce, Stripe, etc.) for the SaaS / hosted deployment use case. This module lives
in its own repository and is **not** shipped with core — following ADR-0098's submodule split
philosophy.

This project addresses how beekeepers subscribe to HiveLog on a hosted site without baking any
commerce platform into HiveLog core.

## Context

HiveLog supports two deployment models ([[0109-entitlement-interface-commerce-gating]]):

| Model | Who creates apiaries? | How do they get access? |
|---|---|---|
| Friend/family (self-hosted) | Anyone with the role | Directly — no gating |
| SaaS / hosted | Only subscribers | Commerce payment → account + apiary |

The core module provides `EntitlementInterface` and a default "always allow" service.
This submodule implements that interface for commerce platforms, wiring subscription events
to HiveLog's permission system.

## Scope
- In scope:
  - A reference implementation of `EntitlementInterface` for one or more commerce platforms
    (Drupal Commerce first, Stripe second).
  - Subscription webhook handlers for payment lifecycles (activate, suspend, cancel).
  - Auto-create Drupal user account and first apiary on successful payment.
  - Tier-based quota enforcement (apiaries/hives per tier).
  - Role management: grant/revoke `"add apiary"` permission based on subscription status.
  - Admin UI for subscribers to view their plan, upgrade/downgrade, manage payment info.
- Out of scope:
  - Payment gateway integrations — the commerce module handles those; this just reacts to
    subscription lifecycle events.
  - Multi-tenant hosting infrastructure (server config, user provisioning pipeline).
  - Core HiveLog changes beyond what `EntitlementInterface` exposes.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [ ] Separate repository created (`github.com/deburca/hivelog-commerce` or equivalent).
- [ ] `hivelog_commerce.info.yml` declares dependency on `hivelog`.
- [ ] `src/Entitlement/CommerceEntitlement.php` implements `EntitlementInterface`.
- [ ] Webhook endpoint for commerce platform subscription events.
- [ ] Admin configuration page: choose tiers, map tiers to permissions/quota limits.
- [ ] Documentation: how to install, configure, and connect a commerce platform.

## Open questions
- Which commerce platform to support first? Drupal Commerce (native Drupal) or Stripe
  (broader market, lower barrier for non-Drupal sites)? Suggest **Drupal Commerce** for
  Drupal-first compatibility; Stripe can follow in the same repo as an additional provider.
- How does this module handle the case where a user's subscription is managed by a
  third-party billing system (e.g., Paddle, Lemon Squeezy) rather than a Drupal commerce module?
  Possible solution: expose a generic webhook API that any billing system can call with
  a signed payload.
- What happens to apiaries owned by a lapsed user's members? Members retain access; only the
  owner loses `"add apiary"`. This is intentional — member relationships are persistent and
  not subscription-gated.

## Related decisions
- [[0109-entitlement-interface-commerce-gating]] (ADR — entitlement interface + commerce gating design)
- [[0098-nanoprobe-collective-locutus-submodule-split]] (ADR — submodule split philosophy)
- [[0108-apiary-membership-entity-and-roles]] (ADR — Layer 2, membership roles)
