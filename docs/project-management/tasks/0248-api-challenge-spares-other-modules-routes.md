---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[hivelog-visual-identity]]"
area: api
created: 2026-10-30
branch: fix/api-challenge-spares-other-routes
release: 4.1.1
depends-on: ["[[0246-path-prefix-setting-and-api-at-api-v1]]"]
blocked-by:
---
# Task: The API's 401 must not catch other modules' routes under /api/v1

## Context
On hivelog.eu the login's FriendlyCaptcha (endpoint "local") failed with "Verification failed": its own puzzle route is `/api/v1/puzzle`, and `HivelogApiChallengeSubscriber` answered every anonymous request under the prefix with `401 "Sign in to use the HiveLog API."`, so nobody could log in. A regression since 4.0.0 moved the API to `/api/v1`.

## Acceptance criteria
- [x] The challenge applies only to routes named `hivelog_api.*` or `jsonapi.*`
- [x] Unit test: an anonymous request to `friendlycaptcha.api_puzzle` is not challenged
- [ ] CI green on `main`, then release 4.1.1
- [ ] hvg updated (`composer update`, `hvg-pull`) and the login checked

## Notes
Workaround until released: set the captcha's API endpoint to EU (`/admin/config/people/captcha/friendlycaptcha`).
