---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-27
supersedes:
---
# ADR-0104: Two-tier in-app navigation

## Status
accepted, 2026-09-27, at the user's request to restructure the nav strip
before it grows any flatter. This ADR is a design/planning artifact only
— no code changes yet; see [[in-app-navigation-restructuring]] for the
implementation tasks.

## Context
`HivelogAppNavBuilder::getAllItems()` (tasks 0119/0120) is a single flat
registry: core's own 8 built-ins plus one item each from
`collective`/`nanoprobe`/`nexus`'s `hook_hivelog_app_nav_items()`
implementations — 11 items today, rendered as one row of pills
(`HivelogAppNavBuilder::build()`) and mirrored 1:1 as main-menu children
of `hivelog.admin` (`HivelogMenuLinks` deriver). `group` (`records` /
`inventory` / `setup`) currently does nothing but order the pills and
insert a decorative divider — it has no structural effect. Every
`hivelog` entity type is scoped to an `Apiary` directly or transitively
(AGENTS.md's own "Content entities" section), so the flat list is
already implicitly hierarchical; the strip just doesn't say so. Adding
even one more entity type or submodule item makes the strip wider
without making it more navigable.

Two structural facts drive the design:
- **Every current item except Dashboard, Apiaries and the three
  `setup`-group items is a child of `Apiary` in the domain model**
  (Hives, Inspections, Queens, Queen Observations, Inventory Items,
  Inventory Purchases, Products) — the natural first-level parent for
  all of them is the existing "Apiaries" item, which already has a real
  page to anchor to.
- **The three `setup`-group items (API Clients, AI Provider Configs,
  Sensor Devices) have no apiary/hive ancestor at all**
  (`COLLECTION_THREADED_TYPES` in `HivelogEntityHierarchy` — they thread
  through their own collection link in the breadcrumb specifically
  because they have no such ancestor) **and no existing item to anchor
  under.** "Setup" needs a genuine new destination, not just a label.

## Decision

### Primary tier (unchanged in spirit, smaller in count)
Three primary items, ordered by the existing `group`
mechanism (`dashboard` / `records` / `setup` positions in
`GROUP_ORDER` — `inventory` no longer has a primary-tier member):
- **Dashboard** — unchanged; synthetic, not a registry item.
- **Apiaries** — unchanged destination; becomes the parent hub for
  every `records`/`inventory`-group secondary item.
- **Setup** *(new)* — a new route `hivelog.setup` at `/hivelog/setup`,
  rendered by a small `SetupController` that lists whichever
  `setup`-group children the current user can access (mirrors
  `DashboardController`'s own "render only what's accessible" shape).
  Access is a custom check — "is at least one `parent: setup` nav item
  accessible to this user" — computed from the same registry, **not** a
  hard-coded permission string, so `hivelog` core still depends on no
  submodule. If no submodule providing a `setup`-group item is
  installed, the item (and therefore the page) is simply inaccessible
  and doesn't appear, same as any other empty-registry state today.

No standalone "Insights" primary item is introduced — see Scope in
[[in-app-navigation-restructuring]] for why that's explicitly out of
scope here, not an oversight.

### Secondary tier
A new optional `parent` key on the existing nav item descriptor
(`hivelog.api.php`'s `hook_hivelog_app_nav_items()` contract, and
`HivelogAppNavBuilder::builtInItems()`): the key of the primary item
this one nests under. Omitting it (as every current primary item does)
keeps an item at the top level — fully backward compatible; a
third-party implementation that doesn't add `parent` still renders,
just at the top level as it does today.

Assignments:
- `parent: 'apiaries'` — hives, inspections, queens, queen_observations,
  inventory_items, inventory_purchases, products.
- `parent: 'setup'` — collective's `collective_api_clients`, nexus's
  `nexus_ai_provider_configs`, nanoprobe's `nanoprobe_sensor_devices`.

`group` keeps its current meaning **within** a hub's own children (the
existing `records`/`inventory` divider still separates Apiaries'
children into two visual clusters; `setup`'s children are all one
group, so no divider there) — only its role at the *primary* tier
changes, since there's now only one member per non-dashboard group.

### Rendering
No new SDC component and no JavaScript — the strip stays plain render
arrays (`#type => 'container'`/`'link'`), consistent with the module's
existing "functional over decorated" nav-strip precedent. `build()`
partitions accessible items into primary (no `parent`) and secondary
(grouped by `parent`), emits each primary item as a wrapper containing
its own link plus a nested list of its accessible children, and
cascades active-state: a secondary item's own section match still marks
just that item `is-active` (unchanged), and now additionally marks its
primary parent `has-active-child` so the current section stays visible
even while its dropdown is closed.

Desktop/tablet (> 768px, the existing small-tablet breakpoint):
child links are always present in the DOM and always focusable, shown
via `opacity`/`pointer-events` (never `display: none` or
`visibility: hidden`, both of which would remove them from the tab
order) toggled by `:hover`/`:focus-within` on the primary item's
wrapper — a hub with `has-active-child` also renders open by default.
Mobile (≤ 768px): the two-tier split is dropped entirely and every
accessible child renders always-visible, indented under its primary
parent — an always-open accordion, not hover-gated — because tapping a
real `<a href>` on a touchscreen navigates immediately with no
intermediate "reveal children" gesture, and `:hover`/`:focus-within`
never fire from a tap. This is a deliberate, accepted trade-off (see
Consequences), not a bug: every secondary destination stays reachable
from its own page's existing cross-links (e.g. "View all Queens")
regardless of nav-strip behaviour, so nothing becomes unreachable, only
less convenient to jump to directly on a phone.

### Main menu
`HivelogMenuLinks` mirrors the same two levels: a secondary item's
derived menu link parents under its own primary item's derived plugin
ID (e.g. `hivelog.nav_item:apiaries`) instead of flatly under
`hivelog.admin`; a primary item (no `parent`) keeps parenting directly
under `hivelog.admin`, exactly as every item does today. No plugin ID
changes, so — unlike `hivelog_update_10029`'s menu-link re-keying for
task 0119's static→derived switch — no update hook is needed here; a
site's existing per-link weight/enabled/expanded overrides in
`core.menu.static_menu_link_overrides` stay keyed to the same plugin
IDs and keep applying.

## Consequences
- Positive:
  - The primary tier stays a fixed size (3) regardless of how many
    entity types or submodules exist; a 4th submodule's `setup`-group
    item is invisible growth, not more pills.
  - `hivelog` core still depends on no submodule: the Setup page's
    access check and its list of children are both derived from the
    same registry every other nav consumer already reads.
  - No new JS surface, no new SDC component, no plugin ID churn, no
    update hook — the smallest change that gets a genuine two levels.
- Negative / trade-offs:
  - Every secondary destination is now two clicks away from the nav
    strip on desktop (open the hub, then the item) instead of one —
    accepted, since the alternative (an ever-widening flat strip) is
    worse for exactly the audience this restructuring is for.
  - Touch users get no hover-gated disclosure at all (see Rendering) —
    mitigated by the always-open mobile accordion, at the cost of a
    taller mobile strip than the desktop one.
  - `HivelogAppNavBuilderTest`/`HivelogMenuLinksTest`-equivalent
    coverage and the CSS both need a real rewrite, not an incremental
    patch — reflected as separate tasks rather than one large one.
- Follow-up tasks:
  [[0146-setup-landing-page-and-route]],
  [[0147-app-nav-registry-parent-key]],
  [[0148-two-tier-app-nav-strip-rendering]],
  [[0149-two-tier-app-nav-css-and-mobile-behaviour]],
  [[0150-two-tier-main-menu-links]],
  [[0151-two-tier-nav-tests-and-docs]].

## Open questions
- **Should "Setup" auto-redirect to its single child when only one
  submodule is installed**, instead of showing a one-link landing page?
  Left as a real page unconditionally — a redirect would make the
  breadcrumb and "you are here" state ambiguous (which page is the
  user actually on?), and a one-link landing page costs nothing extra
  to render.
- **Should the mobile accordion collapse per-hub (tap to open/close)
  once real usage shows the always-open list is too tall?** Deferred
  until it's an observed problem, not a speculative one — the module
  has no analytics and no reported complaint about strip height today.

## Amendment — 2026-10-02 (task 0166): accessibility of the disclosure

Recorded after the fact; the Decision above is unchanged.

**What the CSS-only dropdown now exposes.** The strip is a labelled
`role="navigation"` landmark ("HiveLog"). A hub's link carries an id, and
its submenu is a `role="group"` named by that link, so a screen reader
announces the section on entering it. Two CSS fixes came with it: a
transparent strip bridging the 2px gap between a hub link and its panel
(a pointer crossing the gap used to drop `:hover` and close the dropdown
before it could be reached — WCAG 1.4.13 "hoverable"), and no
transition under `prefers-reduced-motion`.

**What it deliberately does not claim.**
- `aria-haspopup="true"` / `role="menu"` / `role="menuitem"`: these promise
  the ARIA menu keyboard model (arrow-key roving, typeahead) that this
  disclosure doesn't implement. A hub is a real link that navigates, not a
  menu button. The W3C "disclosure navigation" pattern uses a button plus
  `aria-expanded`, which would mean splitting each hub into a link and a
  toggle button.
- `aria-expanded`: it would have to change with the dropdown's state, and
  `:hover`/`:focus-within` state can't be reflected into an attribute
  without script. A static value would be a lie.

**Known gap: no Escape-to-dismiss.** WCAG 1.4.13 asks that content shown on
hover or focus be dismissible without moving the pointer or focus. Closing
a `:hover`/`:focus-within` panel on Escape is not possible in CSS. Options:
(b) a progressive-enhancement script of roughly a dozen lines — on Escape,
add a class that hides the open panel until focus leaves, and return focus
to the hub link — which would amend this ADR's "no JavaScript" position and
needs a new library and a place to ship it; or (c) the full disclosure
pattern above. Not done: it is a change to a stated principle, so it is
left for an explicit decision. Mitigations today: tabbing out of the panel
closes it, the dropdown never covers the hub's own link, and on touch or
narrow screens it is always open, so nothing needs dismissing.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]],
  [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0119-single-source-navigation-registry]],
  [[0120-app-nav-active-state-and-grouping]],
  [[0146-setup-landing-page-and-route]],
  [[0147-app-nav-registry-parent-key]],
  [[0148-two-tier-app-nav-strip-rendering]],
  [[0149-two-tier-app-nav-css-and-mobile-behaviour]],
  [[0150-two-tier-main-menu-links]],
  [[0151-two-tier-nav-tests-and-docs]]
