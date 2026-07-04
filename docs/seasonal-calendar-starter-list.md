# HiveLog Seasonal Calendar — Starter List

A working, human-editable copy of the default starter calendar seeded onto
every new apiary. Extracted from `CalendarAction::DEFAULT_STARTER_CALENDAR`
in `src/Entity/CalendarAction.php` (the actual PHP constant is the source
of truth the module uses at runtime — this file is a copy for review and
iteration, not an automatically-synced export).

- **Weeks** are ISO-8601 week numbers (1–53), not calendar dates — see
  [[0025-seasonal-calendar-and-hive-action-tracking]] for why.
- Assumes a **Northern Hemisphere, generic temperate climate**. Southern
  Hemisphere apiaries should shift every entry by roughly half a year
  (~26 weeks). This is a known limitation, not a bug.
- These are illustrative starting points — every beekeeper's actual timing
  depends on local climate, forage, and colony condition. Adjust freely.
- To make edits here take effect for **newly created apiaries**, the
  changes need to be ported back into the
  `CalendarAction::DEFAULT_STARTER_CALENDAR` PHP constant. Existing
  apiaries are never affected either way — seeding only runs once, on
  apiary creation (see `Apiary::postSave()`), and every seeded row is a
  normal, independently editable `CalendarAction` afterwards.

## Summary

| Weeks | Title | Category |
|---|---|---|
| 1–3 | Midwinter Cluster Check | Winter Preparation |
| 2–4 | Renew Central Beehive Registration (CBR) | Other |
| 4–6 | Order & Prepare Equipment for the Season | Other |
| 5–7 | Fondant / Emergency Feed Check | Feeding |
| 7–9 | Apiary Site & Hygiene Check | Other |
| 9–11 | Spring Inspection & Clean-up | Spring Buildup |
| 10–12 | Assess Winter Losses & Consolidate Colonies | Other |
| 10–13 | Begin Spring Stimulative Feeding | Feeding |
| 14–16 | Varroa Treatment (Spring) | Varroa Treatment |
| 14–16 | Equalise Colonies & Reverse Brood Boxes | Spring Buildup |
| 15–17 | Add Supers Ahead of the Flow | Spring Buildup |
| 15–17 | Set Bait Hives / Swarm Traps | Swarm Prevention |
| 19–22 | Swarm Prevention Check | Swarm Prevention |
| 19–22 | Queen Rearing / Raise Replacement Queens | Requeening |
| 20–22 | Split Strong Colonies / Make Increase | Swarm Prevention |
| 24–26 | Requeening Window (Introduce New Queens) | Requeening |
| 25–27 | Harvest Spring Honey | Harvest (Spring) |
| 28–30 | Mid-Season Health & Brood Disease Check | Other |
| 29–31 | Monitor Honey Stores & Add Supers | Harvest (Summer) |
| 31–33 | Harvest Summer Honey | Harvest (Summer) |
| 33–35 | Varroa Treatment (Late Summer) | Varroa Treatment |
| 33–35 | Requeen Failing Colonies | Requeening |
| 36–38 | Combine Weak Colonies | Other |
| 37–39 | Autumn Feeding | Feeding |
| 38–40 | Final Varroa Check & Treatment Follow-up | Varroa Treatment |
| 40–42 | Mouse Guards & Woodpecker Protection | Winter Preparation |
| 41–43 | Winter Preparation | Winter Preparation |
| 42–44 | Final Stores Weight Check | Winter Preparation |
| 44–46 | Ventilation & Moisture Check | Winter Preparation |
| 45–48 | Midwinter Varroa Treatment (Broodless Period) | Varroa Treatment |
| 49–51 | Apiary Record-Keeping & Season Review | Other |

**31 entries total.** By category: Varroa Treatment (4), Winter Preparation
(5), Other (7), Spring Buildup (3), Swarm Prevention (3), Requeening (3),
Feeding (3), Harvest — Spring/Summer (3).

## Detail

### Midwinter Cluster Check
- **Weeks:** 1–3
- **Category:** Winter Preparation

Brief external check on a mild day — do not open the hive.
- Heft to check stores.
- Clear dead bees from the entrance.
- Listen/knock check for cluster activity.

### Renew Central Beehive Registration (CBR)
- **Weeks:** 2–4
- **Category:** Other

Registration/renewal is typically due early in the year.
- Confirm your apiary and hive details are up to date.
- Renew via the Central Beehive Registration (CBR) system if applicable in your jurisdiction.
- Update your recorded hive count for this apiary.

### Order & Prepare Equipment for the Season
- **Weeks:** 4–6
- **Category:** Other

