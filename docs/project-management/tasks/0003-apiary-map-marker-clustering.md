---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: theme
created: 2026-06-16
branch:
release:
---
# Task: Cluster apiary markers on the Leaflet map

## Context
As the number of apiaries grows, individual Leaflet markers overlap. Add
marker clustering to the apiary map for readability. Apiary geodata is a
`geofield` `geolocation` column (WKT POINT) rendered through the
`leaflet_widget_default` widget — see [[0001-geofield-over-geolocation]].

## Acceptance criteria
- [ ] Markers cluster at low zoom and split apart on zoom-in.
- [ ] No new server-side dependency (clustering is a Leaflet front-end concern).
- [ ] Works with the existing geofield data; **no schema change**.

## Implementation notes
- Likely a Leaflet library/settings toggle in `hivelog.libraries.yml` +
  attaching clustering assets; confirm the contrib `leaflet` module's
  clustering support before committing an approach.
- Reconfirm [[0002-no-geocoder-dependency]] still holds — clustering must not
  drag in geocoder.

## Related
- Project:: _(unassigned — candidate for a future "Map UX" project)_
- Decisions:: [[0001-geofield-over-geolocation]], [[0002-no-geocoder-dependency]]
- Commits:: 
