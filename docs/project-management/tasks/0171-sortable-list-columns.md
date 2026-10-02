---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: routing
created: 2026-10-02
branch: feature/0171-sortable-list-columns
release:
depends-on:
blocked-by:
---
# Task: Sortable columns on the full collection lists

## Context
Found in the 2026-10-02 review of [[collection-page-filter-coverage]]
(done). Every full collection page can now be narrowed with a filter form,
but not reordered: `HivelogListBuilder` sorts on a single fixed key
(`SORT_KEY`, `src/HivelogListBuilder.php:104`). A beekeeper filtering
Inspections by date still can't sort by weight, or Inventory Items by stock.
This is an enhancement rather than a defect, and some columns are computed
(stock on hand, empty weight) so can't sort in a database query.

## Acceptance criteria
- [x] Propose which columns are sortable per list and which are not
      (computed columns excluded unless cheap to sort in PHP after loading).
      Confirm the proposal before building.
- [x] Column headers for sortable columns render as links that toggle
      ascending/descending and carry `sort` and `order` in the query string,
      alongside existing filter parameters.
- [x] Sort state survives filtering, Reset (Reset clears filters and sort),
      and pagination.
- [x] Only whitelisted field names are accepted from the query string; an
      unknown value falls back to the default sort, never reaching the
      query.
- [x] Accessible: `aria-sort` on the active column header.
- [x] Implemented once in `HivelogListBuilder`, with per-builder opt-in of
      sortable columns — not copied per list.
- [x] Kernel tests: each direction, unknown field, combined with a filter
      and with a pager.
- [x] Verified live on `cms2`.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Needs an `hivelog:entity-table` SDC change if header cells can't currently
  render as links. Check the component's props first.
- If the proposal is large, consider an ADR before building.
- No update hook.

## Implementation notes (as built)
- **The proposal was confirmed before building**, as the task required. Two
  decisions, both taking the recommended option: *stored fields only* (no
  computed columns, no related-record-name sorting) and *all full collection
  lists*. No ADR: the mechanism is one opt-in hook on an existing base
  class, not an architectural change.
- **Mechanism** (`HivelogListBuilder`): `getSortableColumns()` — a map from
  a `buildHeader()` key to the stored field it sorts on — is the *only*
  thing `sort` is ever matched against, so no visitor-supplied string
  reaches the query as a field name. `currentSort()` falls back to the
  default order for an unknown key, a real-but-unsortable header (e.g.
  `breed`), `id`/`uuid`, an empty value, an injection-shaped string, or an
  array-valued `sort`/`order`. `order` is `desc` only if it says so. The
  default id key stays as the tie-breaker, so ties and NULLs page
  deterministically (NULLs sort first ascending, last descending).
  Sorting is in the entity query, so numeric columns sort as numbers
  (9.5 before 100), not as text.
- **Sortable columns per list** (13 lists; the Apiary column additions are described under the amendment below; the Calendar Actions page is
  controller-built and untouched, so it passes no sort prop):
  Apiaries: name. Hives: name, status. Inspections: date, weight, queen
  seen. Queens: name, colour, introduced, status. Queen Observations: date,
  active. Inventory Items: name, category, unit, type, weight, status.
  Purchases: date, quantity, unit price, supplier. Products: name, unit,
  expected unit price, status. Hive/Apiary Action Logs: year, status, week
  completed. API Clients: label, enabled, last run. Sensor Devices: label,
  scope, device type, enabled, last seen. AI Provider Configs: label, mode,
  enabled, last run.
- **Amended after confirmation — Apiary columns (user request).** After the
  above was built, you asked for the Apiary column to be sortable on the
  lists that mix several apiaries. That is the "stored fields + related
  names" option you had declined at the proposal, so this changes a recorded
  decision, for Apiary columns only. Hives, Inventory Items, Inventory
  Purchases, Products and Apiary Action Logs now sort Apiary by the related
  apiary's name, via the relationship path `apiary.entity.name` in the same
  `getSortableColumns()` map (so still never visitor-supplied). Findings:
  the entity query joins that sort LEFT, so a record whose apiary was
  deleted stays listed (NULL first ascending) rather than vanishing —
  covered by a test, since an inner join would have dropped orphans from
  the list and its pager. Within one apiary, rows keep creation order (the
  id tie-breaker). Hive Action Logs'
  Hive column was added on your next request (`hive.entity.name`), since that
  list mixes every hive's logs. **Not done, same mechanism if wanted:** the
  remaining reference columns — Hive on Inspections, Queen on Observations,
  Item on Purchases. Sensor Devices' combined "Apiary / Hive" column is not
  sortable (it is one cell of two different references).
