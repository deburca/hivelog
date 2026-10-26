---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hivelog-commerce-submodule]]"
area: entity
created: 2026-10-26
branch: feature/0227-hivelog-commerce-submodule-repo
release:
depends-on: ["[[0225-hivelog-entitlement-interface]]", "[[0226-hivecount-quota-enforcement]]"]
blocked-by:
---

# Task: The hivelog_commerce submodule, in its own repository

## Context
The intention (stated 2026-10-26): an extra layer can be installed and configured on top of HiveLog so that a
host can offer **subscriptions for hosting accounts with apiaries**. A beekeeper subscribes and receives either a
**cut-down version of HiveLog for free**, or the **full version** in a tier chosen by number of hives. The tiers
the owner gave (1 to 3, 4 to 7, 8 to 14, 15 or over) are **examples**, not fixed values.

This is the commerce half of the shared-apiaries work. The core half is the entitlement contract of
[[0109-entitlement-interface-commerce-gating]] (tasks [[0225-hivelog-entitlement-interface]] and
[[0226-hivecount-quota-enforcement]], both revised 2026-10-26 for 3.0.0); this task is the separate package that
implements it on a commerce platform, as the project note [[hivelog-commerce-submodule]] and ADR-0098's submodule
split describe. It is **not** shipped with HiveLog; a self-hosted site never sees it.

## Decisions (2026-10-26)
1. **Free version:** the current HiveLog features, minus sensors (nanoprobe) and potentially minus multiple members and
   minus hive moves. Which of those are off is a setting of the free model, not code.
2. **Free version has a hive limit,** initially **5**, configurable.
3. **Tiers are configurable data,** not constants: each has a **lower and an upper hive bound**, a **price**, and
   belongs to a category; an administrator can add several categories. A tier with no upper bound ("15 or over")
   means **unlimited**.
4. **Billing runs on Drupal Commerce,** with **Stripe or similar** as the payment gateway.
5. **The primary owner's subscription pays** for an apiary (`Apiary::getPrimaryOwner()`). During a hand-over the
   **current owner pays until the new owner takes over**; at that point the new owner must buy a compatible
   subscription. Until they do, the **free model** applies to the apiary.
6. **Downgrade while over the new limit:** the **free model** applies (existing hives stay, adding is refused).
   Nothing is deleted, and the data stays readable and editable.
7. An apiary with two owners during a hand-over is counted once, for the primary owner.

## Acceptance criteria
- [ ] A separate repository exists (`github.com/deburca/hivelog-commerce` or equivalent), a Composer package
      requiring `hivelog/hivelog ^3.0`, with its own CI, versioning and release notes
- [ ] `hivelog_commerce.info.yml` depends on `hivelog` and Drupal Commerce; installing it changes nothing on a site
      until a plan is configured
- [ ] **Plans as configuration:** a plan category and tier configuration (entities), each tier with a name, a lower
      and an upper hive bound (upper empty = unlimited), a price and its Commerce product variation; several
      categories possible; validation that tiers in one category do not overlap
- [ ] **The free model as configuration:** its hive limit (default 5) and which features are on (sensors, multiple
      members, hive move; defaults per decision 1)
- [ ] `CommerceEntitlement` implements the core `EntitlementInterface` (`canAddApiary()`, `getHiveLimit()`,
      `hasFeature()`), replacing the default service; its answer for an apiary is the plan of its **primary owner**,
      or the free model if that owner has no compatible plan
- [ ] **Compatible plan:** an active subscription whose range covers the owner's current hive count; one that does not
      (too small after hives were added or arrived by a hand-over or a move) is not compatible and the free model applies
- [ ] **Subscription lifecycle** from Drupal Commerce's own events (and a signed generic webhook for other billing
      systems): activate, change tier, lapse, cancel; idempotent; invalidates the owner's
      `hivelog_entitlement:<uid>` cache tag on every change
- [ ] **Hand-over:** implements `hook_hivelog_primary_owner_changed()` (task 0226): tells the new owner by e-mail
      that they need a subscription and which tier fits their hive count, and shows the same on their dashboard
- [ ] On a successful first payment, a Drupal account and a first apiary (the subscriber an `owner` member) are
      created; an existing account just gets its plan
- [ ] A lapsed or cancelled subscription never deletes data and removes no permissions or roles; the free model applies
      and the owner sees why
- [ ] A subscriber page: current plan, hive count against the limit, the apiaries that count, upgrade or downgrade,
      payment details (through Drupal Commerce's account pages)
- [ ] Documentation: install, configure plans and the free model, connect Stripe, what each model unlocks
- [ ] Kernel tests for tier selection (bounds, unlimited, categories), the compatible-plan rule, the hand-over and
      the lifecycle; phpcs and phpstan clean

## Implementation notes
- **Count by primary owner, never `uid`.** Hive count = hives in every apiary whose primary owner is the account,
  each apiary once. Core provides it (`hivelog.entitlement_checker`, task 0226); the commerce layer only chooses
  the tier from it.
- **A move changes whose count it is.** The target apiary's primary owner is checked on a hive move (task 0226).
- **Hand-over.** When the first owner is removed the next owner becomes primary (0232). The new owner's plan is looked
  up at that moment; usually none, so the apiary falls to the free model until they subscribe. The hand-over does
  not transfer the previous owner's subscription.
- **No role or permission changes** on lapse (this replaces the old design in 0226); the entitlement is asked at the
  point of use.
- **Grace period** for past-due payments, if wanted, is this package's own setting.
- ADR-0109 is `proposed` and still says a lapsed user loses `add apiary`; it needs an amendment before this task
  starts (entitlement as a question asked at use, the free model, `getHiveLimit()` and `hasFeature()`).

## Open questions
1. **Apiary limit on the free model?** Hives are limited (5); is the number of apiaries too?
2. **Exact free feature set:** are multiple members and hive moves off in the free model by default, and what of AI
   insights (nexus, collective), the app API (hivelog_api), inventory and cost reports, the calendar and photos?
3. **Billing details:** monthly or yearly or both, currency, VAT/tax, trials; who sets prices (assumed: the host,
   in Drupal Commerce)?
4. **"Compatible subscription" at a hand-over:** must the new owner buy a tier covering the apiary's hive count plus
   their own existing hives (assumed yes, by total across their apiaries)?
5. **Hives over the free limit:** editable as now (assumed), with adding blocked; or read-only?
6. **Does a member of someone else's apiary need a subscription?** (Assumed: no.)
7. Repository name, public or private, and whether it targets the same host as the first deployment.
8. **Which Stripe integration** (Commerce Stripe plugin, recurring via Commerce Recurring, or Stripe-hosted billing
   with webhooks)? Decide when this task starts.

## Related
- Project:: [[hivelog-commerce-submodule]]
- Decisions:: [[0109-entitlement-interface-commerce-gating]], [[0098-nanoprobe-collective-locutus-submodule-split]], [[0108-apiary-membership-entity-and-roles]]
- Core tasks:: [[0225-hivelog-entitlement-interface]], [[0226-hivecount-quota-enforcement]]
- Follows:: [[0232-apiary-cbr-number-from-primary-owner]]
- Commits::
