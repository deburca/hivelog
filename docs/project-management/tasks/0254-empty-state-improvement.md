---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-10-30
branch: feat/empty-state-improvement
release: 4.2.0
depends-on: ["[[0252-sensor-charts-improved]]"]
blocked-by: []
---
# Task: Empty states — guide, not just inform

## Context
HiveLog's empty states are functional but passive. They tell the user "nothing matches your
filters" or "no data here" without helping them understand what to do next or why it matters.
For a beekeeping tool, these moments are critical: a first-time beekeeper seeing an empty
dashboard should feel invited in, not abandoned. An apiary with no hives should know the next
step. A sensor panel with no devices is just blank space.

### Current empty states across surfaces

| Surface | Current state | Problem |
|---|---|---|
| Dashboard (no apiaries) | `.hivelog-dashboard__welcome` card: "You have no visible apiaries." + link to create one. | Functional but generic. No context for *why* creating an apiary matters. |
| Apiary list (empty) | Plain text, no CTA. | Missing "Add your first apiary" button entirely on some implementations. |
| Hive list (per apiary) | "No hives found." — same as generic entity-list empty message. | No guidance; doesn't acknowledge this is a new apiary vs. filtered view. |
| Queen list (per hive) | Same generic empty message. | |
| Inspection list (per hive) | "No inspections match your filters" or plain empty. | Does not distinguish between "none exist" and "all filtered out". See task 0251. |
| Sensor panel (nanoprobe) | No sensor devices: blank panel area, no indication that sensors are optional. | Missed opportunity to explain what sensors do and how to add them. |
| Calendar Actions (empty) | The page is just the heading + "no rows match". | A new apiary opens here before any actions exist. |
| Financial report (no data) | Table with header only, no body rows. | Beekeeper sees a table frame and nothing else — confusing. Does this mean I have no inventory or that there's an error? |

## Desired configuration

### Principles for empty states in HiveLog
1. **Empty is an invitation to act.** Every empty state has a single primary CTA that takes the
   user to the relevant creation form. No secondary CTAs, no explanatory text unless it helps
   someone understand *what* they're creating.
2. **Name what's missing in plain language.** Not "No hives found" but "This apiary has no hives yet."
   The beekeeper knows they didn't find anything; tell them why (it's new) and what to do next.
3. **Explain value when the surface is optional.** Sensors, financial reports, calendar actions —
   these are add-ons. An empty panel for an optional feature should say what it offers and link
   to setup/creation.
4. **Keep tone conversational, not apologetic or overly friendly.** No "Oops!" or emojis. The
   message is a sentence that names the situation and points at an action.

### Specific states

#### Dashboard — no visible apiaries (current: `.hivelog-dashboard__welcome`)
**Current state:** The card already has a CTA and value explanation:
```
Welcome to HiveLog
Start by adding an apiary — a location where you keep hives. Creating one seeds a 31-entry seasonal calendar...
[Add your first apiary]
```
The message is functional but a bit long for the intro text (two sentences). Tighten it:
```
Welcome to HiveLog
Add an apiary to get started — a location where you keep hives. You'll set up its calendar, then add hives, queens and inspections.
[Add your first apiary →]
```

- The CTA links to `entity.apiary.add_form`.
- "Set up your first apiary" is H2 (a page-level heading).
- One sentence of value explanation, not a paragraph. Beekeepers know what an apiary is;
  just name the connection: it holds hives, calendars, and records.

#### Apiary list — no apiaries at all
**Before:** (text only, varies by implementation)

**After:** A centered card in the same style as the dashboard welcome but with tighter margins:
```
Add your first apiary
This is where you'll manage hives, seasonal calendars, and inventory.
[Create an apiary →]
```

#### Apiary page — no hives
**Before:** Generic empty table row or message.

**After:** A card at the top of the "Hives" section:
```
No hives yet — this apiary is empty.
Add your first hive to start recording inspections and tracking inventory.
[Add a hive →]
```

- Placed in the same section where the list would go (not floating above other sections).
- Uses the `hivelog:stat-tile` component structure for visual consistency with the stat tiles below.

#### Hive page — no queen assigned
**Before:** Detail table shows empty queen field; no indicator of status.

**After:** A small warning banner inside the "Queen Status" section (not a severity banner, just
a muted warning chip):
```
No queen recorded
Hives without an active queen need attention during inspections.
[Record a queen →]
```

#### Sensor panel — no devices
**Before:** Blank panel area in the hive page.

**After:** A card explaining the sensor system:
```
Sensor devices
Connect weight, temperature, or humidity sensors to your hives for automatic
monitoring. Readings feed directly into the inspection view and AI insights.
[Add a sensor device →]
```

- Only shown on the hive page's sensor panel (not the apiary-level panel).
- Links to `hivelog.sensor_device.add` scoped to this hive.

#### Calendar Actions — no actions configured
**Before:** Heading + "No rows match your filters."

**After:** A card at the top of the calendar list:
```
Set up your seasonal calendar
This is where you'll track varroa treatments, honey harvests, and other
recurring duties for every hive in this apiary.
[Use a starter calendar →]
```

- Links to `hivelog.apiary.calendar_copy` with the default source pre-selected.
- The link text "Use a starter calendar" is clearer than "Copy a calendar" for a first-time user.

#### Financial report — no data
**Before:** Empty table header, no body.

**After:** A single row in the summary table:
```
No records yet
Inventory purchases, product yields, and hive component costs appear here as you record them.
[View inventory items →]  [View products →]
```

- Two CTAs because "no data" could mean no items OR no transactions — pointing at both helps.
- The row spans the full table width with muted text ("No records yet" as a heading).

## Implementation notes
- Key files: 
  - `src/Controller/DashboardController.php` (welcome card)
  - `src/Entity/ApiaryListBuilder.php` (collection empty state)
  - `src/Controller/ApiaryController.php` (hive list empty)
  - `src/Controller/HiveController.php` (queen, inspection, sensor panel empty)
  - `components/stat-tile/stat-tile.twig` (reused for some cards)
  - Template overrides where a custom layout is needed beyond the stat tile structure

- Empty-state cards use the same padding/border as `.hivelog-stat-tile` but without the link:
  `.hivelog-empty-state` class with modifier classes for size and context.

- No JavaScript; all CTAs are direct links to the scoped add route or configuration form.

## Acceptance criteria
- [ ] Dashboard welcome card rewritten with value explanation + single CTA
- [ ] Apiary collection page: empty state has "Create an apiary" CTA centered in a card
- [ ] Apiary page (no hives): card in the Hives section with "Add a hive →" link
- [ ] Hive page (no queen): muted warning banner in Queen Status section
- [ ] Sensor panel (no devices): explanatory card with "Add a sensor device →" link
- [ ] Calendar Actions (empty): card with "Use a starter calendar →" link
- [ ] Financial report (no data): row spanning the table with two CTAs
- [ ] All cards styled consistently (same padding, border, typography as stat tiles)
- [ ] Tone review: plain language, no filler, direct CTA text
- [ ] Visual review at 390px, 768px, 1280px

## Related
- Project:: [[hive-log-frontend-polish]]
- Follows:: [[0252-sensor-charts-improved]]
- See also:: [[0251-inspection-list-grouping]] (empty states for filtered vs. genuinely empty)
