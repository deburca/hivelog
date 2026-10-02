---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: navigation
created: 2026-10-02
branch: feature/0166-nav-dropdown-accessibility
release:
depends-on:
blocked-by:
---
# Task: Accessibility attributes and Escape-to-close for the nav dropdown

## Context
Follow-up from the 2026-10-02 review of [[in-app-navigation-restructuring]]
(done). The nav strip's hover-gated dropdown has no `aria-haspopup` or
`aria-expanded` on its hub links (nothing in `HivelogAppNavBuilder` or its
template emits either), and a keyboard user who tabs into a submenu cannot
dismiss it with Escape — it stays open for as long as focus is inside it.
Task [[0128-page-heading-hierarchy]] committed the module to WCAG 2.2
(SC 1.3.1 / 2.4.6) for heading structure, but the nav strip was added later
and wasn't held to the same standard. A CSS-only disclosure cannot toggle
`aria-expanded` truthfully, so the fix needs a deliberate choice about how
to describe the state without JavaScript.

## Acceptance criteria
- [x] Decide and record the approach: (a) mark hub links with
      `aria-haspopup="true"` and describe the submenu with
      `aria-labelledby` while leaving `aria-expanded` off (it can't be kept
      truthful in CSS-only), or (b) allow a minimal progressive-enhancement
      script for `aria-expanded` and Escape handling. If (b), it needs an
      amendment to ADR-0104's "no JavaScript" position.
- [x] Hub links and their submenus carry the agreed ARIA attributes; the
      nav landmark is labelled.
- [x] Escape closes an open submenu and returns focus to its hub link, or the
      task records why that isn't achievable under option (a). *(Recorded as
      not achievable — see notes; this is a known gap, not a delivered
      feature.)*
- [x] `aria-current` behaviour (task 0120) is unchanged.
- [x] Kernel test asserts the attributes in the rendered nav array.
- [ ] Checked with a screen reader or the browser accessibility tree
      (`read_page`) on `cms2`. *(NOT done as written — see notes: checked the
      real rendered markup on `cms2` and the gap behaviour in a browser, but
      not with a screen reader or the accessibility tree.)*
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/HivelogAppNavBuilder.php` (render array),
  `css/hivelog.app-nav.css`, any nav template.
- Coordinate with [[0165-nav-submenu-on-touch-devices-above-768px]] — both
  touch the same CSS and render array.
- No schema change → no update hook.

## Implementation notes (as built)
- **Approach: option (a), but not exactly as the task wrote it.** The task
  proposed `aria-haspopup="true"` on hub links. I did **not** add it:
  `aria-haspopup="true"` means the popup is an ARIA `menu`, so assistive
  tech announces a menu and users expect arrow-key roving and typeahead,
  which this disclosure doesn't implement. A hub here is a real link that
  navigates, not a menu button. `aria-expanded` is left off too, because
  `:hover`/`:focus-within` state can't be mirrored into an attribute without
  script, and a static value would be false. The reasoning is recorded as an
  amendment to ADR-0104.
- **What was added** (`src/HivelogAppNavBuilder.php`): the strip is a
  `role="navigation"` landmark labelled "HiveLog" (translatable, left lazy
  so a cached build doesn't freeze one language); a hub's link gets an id
  `hivelog-app-nav-<key>`; its submenu is a `role="group"` with
  `aria-labelledby` pointing at that id. Items without children get neither.
  Decorative separators were already `aria-hidden` and still are.
- **Finding: the strip was not a landmark at all** — a bare `div`, so a
  screen reader user had no way to jump to it or skip it. This is the most
  useful fix here; the group labelling is secondary.
- **Two CSS fixes found along the way** (`css/hivelog.app-nav.css`):
  - *Hover gap.* The panel sits 2px below its hub link and that gap belonged
    to neither, so a pointer crossing it dropped `:hover` and closed the
    dropdown before it could be reached (WCAG 1.4.13 "hoverable"). Added an
    invisible `::before` strip on the panel bridging it; it inherits the
    closed panel's `pointer-events: none`, so it can't open the dropdown
    itself, and it is turned off in the always-open layout, where a
    statically positioned panel would anchor it somewhere distant.
  - `prefers-reduced-motion: reduce` now disables the panel's transition.
- **Known gap, not fixed: no Escape-to-dismiss** (WCAG 1.4.13
  "dismissible"). Not possible in CSS. Closing on Escape needs a small
  script (roughly a dozen lines), which would amend ADR-0104's "no
  JavaScript" position — a decision I left for you rather than made. The
  ADR amendment lays out the options (b: that script; c: the full
  link-plus-toggle-button disclosure pattern). Mitigations: tabbing out of
  the panel closes it, the panel never covers its own hub link, and on touch
  or narrow screens it is always open.
- **Tests (6 new):** landmark role and label; hub submenu is a group
  labelled by its hub link, rendered to HTML so the `id`/`aria-labelledby`
  pair and the id's uniqueness are checked as they reach the browser; an
  item without children gets no id or group; separators stay `aria-hidden`;
  a guard that the rendered strip contains no `aria-haspopup`,
  `aria-expanded`, `role="menu"` or `role="menuitem"` (so adding any later is
  a deliberate, visible change); and the Insights hub's group in nanoprobe's
  `AppNavItemsTest`.
- **Verification, and its limits:**
  - On `cms2` (`vdg`), rendering `/hivelog`, `/hivelog/hives` and
    `/hivelog/sensor-devices` through the HTTP kernel as admin: the landmark
    is present, both hub ids exist and are each the target of their group's
    `aria-labelledby`, and the nav region has no `aria-haspopup` /
    `aria-expanded` (the only hits on the page are the site's admin toolbar,
    far above the strip).
  - Hover gap, in a browser on a prototype page with the real stylesheets,
    logging real mouse-move targets: with the bridge, a pointer in the gap
    lands on the submenu and it stays open; with the bridge rule disabled,
    the same pointer lands on the nav strip and the dropdown closes. (An
    earlier reading that showed it staying open without the bridge was a
    stale `:hover` state, not a result — I re-ran it with move logging
    before trusting either.)
  - **Not done:** no screen reader, and no accessibility-tree read of the
    live page (the browser pane was logged out and I did not sign in), so the
    announcements are inferred from the markup, not heard.
- phpcs clean; phpstan clean (no baseline change).
- AGENTS.md's nav paragraph updated. It also said the dropdown was "opened
  unconditionally when `has-active-child`", which the CSS header documents
  as a bug fixed in task 0149 — corrected, and the `hover: none` rule from
  task 0165 added.
- **Full suite: 1,179 tests / 19,530 assertions, 0 failures, 0 errors**
  (1,173 before this task + the 6 new), only the usual third-party
  deprecations and notices. Run as seven separately-completed chunks (six
  balanced non-Functional chunks of 172–288 tests, then Functional). The
  task was committed in `review` before the run finished and flipped to
  `done` in a follow-up commit. Status is `done` with two items open, both
  recorded above: Escape-to-dismiss (a decision on ADR-0104, not
  implemented) and the unticked screen-reader / accessibility-tree check.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0128-page-heading-hierarchy]], [[0165-nav-submenu-on-touch-devices-above-768px]]
- Commits::
