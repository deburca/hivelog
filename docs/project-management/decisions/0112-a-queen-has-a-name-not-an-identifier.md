---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-11-25
supersedes:
---

# ADR-0112: A queen has a name, not an identifier

## Status
accepted, 2026-11-25, by the maintainer. Written for task [[0297-consistent-field-labels]]
(raised by the Vinculum app's UI review, Vinculum task 0081).

## Context
`queen.name` is the queen's label field. Until 4.5 it was labelled "Queen ID" and described
as "A human readable identifier for this queen, e.g. Q-2026-001." Nothing in the module
treats it as an identifier: it is free text, not unique, not generated, and nothing parses
it. It is what the queen's page, breadcrumbs and lists show as her title.

The Vinculum app reads the same field from `/api/v1/schema` and shows it as the queen's name
("Queen Maud"), with its own words for a queen that has none. The site's form and the app
disagreed on what the beekeeper is being asked for.

## Decision
`queen.name` is the queen's **name**. Its label is "Name" and its description gives a name
as the example: "A name for this queen, e.g. Maud." The field key stays `name`, so the API
and every stored value are unchanged. HiveLog does not add a separate identifier field; a
beekeeper who numbers queens can still type a number as her name.

## Consequences
- Positive: the site and the app ask for the same thing in the same words; "Queen ID" no
  longer suggests a uniqueness or format the module never enforced.
- Negative / trade-offs: none for data. A site whose queens are already named
  `Q-2026-001` keeps those values; they simply read as names.
- The app's fallback words for an unnamed queen stay as they are (a queen without a name).
- Base-field label only: no update hook, no API version change (ADR-0107).

## Related
- Tasks:: [[0297-consistent-field-labels]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
