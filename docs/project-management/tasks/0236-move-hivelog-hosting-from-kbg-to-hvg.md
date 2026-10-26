---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[shared-apiaries-and-team-roles]]"
area: install
created: 2026-10-26
branch:
release:
depends-on: ["[[0233-roll-out-3-0-x-to-the-sites]]"]
blocked-by:
---
# Task: Move HiveLog hosting from kbg to hvg

## Context
Decided 2026-10-26: the **hvg** site will supersede kbg (`kragebaekgaard.ddev.site`, `kragebaekgaard.dk`) as the host
for HiveLog. Until hvg is installed and configured, kbg stays the only site that runs and is tested with it.

## Acceptance criteria
- [ ] hvg's local and web hostnames recorded here
- [ ] hvg has the module and its dependencies installed (geofield, leaflet, and key and simple_oauth if `nexus` and
      `hivelog_api` are used), its theme and menu set up, and the HiveLog permissions given to its roles
- [ ] OAuth keys generated on hvg with `private://` paths, and the Vinculum app pointed at hvg's address
- [ ] Data brought over from kbg (database export or a migration), checked afterwards: apiaries, owners, roles, CBR
      numbers, hives, history and files
- [ ] `'demo'` in `file_scan_ignore_directories` kept in hvg's `settings.php` (already added); the testing rule
      "HiveLog is tested only on its host" moves to hvg, and `AGENTS.md` and tasks 0233 and 0235 are updated
- [ ] kbg's HiveLog retired or left read-only, as decided when this starts

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0233-roll-out-3-0-x-to-the-sites]]
- Commits::
