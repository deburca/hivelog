---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[ios-field-app]]"
area: ios
created: 2026-10-07
branch: feature/0219-map-zoom-and-hive-rows
release:
depends-on:
blocked-by:
---
# Task: iOS app: a usable first view of the map, and more on a hive's row

## Context
Field comments from the owner testing Vinculum on a phone:
1. **Map:** the first view is "at 4 m", too close to see anything. It should start at 250 m.
2. **Hives:** a hive's row spends a lot of space on little information (its name and, say, `10x12`).
   Add the breed of bee and the hive's status.

## Acceptance criteria
- [x] The map starts at least 250 m across: one apiary is centred at 250 m; several are all in view
      with a margin, never closer than 250 m
- [x] A hive's row shows the breed of its active queen and the hive type ("Buckfast · Langstroth"),
      and its status, always (an active hive too), in words from the server's own schema
- [x] No request per hive: one request for the whole apiary's active queens
- [x] A hive with no queen, or a failed queen request, still lists (just the type)
- [x] Tests; checked on the simulator against the demo site
- [ ] Looked at on a phone with real hives

## Implementation notes
- **Cause of the 4 m view:** `Map` with no camera fits its markers, and one marker is a point, so
  MapKit zooms right in. `ApiaryMap.startingRegion(of:)` now gives `Map(initialPosition:)` a region:
  the bounding box of the placed apiaries x 1.5, but at least `minimumStartMetres` (250) across.
  Pure function, tested for one apiary, two close together and two far apart.
- **Breed is on the queen, not the hive.** A hive has `hive_type`, `hive_material`, `temperament`
  and `status`; the breed belongs to its active queen (`queen.breed`). `APIClient.activeQueens(in:)`
  asks `queen?filter[hive.apiary.id]=<apiary>&filter[status]=active` once and keys the answer by
  hive (`Queen.hiveID`, new). The hive list loads it beside the hives and does without it if it
  fails. The server already allows this filter (the app's `view own queen` and the query narrowing
  of task 0205).
- `HiveRowText.subtitle` / `.status` build the words (the schema's labels, so "Apis mellifera
  carnica (Carniolan)" appears as the server names it; the fallback capitalises the first letter
  only, since `capitalized` turned `10x12` into `10X12`, which a test caught).
- Before, the status chip was shown only for a hive that was *not* active, which read as "no status".

## Verification
- `swift test`: 149 + 91 tests pass (new: the apiary-queens client test; `HiveRowAndMapTests`, 7).
- Simulator (iPhone 18 Pro, demo site): the map opens at street level around the Heathland pin; the
  hive list shows "Buckfast · Langstroth", "Apis mellifera carnica (Carniolan) · Norwegian" (wraps to
  two lines) and an ACTIVE chip on each row.

## Not verified
- A long breed label wraps to two lines (as on Hive 2); if that is too tall, shorten the label
  (drop the bracketed part) or move the type to the chip.
- Several apiaries far apart, on a device.

## Related
- Project:: [[ios-field-app]]
- Decisions:: 
- Commits:: app repository `feature/0219-map-zoom-and-hive-rows` (not committed yet)
