---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/apiary-page-remove-members-fold
release: 4.3.0
depends-on: []
blocked-by: []
---
# Task: Apiary page: remove the Members fold

## Context
The apiary page's Details section has a collapsible "Members" line (a `<details>` listing display names and roles, shown to people who may update the apiary), added in task 0255 to keep the facts strip compact. The same information is one click away behind the **Manage members** button beside Edit, so the fold is duplicate and the user asked for it to go (reported on `/app/apiary/1`).

## Acceptance criteria
- [x] The Members `<details>` and its member list are removed from `ApiaryController::buildSummary()`, with the `$members` loop, the `apiary_membership_list` cache tag it added, and the `ApiaryMembership` / `Html` imports if nothing else uses them
- [x] The "Manage members" button is unchanged (still owner-only as today, linking to the Members tab of the apiary edit form)
- [x] The owner warning (`HivelogOwnerWarning`, more than two owners) is unchanged
- [x] If the Details section is left with only the facts strip, check the "more" wrapper (`hivelog-apiary-more`) is removed too and nothing renders an empty `<details>`
- [x] Tests updated: any assertion that the page lists member names is changed to assert it does not (members are listed only on the manage form)
- [x] AGENTS.md: the sentence "The apiary canonical page shows the members, with a Manage members link, only to owners" is updated to say the page shows the Manage members link only
- [ ] Phone width (≤480px) checked

## Implementation notes
- Key file: `src/Controller/ApiaryController.php` (`buildSummary()` around the "Members fold behind one line" comment, and `buildApiaryActions()`).
- Check the theme: beeswax may style `.hivelog-apiary-more`; a leftover rule is harmless but note it for the beeswax vault if so.

- Removed the `<details>` block, the member loop and the unused `$empty`. `ApiaryMembership` and `Html` are still used elsewhere in the controller, so their imports stay.
- The summary's cache now always carries `apiary_membership_list`: the "Your role" tile reads the viewer's membership, so a role change must refresh it (before, the tag was only added for owners).
- `.hivelog-apiary-more` no longer has an element; beeswax may still style it, which is harmless (check its CSS when next in that repo).
- Phone width not checked (the browser pane cannot load the site stylesheets).

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
