---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0208-ios-app-qr-hive-label-scanning
release:
depends-on: ["[[0205-ios-app-browse-and-inspection-logging]]", "[[0177-hive-quick-access-page]]"]
blocked-by:
---
# Task: iOS app: scan hive QR labels

## Context
[[qr-hive-labels]] points labels at the web quick-access page
(`/hivelog/hive/{hive}/quick`, [[0177-hive-quick-access-page]]). The app
should open the same label straight to its own hive screen.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] In-app scanner (VisionKit `DataScannerViewController`) recognises a
      label URL for the connected server and opens that hive.
      **Built; the camera path has not been run** (the simulator has no camera). The
      same lookup, behind a typed or pasted address, was run end to end.
- [x] A label from a different server, or a hive the user can't access,
      gives a clear message
- [x] Optional: universal links so the system camera opens the app — needs
      an `apple-app-site-association` file per site; record whether it's
      worth it for self-hosted installs. **Recorded below: not worth it for v1.**
- [x] Label URL format unchanged (web scanning keeps working)

## Implementation notes

### Backend (this repo)
None. This task changes nothing here, and does not depend on the hive quick-access page
or the label sheet existing: neither [[0177-hive-quick-access-page]] nor
[[0178-printable-qr-label-sheet]] has been built yet (both are `backlog`), so **no label
exists to scan today**. The app reads the address those tasks are specified to print
(`<site>/hivelog/hive/<id>/quick`, per 0177/0178) and also accepts the hive's own page
address. If 0177/0178 settle on a different shape, only `HiveLabel.parse` changes.

### App (`~/Development/vinculum`, branch `feature/0208-qr-hive-labels`)
- **`HiveLabel.parse(_:for:)`** reads an address for the server the app is signed in to:
  the hive number from `/hivelog/hive/<n>/quick` (or `/hivelog/hive/<n>`). The host, the port
  and any folder the site lives in must match the connected server (so a longer host that
  merely starts the same, or the connected host as a folder of another host, is *another
  server*, not this one); `http` against `https` does not matter, and a written default port
  is the same port. A label for another site names that site; anything else is "not a hive
  label".
- **Lookup:** `GET hive/hive?filter[drupal_internal__id]=<n>`. The number in a label is the
  hive's id on the website, which the API only exposes as that attribute; the filter works
  because of 0205's filter fix. A hive that is someone else's looks exactly like one that does
  not exist (the server never says which), and the message says both.
- **Scanner:** a **Scan** button at the top of the apiaries list opens VisionKit's
  `DataScannerViewController` (QR only), which pauses while a result is looked up or a message
  is showing. If the device has no scanner (a simulator, an old device) it says so and the typed
  or pasted address, always on screen, still works. On success it lands on the hive's page with
  the apiary list behind it.
- The camera permission text now mentions scanning labels.

### Universal links: not worth it for v1
A universal link (so the phone's *system* camera opens the app) needs the site to serve
`/.well-known/apple-app-site-association` listing the app's team and bundle ID, and the app to
carry the site's domain in its Associated Domains entitlement, **fixed at build time**. HiveLog
is self-hosted: every site has its own domain, and an App Store binary cannot list domains it
does not know. The options are an entitlement per site (a separate build for each, no) or a
wildcard (`*.` something, which only works for sites on one parent domain, such as a hosted
service). So universal links would work only for a hosted offering under one domain, not for
self-hosted installs. Until there is such a thing, labels are scanned *in the app* (or with the
camera app, which opens the website's quick page in the browser, as printed labels do today).
Revisit if a hosted HiveLog appears.

### Verification
- 184 app tests pass on the Mac and on an iOS 27 simulator. The parser has tests for: a label,
  the hive page, trailing slash and whitespace, a query or fragment, `http`/`https`, host case,
  a different host, a lookalike host (`hives.example.org.evil.example`), the connected host as a
  path segment of another host, a different port, a written default port, a site in a folder,
  fourteen things that are not labels, another site's non-label page, and a huge number.
- **Run on the simulator against the kbg dev site**, by pasting addresses into the fallback
  field: a label for another site names it ("This label belongs to other.example.com, but the
  app is connected to kragebaekgaard.ddev.site…"); a hive that is not the person's gives
  "Couldn't find that hive…"; the person's own hive's label opens its page.

### Not verified
- **The camera.** `DataScannerViewController` was not run: the simulator has no camera and
  reports it unsupported. Whether a printed label is recognised at a hive, in sunlight, at
  arm's length, needs a real phone. The pause-while-looking-up logic is untested too.
- A label that is itself a *short* or redirecting URL (some label printers do that) is read as
  written, not followed.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
