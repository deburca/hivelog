---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: routing
created: 2026-09-20
completed: 2026-09-21
branch: feature/0091-insight-agent-management-ui
release:
depends-on: ["[[0089-insight-agent-and-hive-insight-entities]]", "[[0090-insight-agent-api-endpoints]]"]
blocked-by: ["[[0090-insight-agent-api-endpoints]]"]
---
# Task: `InsightAgent` provisioning UI + `ai_insights_enabled` consent toggle

## Context
Without this, nobody can actually turn the feature on or get a
credential to build the external agent against — mirrors
[[0078-sensor-device-configuration-descriptor]]'s role for
`SensorDevice`. `administer hivelog`-gated, since an `InsightAgent`
token is a single, high-trust, site-wide credential
([[0088-ai-insights-implementation]] Consequences flags this explicitly:
a leaked token exposes every opted-in apiary's context at once).

**Lives in `collective`**, per
[[0098-nanoprobe-collective-locutus-submodule-split]]. Unlike
[[0078-sensor-device-configuration-descriptor]] (a minimal custom
canonical page, since `SensorDevice` had no genuine "manage many
records" need), this task explicitly asks for the **full standard
entity add/edit/delete/collection UI** (`HivelogListBuilder` pattern) —
a real, if deliberately different, shape from `SensorDevice`'s, and
implemented as such.

## Acceptance criteria
- [x] Standard entity add/edit/delete UI for `InsightAgent`
      (`InsightAgentListBuilder`, `InsightAgentForm`,
      `InsightAgentDeleteForm`, routed via `entity.insight_agent.*`) —
      `administer hivelog` only for `add_form` (no "add insight agent"
      permission exists at all, per
      [[0089-insight-agent-and-hive-insight-entities]]); the standard
      own/any pattern for view/edit/delete.
- [x] Token shown once, immediately after creation or regeneration, via
      a status message with a clear "will not be shown again" notice
      (`InsightAgentForm::save()` on create;
      `InsightAgentRegenerateTokenForm::submitForm()` on regenerate).
- [x] "Regenerate token" action (`InsightAgentRegenerateTokenForm`):
      invalidates the previous token immediately — implemented as a real
      POST form (CSRF-protected), not a GET link, since it mutates the
      token, mirroring
      `\Drupal\nanoprobe\Form\SensorDeviceConfigDownloadForm`'s own
      documented reasoning for the same CSRF concern.
- [x] The `InsightAgent` canonical page (`InsightAgentController`)
      states both endpoint URLs plainly (also echoed on the edit form
      itself, for whoever's editing without visiting the canonical page
      first).
- [x] `Apiary.ai_insights_enabled` exposed on the apiary add/edit form
      (already `setDisplayConfigurable('form', TRUE)` since
      [[0089-insight-agent-and-hive-insight-entities]]), defaulting
      `FALSE`. A disclosure of exactly what will be sent (in plain
      language, not a tooltip) is injected via `hook_form_alter()` —
      **no core change needed for this**, since `hook_form_alter()` is a
      standard Drupal extension point every module already has, unlike
      the Sensors-panel/dashboard-alert hooks
      [[0080-hive-apiary-sensors-panel]]/
      [[0081-sensor-needs-attention-alerts]] had to define fresh.
- [x] Kernel tests (`InsightAgentManagementUiTest`, 5 tests): token shown
      once via a status message and not recoverable in plaintext after a
      fresh load; regenerating invalidates the old token — verified by
      an actual follow-up call to
      [[0090-insight-agent-api-endpoints]]'s context-read endpoint with
      the stale token, asserting `401`, not just that a new plaintext
      differs from the old one; `ai_insights_enabled` toggle persists
      through the real `ApiaryForm`; the disclosure text appears on both
      the apiary add and edit forms.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **The disclosure is always visible, not JS-revealed only on toggle**:
  task 0091's "shows... before the toggle takes effect" could read as
  requiring interactive, toggle-triggered UI — but kernel tests (the
  only test type this acceptance criteria calls for) can't exercise
  real client-side interactivity. A disclosure block always rendered
  next to the field serves the actual intent (an informed decision
  before enabling) just as well, is honestly testable at the kernel
  level, and avoids inventing JS machinery nothing else in this
  submodule needs yet. Documented as a deliberate interpretation, not
  an oversight.
- **`hook_form_alter()` targets `apiary_add_form`/`apiary_edit_form`
  directly** (`ApiaryForm`'s default, unoverridden `getFormId()`
  pattern) rather than reusing the ADR-0099 hook mechanism — that
  mechanism was built for canonical-page panels and a dashboard queue,
  a different shape of extension point; `hook_form_alter` already solves
  this one natively, so no new pattern was invented for it.
- **A real access-check gap caught while writing tests**: the first
  version of `InsightAgentManagementUiTest::
  testRegenerateInvalidatesPreviousToken()` failed with an
  `AccessDeniedHttpException` — `InsightAgentRegenerateTokenForm::
  buildForm()`'s own `access('update')` check (added for real security,
  not incidentally) correctly rejected the test's default,
  unauthorized current user. Fixed by creating and setting an admin
  user in `setUp()` — a genuine reminder that a form's own internal
  access check needs an authorized actor in tests, exactly like a route
  would in production, not just entity-level CRUD working in isolation.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0087-ai-insights-hosting-and-privacy-model]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0019-authorisation-model]]
- Commits::
