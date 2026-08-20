---
type: project
tags: [hivelog/project]
status: active
target:
created: 2026-08-20
---
# Project: Honey/wax/propolis yield and potential income

## Goal
Let beekeepers record what a harvest actually produced (honey, wax,
propolis, or anything else worth selling) and see a potential income
figure for it, so the per-apiary cost report started by
[[inventory-tracking-and-depreciation]] can finally show cost vs. income
vs. net for a year — the "more profitable as years go by" premise that
project's ADR explicitly deferred. See
[[0034-honey-wax-propolis-yield-and-potential-income]] (accepted) for the
full data model and architecture.

## Scope
- In scope:
  - `Product` (apiary-scoped catalog): name, unit, `expected_unit_price`
    (a single mutable current-best-guess price), status.
  - `CalendarActionProductYield` (the "recipe"): links a `CalendarAction`
    to one or more products with an estimated quantity — per-hive for
    hive-scoped actions, per-apiary for apiary-scoped ones, mirroring
    `CalendarActionItemRequirement` exactly.
  - `HarvestYield` (the "actual"): links a `HiveActionLog`/
    `ApiaryActionLog` "done" report to the product(s) and quantity
    actually produced, with a price snapshot taken at creation — mirrors
    `InventoryUsage`.
  - UI: catalog CRUD pages (per apiary, embedded on the apiary page like
    `InventoryItem`); yield-recipe management embedded on the
    `CalendarAction` canonical/edit page; yield pre-fill (from the
    recipe, editable) wired into the `HiveActionLog`/`ApiaryActionLog`
    "done" report flow, alongside the existing inventory-usage fields on
    the same form; the existing per-apiary, per-year cost report
    (`InventoryReportController`) gains a potential-income figure and a
    net (income − cost) figure.
  - Potential income is always computed from the yield ledger, never
    stored as a running total — same "derived fact, not duplicated
    state" discipline as stock-on-hand and depreciation.
- Out of scope (for now):
  - Recording jars/consumables used during harvest — **already fully
    solved** by the existing `CalendarActionItemRequirement`/
    `InventoryUsage` mechanism from [[inventory-tracking-and-depreciation]].
    Nothing in this project touches that path; a beekeeper adds a
    requirement row for jars against a harvest action exactly as they
    would for sugar against a feeding action.
  - An actual sales ledger (real transactions: buyer, date, actual price
    realized). This project produces a *potential* income figure from a
    single mutable expected-price assumption, confirmed explicitly by the
    user — not an audited actual-sales record. A future ADR would be
    needed for that.
  - True profitability accounting (e.g. matching a specific harvest's
    cost of goods to the specific units of it actually sold, partial
    inventory of unsold honey, etc.) — potential income and cost are each
    reported as an annual total, not reconciled against each other beyond
    a simple net figure.
  - Unit conversion — one unit per product, chosen consistently by the
    beekeeper, same trade-off as `InventoryItem.unit`.
  - Any change to how jars/consumables are recorded or reported — see
    first bullet above.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [[0035-product-catalog-entity-and-ui]] — backlog
- [[0036-calendar-action-product-yield-and-recipe-ui]] — backlog
- [[0037-harvest-yield-and-action-log-reporting-integration]] — backlog
- [[0038-potential-income-in-the-cost-report]] — backlog
- [[0039-yield-and-income-test-coverage]] — backlog

Suggested build order matches the numbering, and mirrors
[[inventory-tracking-and-depreciation]]'s own sequence exactly (it is, in
shape, the same five-task build: catalog entity/UI, recipe, actual/log
integration, report, test backstop) — see that project's own note for why
this shape was chosen. The catalog (`Product`) comes first since nothing
else can be built without it; the recipe next since it needs `Product` to
reference; the log (`HarvestYield`) next since its report-form pre-fill
needs the recipe; the cost-report extension last of the four feature tasks
since it needs real `HarvestYield` rows to sum; test coverage threaded
through each task's own acceptance criteria, with the final task as a
backstop for cross-task integration gaps only — not the only place tests
get written, matching [[0033-inventory-test-coverage]]'s own framing.

## Open questions
- Should `Product.expected_unit_price` changes be logged/audited in any
  way, or is silently overwriting the current assumption acceptable given
  it's explicitly not a sales ledger? Leaning toward "acceptable" — no
  request for a price history, and adding one would blur the line with
  the explicitly out-of-scope real sales ledger — but worth confirming
  once the entity exists.
- Should the cost report's new "net" figure be signed (income can be
  negative if cost exceeds it) or should a net loss just read as "0" with
  cost/income still shown separately? Leaning toward signed — hiding a
  loss would be actively misleading for a profitability-oriented report —
  but not yet confirmed.
- Should `HarvestYield`/`CalendarActionProductYield` reuse the exact
  `InventoryUsageFormTrait`/`InventoryUsage` pattern via a literal parallel
  trait (`HarvestYieldFormTrait`), or could the two be unified into one
  more general "linked-quantity" trait parameterised by entity type? ADR-
  0034 chose the literal-mirror approach (matching how `HiveActionLog`/
  `ApiaryActionLog` are parallel siblings rather than a shared polymorphic
  base) for consistency with the rest of the module; revisit only if the
  duplication actually causes maintenance pain in practice.

## Related decisions
- [[0034-honey-wax-propolis-yield-and-potential-income]] (accepted — core
  architecture for this project)
- [[0027-inventory-tracking-and-depreciation]] (the plan/log pattern this
  project mirrors a third time, and the existing cost report this project
  extends)
- [[0025-seasonal-calendar-and-hive-action-tracking]] (the original
  plan/log pattern, and the `CalendarAction`/`HiveActionLog`/
  `ApiaryActionLog` entities this project attaches to)
- [[0003-code-defined-entity-schema]] (baseFieldDefinitions + update hooks
  — every new entity here needs a paired `hivelog_update_NNNN` hook)
- [[0019-authorisation-model]] / [[0020-access-parity-custom-routes]] /
  [[0021-field-level-access]] (permission and access-control parity —
  `Product` needs the same apiary-membership-scoped access model as
  `InventoryItem`, via `ApiaryAccessTrait`)
