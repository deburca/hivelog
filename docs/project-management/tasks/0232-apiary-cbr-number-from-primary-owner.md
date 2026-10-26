---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-26
branch: feature/0232-apiary-cbr-number-from-primary-owner
release:
depends-on: ["[[0224-permission-granularity-by-role]]"]
blocked-by:
---
# Task: The apiary's CBR number comes from its primary owner

## Context
Follow-up to [[0224-permission-granularity-by-role]], from trying the hand-over on a site (2026-10-26). The CBR
number belongs to a person (the `cbr_number` field on the user). Since 0224 the apiary's `uid` is "created by",
yet the apiary list still read the owner and the CBR number from it, so after a hand-over it kept showing the
old owner. Handing an apiary over is "A adds B as owner; later B removes A", so for a while there are two owners.

Decisions: the CBR number moves with the owner. During a hand-over there should be **at most two owners**, and
showing a warning when there are more is enough (it is not blocked). When the current owner is removed, the apiary
takes the CBR number of the next user who was made an owner.

## Acceptance criteria
- [x] A membership records when it became an owner membership (`owner_since`, set when the role becomes `owner`,
      cleared when it stops, `hivelog_update_10038` backfills from the creation time)
- [x] The **primary owner** is the owner who has held the role longest (then lowest id); the apiary's CBR number is
      theirs, trimmed, or empty if they have set none (it does not fall back to another owner's)
- [x] The apiary list shows that CBR number and every owner (longest first) instead of reading `uid`; the cached
      table is invalidated by memberships and users
- [x] The apiary page shows "CBR number" in its details
- [x] More than two owners shows a warning on the apiary page and in the Members section, to someone who may manage
      the apiary, naming the owners and whose CBR number is used
- [x] The Members section says whose CBR number the apiary carries
- [x] Tests (order, hand-over, no number, a member promoted later, warning and who sees it, the page, the list,
      the hook); phpcs and phpstan clean
- [x] `AGENTS.md` and the 3.0.0 release note updated

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]]
- Commits:: merged for 3.0.0