Get ahead of the season while workload is still low.
- Order new frames, foundation and woodware needed for the coming year.
- Clean and repair supers, floors and roofs stored over winter.
- Sterilise old equipment (blowtorch or washing soda solution) before reuse.

### Fondant / Emergency Feed Check
- **Weeks:** 5–7
- **Category:** Feeding

Check stores are holding out through late winter.
- Heft each hive; top up fondant if it feels light.
- On a mild, still day, briefly check for signs of isolation starvation without fully opening the hive.
- Replace any fondant that has crystallised or dried out.

### Apiary Site & Hygiene Check
- **Weeks:** 7–9
- **Category:** Other

General site maintenance ahead of the first full inspection.
- Check hive stands, straps and roofs after winter weather.
- Clear vegetation away from hive entrances.
- Check for woodpecker or other winter damage to woodware.

### Spring Inspection & Clean-up
- **Weeks:** 9–11
- **Category:** Spring Buildup

First full inspection once temperatures are reliably above 10°C.
- Check winter survival and queen status.
- Remove mouse guards/entrance reducers.
- Scrape frames and clean floors.

### Assess Winter Losses & Consolidate Colonies
- **Weeks:** 10–12
- **Category:** Other

Take stock of the apiary after winter.
- Record which colonies did not survive, and note a likely cause where possible.
- Consolidate weak survivors onto fewer frames to help them keep warm.
- Reduce entrances on any small nucleus colonies.

### Begin Spring Stimulative Feeding
- **Weeks:** 10–13
- **Category:** Feeding

Support early brood rearing if stores or forage are short.
- Feed thin sugar syrup (roughly 1:1) to stimulate brood rearing.
- Only feed if stores are genuinely low or forage is scarce — avoid overfeeding a colony that has enough.
- Stop stimulative feeding once a natural nectar flow begins.

### Varroa Treatment (Spring)
- **Weeks:** 14–16
- **Category:** Varroa Treatment

Assess and treat mite load before the main flow.
- Perform a mite wash or sugar roll count.
- Treat if above threshold, following label instructions.
- Remove supers before treating if required by the product.

### Equalise Colonies & Reverse Brood Boxes
- **Weeks:** 14–16
- **Category:** Spring Buildup

Even out colony strength as spring buildup accelerates.
- Move frames of brood or stores from very strong colonies to weaker ones if needed.
- Reverse brood boxes if the queen has moved fully into the top box, to free up laying space below.

### Add Supers Ahead of the Flow
- **Weeks:** 15–17
- **Category:** Spring Buildup

Keep ahead of colony growth so bees never feel congested.
- Add a queen excluder and first super once the brood box holds 7-8 frames of bees.
- Add further supers ahead of need, not behind it — congestion drives swarming.

### Set Bait Hives / Swarm Traps
- **Weeks:** 15–17
- **Category:** Swarm Prevention

Prepare to capture swarms before they establish elsewhere.
- Position one or more bait hives (with old comb or lemongrass oil as an attractant) near the apiary.
- Check bait hives weekly through the swarm season.

### Swarm Prevention Check
- **Weeks:** 19–22
- **Category:** Swarm Prevention

Check weekly for queen cells during peak swarm season.
- Look for swarm cells on the bottom of frames.
- Add supers/space ahead of need.
- Consider an artificial swarm if cells are found.

### Queen Rearing / Raise Replacement Queens
- **Weeks:** 19–22
- **Category:** Requeening

Raise your own replacement and spare queens while drones are plentiful.
- Graft larvae, or select cells, from your best-performing colonies.
- Run queen-rearing nucs to mate the resulting virgin queens.
- Mark and record new queens as they emerge and begin laying.

### Split Strong Colonies / Make Increase
- **Weeks:** 20–22
- **Category:** Swarm Prevention

Turn swarm pressure into planned increase rather than lost swarms.
- Make artificial swarms or nucleus splits from colonies already showing swarm preparations.
- A controlled split now is far preferable to losing an uncontrolled swarm later.

### Requeening Window (Introduce New Queens)
- **Weeks:** 24–26
- **Category:** Requeening

Introduce queens raised earlier in the season.
- Introduce mated queens from spring queen rearing using a suitable introduction cage.
- Requeen any colony that is unusually defensive or shows a poor/spotty laying pattern.

### Harvest Spring Honey
- **Weeks:** 25–27
- **Category:** Harvest (Spring)

Harvest capped spring honey supers.
- Confirm at least 80% of the frame is capped before pulling.
- Leave adequate stores for the colony.
- Extract promptly and return wet supers for cleaning.

