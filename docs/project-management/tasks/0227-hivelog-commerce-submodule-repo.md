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
host can offer **subscriptions for hosting accounts with apiaries**. A beekeeper subscribes and receives:

- a **cut-down version of HiveLog for free**; or
- the **full version**, as a choice of tier by number of hives: **1 to 3**, **4 to 7**, **8 to 14**, and **15 or over**.

This is the commerce half of the shared-apiaries work. The core half is the entitlement contract of
[[0109-entitlement-interface-commerce-gating]] (tasks [[0225-hivelog-entitlement-interface]] and
[[0226-hivecount-quota-enforcement]]); this task is the separate package that implements it for a commerce
platform, as the project note [[hivelog-commerce-submodule]] and ADR-0098's submodule split describe. It is
**not** shipped with HiveLog; a self-hosted site never sees it.

Version 3.0.0 changed what an "owner" is (see below), which the core tasks 0225 and 0226 were written before.

## Acceptance criteria
- [ ] A separate repository exists (`github.com/deburca/hivelog-commerce` or equivalent), a Composer package
      requiring `hivelog/hivelog ^3.0`, with its own CI, versioning and release notes
- [ ] `hivelog_commerce.info.yml` declares the dependency on `hivelog`; installing it changes nothing on a site
      until a plan is configured
- [ ] A tier model: **free (cut-down)**, and the paid tiers 1 to 3, 4 to 7, 8 to 14 and 15 or over hives, each with
      what it allows (hive limit, apiary limit, the features of the full version), configurable by the site
      administrator, not hard-coded
- [ ] `CommerceEntitlement` implements the core `EntitlementInterface`: `canAddApiary()`, and the quotas for the
      user's current tier (`getAccountQuotas()`), replacing the default "always allowed" service
- [ ] Subscription lifecycle: activate, upgrade or downgrade between tiers, lapse and cancel, from the commerce
      platform's events (a webhook endpoint, or the platform's own events), idempotent and signature-checked
- [ ] On a successful first payment, a Drupal account and a first apiary are created (an `owner` membership for
      the subscriber)
- [ ] A lapsed or cancelled subscription never deletes data: existing apiaries stay readable, `add apiary` and
      `add hive` are withdrawn, members keep their access (their membership is not subscription-gated), and the
      subscriber sees why
- [ ] A subscriber page: current plan, hive count against the limit, upgrade or downgrade, payment details
      (through the commerce platform)
- [ ] First platform: **Drupal Commerce** (recurring); a generic signed webhook so any billing system can call it
- [ ] Documentation: install, configure the tiers, connect a platform, what each tier unlocks
- [ ] Kernel tests for tier-to-quota mapping and the lifecycle; phpcs and phpstan clean

## Implementation notes
- **Count by owner memberships, not `uid`.** Since 3.0.0 an apiary's owners are its `owner` memberships (several
  at once during a hand-over), and `uid` is only "created by". Tasks 0225 and 0226 count "apiaries owned by the
  user" through `uid`, which is now wrong. Whoever's subscription pays for an apiary should be its **primary
  owner** (`Apiary::getPrimaryOwner()`, the one who has held the role longest), so a hand-over moves the apiary's
  hives to the new owner's count, and two owners during a hand-over do not count the apiary twice.
- **Moving a hive between apiaries** (3.0.0) changes whose hive count it is. The check on a move belongs to the
  target apiary's owner subscription.
- A tier is the *upper* bound. When a subscriber has more hives than a lowered tier allows, hives are never
  removed: new hives are refused until they are under the limit or upgrade.
- "Cut-down" needs a definition before it can be built (see open questions). The core contract only has quotas
  (apiaries and hives); a feature split needs either a new method on `EntitlementInterface` (for example
  `hasFeature()`) or a set of Drupal permissions granted per tier. Which is better is part of this task, and
  any core change goes through 0225.
- Members and roles stay separate from subscriptions (ADR-0108, ADR-0109): an owner who lapses keeps the apiary;
  only the ability to add more is withdrawn.

## Open questions
1. **What is in the cut-down free version?** Which features does the full version add: sensors (nanoprobe),
   AI insights (nexus and collective), the mobile app API (hivelog_api), inventory and cost reports, the seasonal
   calendar, photos? Is the free version also limited in hives or apiaries?
2. Is the "15 or over" tier unlimited, or priced per hive above 15?
3. Billing: monthly and yearly, currency, VAT, trials; and who sets prices, the host in the commerce platform?
4. Which owner's subscription pays when an apiary has two owners during a hand-over, and what happens to the hive
   count when the primary owner is removed (the note above suggests the primary owner)?
5. Does a member (beekeeper or novice) of someone else's apiary need a subscription at all? (Assumed: no.)
6. Downgrade while over the new limit: refuse the downgrade, or allow it and block new hives?
7. Repository name and whether it is public, and whether it targets the same host as the first deployment.
8. Do tasks 0225 and 0226 need revising for 3.0.0 (the `uid` counting above, and the feature question) before
   this task starts? (Probably yes.)

## Related
- Project:: [[hivelog-commerce-submodule]]
- Decisions:: [[0109-entitlement-interface-commerce-gating]], [[0098-nanoprobe-collective-locutus-submodule-split]], [[0108-apiary-membership-entity-and-roles]]
- Core tasks:: [[0225-hivelog-entitlement-interface]], [[0226-hivecount-quota-enforcement]]
- Commits::
