---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0204-ios-app-setup-and-sign-in
release:
depends-on: ["[[0201-hivelog-api-submodule-scaffold]]"]
blocked-by:
---
# Task: iOS app: project setup, server connect and sign-in

## Context
First app task. The app lives in its own repository
([[0107-mobile-client-api-and-native-ios-app]] §3); this note tracks it
from the HiveLog vault so the project's Dataview table stays complete.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] New repo created; SwiftUI app, minimum iOS version decided and noted
- [ ] "Connect to your HiveLog server" screen: URL entry, discovery check
      that the site has the API submodule enabled, clear error otherwise
- [ ] OAuth auth-code + PKCE sign-in (`ASWebAuthenticationSession`), tokens
      in the Keychain, refresh handled, sign-out revokes
- [ ] Multiple servers or a single one — decided and noted
- [ ] CI for the app repo (build + unit tests)

## Implementation notes
- Generated API client vs. hand-written models: decide based on how the
  JSON:API payloads look after 0201.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