### Mid-Season Health & Brood Disease Check
- **Weeks:** 28–30
- **Category:** Other

A dedicated disease-focused check between the two harvests.
- Check the brood pattern closely for signs of AFB, EFB, chalkbrood or sacbrood.
- Report any suspected notifiable disease to your local authority/bee inspectorate promptly.

### Monitor Honey Stores & Add Supers
- **Weeks:** 29–31
- **Category:** Harvest (Summer)

Stay ahead of the summer flow.
- Add supers proactively so the colony never runs short of storage space.
- Check queen excluders are not clogged with wax or propolis, restricting bee movement.

### Harvest Summer Honey
- **Weeks:** 31–33
- **Category:** Harvest (Summer)

Harvest capped summer honey supers.
- Confirm at least 80% of the frame is capped before pulling.
- Leave adequate stores for the colony.
- Extract promptly and return wet supers for cleaning.

### Varroa Treatment (Late Summer)
- **Weeks:** 33–35
- **Category:** Varroa Treatment

Treat again after the summer harvest and before winter bees are raised.
- Perform a mite wash or sugar roll count.
- Treat if above threshold, following label instructions.

### Requeen Failing Colonies
- **Weeks:** 33–35
- **Category:** Requeening

Late-summer requeening gives a new queen time to establish before winter.
- Requeen any colony with a failing, drone-laying, or otherwise underperforming queen.
- Source locally-adapted stock where possible for better winter survival.

### Combine Weak Colonies
- **Weeks:** 36–38
- **Category:** Other

Consolidate ahead of winter rather than overwintering colonies unlikely to survive.
- Unite any colony too weak to survive winter on its own, using the newspaper method.
- Never overwinter a colony you don't genuinely expect to make it through — combine or accept the loss now.

### Autumn Feeding
- **Weeks:** 37–39
- **Category:** Feeding

Check and top up winter stores.
- Heft or weigh each hive.
- Feed sugar syrup or fondant as needed to reach target winter weight.

### Final Varroa Check & Treatment Follow-up
- **Weeks:** 38–40
- **Category:** Varroa Treatment

Confirm the late-summer treatment worked before winter bees are fully raised.
- Recheck mite drop after the late-summer treatment.
- Apply a follow-up treatment if levels are still high.

### Mouse Guards & Woodpecker Protection
- **Weeks:** 40–42
- **Category:** Winter Preparation

Protect hives from common winter pests/predators.
- Fit mouse guards to every hive entrance.
- Fit woodpecker netting or wrap hives in areas where green woodpeckers are a known problem.

### Winter Preparation
- **Weeks:** 41–43
- **Category:** Winter Preparation

Prepare colonies for winter.
- Reduce entrances and fit mouse guards.
- Check final stores weight.
- Insulate/wrap hives if appropriate for your climate.

### Final Stores Weight Check
- **Weeks:** 42–44
- **Category:** Winter Preparation

Confirm every colony has enough to make it through winter.
- Heft or weigh each hive — a full-size colony typically needs a substantial stores reserve to see it through winter (roughly 18-25kg, depending on climate).
- Feed fondant immediately to any hive that feels light.

### Ventilation & Moisture Check
- **Weeks:** 44–46
- **Category:** Winter Preparation

Damp kills colonies faster than cold.
- Check hives have adequate ventilation to prevent condensation dripping onto the winter cluster.
- Tilt hives slightly forward if needed so any condensation drains away from the entrance.

### Midwinter Varroa Treatment (Broodless Period)
- **Weeks:** 45–48
- **Category:** Varroa Treatment

The broodless period gives the most effective single mite treatment of the year.
- Apply an oxalic-acid-based (or equivalent) treatment once the colony is broodless, for maximum effectiveness.
- Choose a cold, dry, still day and treat quickly to minimise heat loss from the cluster.

### Apiary Record-Keeping & Season Review
- **Weeks:** 49–51
- **Category:** Other

Close out the season's records while it's fresh in mind.
- Update hive, queen and inspection records for the year.
- Review which colonies to requeen, split, combine or replace next season.
- Note what worked and what didn't for next year's plan.

## Open questions (carried over from project notes)

- Should these week numbers/wording be reviewed by someone with real
  seasonal beekeeping experience before release? All 31 entries pass
  entity validation and are internally consistent, but the specific
  timing is still illustrative rather than locally verified.
- Should there be a Southern Hemisphere variant (same list, shifted
  ~26 weeks) offered as an alternative starter set?
- Are there gaps worth adding — e.g. a dedicated varroa monitoring-only
  entry distinct from treatment, heather/ivy flow handling for regions
  that have one, or a spring dead-out equipment-reuse checklist?
