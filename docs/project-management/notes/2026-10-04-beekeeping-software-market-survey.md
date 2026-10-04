---
type: notes
tags: [hivelog/notes, research]
created: 2026-10-04
---

# 2026-10-04 — Beekeeping software market survey

A survey of 8 beehive logging and management products, comparing the features
in their published product documentation with HiveLog 2.4.0. The 8 proposals at
the end were all accepted the same day and each became a project.

## Products surveyed

| # | Product | Kind | Primary source |
|---|---|---|---|
| 1 | HiveTracks | Record keeping; web and mobile; free | hivetracks.com, independent reviews |
| 2 | Apiary Book | Record keeping; EU; freemium (€80/yr) | apiarybook.com, FAO AgriTech listing |
| 3 | ApiManager | Records and finances; cross-platform | apimanager.net |
| 4 | Bee Squared (from the makers of BeePlus) | Records; iOS | App Store listing |
| 5 | BeeKeepPal | Records, finances, inventory | beekeeppal.com |
| 6 | Pocket Hive | Records with AI and voice; new entrant | App Store listing |
| 7 | BroodMinder | Hive scales and sensors with an app | broodminder.com, reseller spec pages |
| 8 | BeeHero | Commercial sensors and AI | beehero.io |

**Caveats:** most of the evidence is vendor marketing pages and app-store
listings, not full user manuals. BroodMinder's own site didn't list its app
features, so its specs come from resellers. HiveTracks' homepage now focuses on
biodiversity, so its app features come from reviews.

## Feature matrix

✅ = documented · ◐ = partial · — = not documented

| Feature | HiveTracks | Apiary Book | ApiManager | Bee Squared | BeeKeepPal | Pocket Hive | BroodMinder | BeeHero | HiveLog 2.4 |
|---|---|---|---|---|---|---|---|---|---|
| Apiary → hive → inspection records | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ◐ | ◐ | ✅ |
| Queen tracking | ✅ | ✅ | ✅ | — | ✅ | — | — | — | ✅ |
| Inspections at frame or box level | — | — | — | ✅ | — | — | — | — | — |
| Tasks, reminders, seasonal plan | ✅ | ✅ | ✅ | — | ✅ | — | — | ✅ | ◐ calendar, no reminders sent |
| Treatment and veterinary record | ◐ | ✅ | ◐ | — | ◐ | — | — | ✅ | ◐ |
| Splits, swarms, colony moves | — | ✅ | — | — | ✅ | — | ◐ | ✅ | — |
| Harvest and yields | — | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | ✅ |
| Finances and sales | — | — | ✅ | ✅ | ✅ | ✅ | — | — | ◐ no sales ledger |
| Equipment and inventory | — | ✅ | — | ✅ | ✅ | — | — | — | ✅ |
| Weather and when to inspect | ✅ | ✅ | — | — | ✅ | — | — | — | — |
| QR / NFC hive labels | — | ✅ | — | ✅ | — | ✅ | — | — | — |
| Voice or hands-free logging | — | ✅ | — | — | — | ✅ | — | — | — |
| Offline field use | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | — | — |
| CSV / PDF export | — | ✅ | ✅ | — | ◐ | — | ✅ | — | — |
| Team access with roles | — | — | ✅ | — | ✅ | — | — | ✅ | ◐ |
| Sensors: weight, temperature, humidity | — | — | — | — | — | — | ✅ | ✅ | ✅ nanoprobe |
| AI insights and alerts | — | ◐ | — | — | ◐ | ✅ | ◐ | ✅ | ✅ nexus |
| Open-hive, swarm or robbing alerts | — | — | — | — | — | — | ✅ | ✅ | — |
| Honey batch provenance QR | — | — | — | — | — | ✅ | — | — | — |

**What this shows:** HiveLog is already ahead of most of these apps on
inventory, depreciation, the seasonal calendar, sensors and AI. It falls behind
on things you do at the hive and on getting data out: export, QR labels, weather
planning, offline use, colony lifecycle events and shared access.

## Accepted proposals → projects

| ID | Project |
|---|---|
| A | [[data-export-and-printable-records]] |
| B | [[qr-hive-labels]] |
| C | [[weather-aware-inspection-planning]] |
| D | [[treatment-register]] |
| E | [[colony-lifecycle-events]] |
| F | [[shared-apiaries-and-team-roles]] |
| G | [[honey-sales-and-batch-provenance]] |
| H | [[sensor-event-detection]] |

Recommended order: B → A → D → H, then C, E, G, F.

**Not proposed:**
- **Offline mode and voice logging.** The app is server-rendered Drupal, so
  either would need a large PWA project. Raise it as a separate investigation
  if wanted.
- **Frame-level inspections.** Only one product documents it, so the evidence
  is thin.

## Sources
- https://www.guideflow.com/blog/beekeeping-software
- https://www.hivetracks.com/
- https://www.beekeepingfornewbies.com/hive-tracks-app-review/
- https://www.apiarybook.com/
- https://agritechobservatory.review.fao.org/fr/apiary-book
- https://www.apimanager.net/
- https://www.similarweb.com/app/apple/1585158898 (Bee Squared)
- https://www.beekeeppal.com/
- https://mwm.ai/apps/pocket-hive-beekeeping-log/6757815980
- https://apps.apple.com/app/id6769492453 (Honey Chain)
- https://broodminder.com/
- https://www.perfectbee.com/store/accessories-and-tools/monitors-and-scales/broodminder-weight-scale
- https://www.beehero.io/beekeeping-solutions
- https://www.thepacker.com/news/sustainability/beehero-debuts-beekeeping-task-manager-tool-apiary-management
- https://beekeepclub.com/apiary-management-software-comparison/
