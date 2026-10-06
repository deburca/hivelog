---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-05
branch: feature/0210-ios-app-hivelog-look-and-feel
release:
depends-on: ["[[0205-ios-app-browse-and-inspection-logging]]"]
blocked-by:
---
# Task: iOS app: the HiveLog / beeswax look and feel

## Context
Asked for after the app's screens existed: the iOS interface should take its look from the
HiveLog web application and the **beeswax** theme (ADR-0060) — colours, fonts, hexagonal
elements — so the app and the site read as one product. The app was plain system UI.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] Colours: the theme's palette, light and dark, following the phone's appearance setting
- [x] Fonts: the theme's three faces (Fraunces, IBM Plex Sans, IBM Plex Mono), bundled, with
      their licence, scaling with Dynamic Type
- [x] Hexagonal elements: the website's mark, hexagon badges, tick boxes and the queen's
      marking colour as hexagons, a faint honeycomb behind the welcome screens
- [x] Every screen restyled (welcome and sign-in, apiaries and map, hives, hive page,
      alerts and reporting, the forms, the outbox and the scanner)
- [x] App icon, accent colour and launch screen in the same style
- [x] Text stays readable: contrast checked in both modes
- [ ] Looked at on a physical phone, and at the largest text sizes *(a physical phone: yes,
      2026-10-06, found fine; the largest text sizes were checked on the simulator only)*

## Implementation notes
App-only; this repository's code does not change. The app repository's branch is
`feature/0210-brand-look`. How it is organised is in the app's README ("Look and feel").

- **Source of truth is the theme.** `Palette.swift` copies the `--bw-*` tokens from
  `beeswax/src/theme.css`; a test pins them. The hexagon is the exact path of the dashboard
  masthead's SVG (`DashboardController::title()`), also pinned by a test.
- **Fonts** are the website's own `.woff2` files, which iOS cannot load, so they were converted to
  TrueType, cut to fixed weights from the variable fonts (static files are more dependable on iOS than
  driving variation axes) and **renamed** `Vinculum Display / Sans / Mono`. Renaming is because the OFL
  lets a modified version not use a Reserved Font Name, and IBM Plex may reserve "Plex"; the copyright
  notices are kept and the OFL text ships beside the files (`OFL.txt`, written from the standard
  licence text; worth comparing with openfontlicense.org before the App Store build). Tooling was a
  throwaway virtualenv with fonttools and brotli. Latin only (about 180 KB for five files).
- **Components, not per-screen styling:** `BrandCard`, `SectionHeading`, `Chip`, `BrandButtonStyle`,
  `HexBadge`, `HexCheck`, `HexMark`, `EmptyState`, `NavRow`, `Notice`. The system's navigation bar, tab
  bar and bar buttons take the fonts through `BrandAppearance.apply()`.
- **Dark mode** uses the theme's `.dark` tokens (mint-green accent on a dark brown ground).
- The icon and launch mark are drawn by an opt-in test from the same hexagon code
  (`VINCULUM_RENDER_ASSETS=1 swift test --filter AssetRender`).

## Found while doing it
- **Two of the website theme's own colour pairs are a little under the 4.5:1 that small text needs:**
  the amber on its pale tint in light mode (4.03:1) and the red on its tint in dark mode (4.43:1).
  The app uses slightly adjusted text colours of the same hue for chips. The website's attention chips
  and warning text use the same pairs; worth fixing in `beeswax` too (a follow-up for that theme, not
  done). The theme's "faint" ink is also too pale for text (about 3:1); the app uses the muted ink
  wherever the website uses faint for something that matters.
- Headings that come from the server are in the site's language (Danish on the dev site) while the
  app's own words and the choice labels are English; unchanged by this task.

## Verification
- 197 app tests pass on the Mac (195 on an iOS simulator; the two icon-rendering tests are Mac-only):
  new tests pin the palette to the theme, check readable contrast for 16 text/background pairs in
  both modes, check the hexagon against the website's SVG, and check that the five fonts register and
  contain the Danish letters.
- Run on the iOS 27 simulator, signed in to the kbg dev site, in light and dark: welcome, server
  found, apiaries (with the masthead), an apiary's due actions and hives, a hive page (queen
  hexagon, cards), the inspection form (chips, hexagon tick boxes, boxed inputs), the Alerts tab, the
  report sheet, the scanner screen (its no-camera state) and the sign-out dialog. The icon was rendered
  and looked at.

## Not verified
- A physical iPhone and the largest accessibility text sizes.
- Screens I did not open in the new style: the outbox screen and its banners (restyled, not looked at),
  an inspection's detail page, the map, the empty and error states, the live camera scanner, and the launch
  screen as the first frame.
- The app icon is a first design: the website's mark in paper on pine over a faint comb. It has no
  dark or tinted variant.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