- **Judgement calls beyond "stored fields only"**: I also left out stored
  list fields whose alphabetical order misleads — Queen Observation
  *health* (excellent, fair, good, poor), Inspection *honey stores*, and
  *temperament* — as well as Apiaries' CBR and Location (a related user
  value and a geofield). Plain categorical fields (status, scope, type,
  mode) are in, since grouping is the point. Say if you want the ordinal
  ones with a custom order.
- **Component** (`hivelog:entity-table`): new optional `column_sorts` prop,
  keyed by header string, each `{url, direction: none|ascending|descending}`;
  sortable headers become links with `aria-sort` on the `<th>` and a
  decorative (aria-hidden) arrow. Omitted entirely when empty — an empty PHP
  array would encode as a JSON array and fail the `object` schema.
  `buildEntityTable()` gained an optional fourth argument; its other callers
  are unaffected.
- **Below 768px** the header row used to be visually hidden (the table turns
  into cards), which would have left the sort links as invisible tab stops.
  A sortable table now keeps its header as a compact "Sort by: Hive ▼ Status
  ⇅" bar of just the sortable columns, label passed in translated.
- **Behaviour**: a sort link keeps the active filters but drops `page`
  (a different order makes the old page meaningless); the filter form carries
  `sort`/`order` as hidden inputs so submitting a filter doesn't silently
  reset the sort; pager links keep them; Reset (a link to the bare route)
  clears filters and sort together.
- **Tests (14 new in the first build, 5 more for the Apiary/Hive columns, 19 total):** `ListSortTest` (11: default order unchanged; asc/desc;
  bogus, unsortable, array-valued and injection-shaped input; sort + filter;
  numeric order with NULLs and ties; next-direction/state per header; links
  keep filters and drop `page`; no sort prop on a list without sortable
  columns; hidden inputs in the filter form and Reset clearing them; paging
  keeps order and the pager links keep the sort; rendered `aria-sort`,
  links and sortable class) plus one label-sort test each in the API
  Client, AI Provider Config and Sensor Device filter tests. The 5 added:
  Apiary sorts by apiary name on Hives (asc/desc, ties in creation order);
  an orphaned record stays listed when sorting by apiary; Inventory Items,
  Purchases and Products sort by Apiary; the Apiary Action Log list; and
  Hive Action Logs by Hive. Mutation checks (cms2 copy only): stopping the
  query applying the sort failed 6 tests; replacing the relationship paths
  with plain fields failed 3 (including the orphan test, which is what shows
  the join is LEFT).
- **Verified on `cms2`** (`vdg`) through the real HTTP kernel as admin with
  throwaway fixtures: Hives sorted name ascending, descending with a status
  filter, and a `breed` sort correctly ignored; Inspections sorted by weight
  descending gave 100, 22.5, 10.5, 9.5 then the blanks, i.e. numerically.
  Then viewed the real rendered page with the module's own CSS in a browser
  (cms2's stylesheets are blocked in the pane): desktop header shows "Hive ▼"
  and "Status ⇅" as links, Apiary and Breed as plain text, the filter's
  hidden sort inputs present, header links keep `status=active`; the phone
  width shows the "Sort by" bar above the cards with no overflow. Fixtures,
  including their inspections, deleted and confirmed gone.
- **Not done**: no screen reader or accessibility-tree check of the
  `aria-sort` header (the browser pane was logged out; I did not sign in).
  Keyboard focus styling on the header links is the browser default plus an
  underline on `:focus-visible`.
- phpcs and phpstan clean; no baseline change.
- AGENTS.md: new paragraph after the filter-hook one.
- **Full suite: 1,193 tests / 19,750 assertions, 0 failures, 0 errors** for
  the first build (1,179 before this task + 14), run as seven
  separately-completed chunks. **The Apiary and Hive column additions came
  after that run and the full suite was not repeated.** Instead, after
  them: `ListSortTest` (16, all five new tests included), plus the other
  tests that render the changed lists — `ListFilterPaginationTest`,
  `FullListFilterTest`, `InventoryProductFilterTest`, `ActionLogFilterTest`,
  `ApiaryQueenFilterTest`, `ApiaryScopedAccessTest`, `RouteEntityAccessTest`
  and `ControllerCacheMetadataTest` (121 tests) — all green with 0 failures,
  and phpcs and phpstan clean. The delta is six one-line map entries.
- Also verified on `cms2` after the additions, through the real HTTP kernel
  with throwaway fixtures (deleted, children included, and confirmed gone):
  Inventory Items, Purchases and Products sorted by Apiary ascending
  (Barn before Zeta) and descending; Hive Action Logs by Hive both ways; the
  Apiary and Hive headers reported `aria-sort` correctly.

## Related
- Project:: [[collection-page-filter-coverage]] (done; follow-up)
- Tasks:: [[0126-unified-list-page-base]]
- Commits::
