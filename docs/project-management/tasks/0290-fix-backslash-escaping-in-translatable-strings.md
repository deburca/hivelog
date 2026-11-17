# Task: Fix backslash escaping in translatable strings (task 0290)

## Context
`phpcs` reports ~25 warnings across the codebase for backslash escaping inside single-quoted `t()`/`$this->t()` calls. In PHP, `\` inside `'...'` is a literal backslash — Drupal's string translation may interpret it incorrectly or pass it through as-is in some environments. The fix is to use double quotes `"..."` (which support escape sequences) instead of single quotes for translatable strings containing `\`.

## Warning details (file:line → description)

### Core (src/)
| File | Line(s) | Current code | Fix |
|------|---------|-------------|-----|
| `src/Form/CalendarCopyForm.php` | 64 | `'...apiary\'s.'` | Use double quotes |
| `src/Form/HiveMoveForm.php` | 77 | `'...'hive\'s queens...'` | Use double quotes |
| `src/Form/HarvestYieldFormTrait.php` | 140 | `'...calendar action\'s expected harvest'` | Use double quotes |
| `src/Form/InventoryUsageFormTrait.php` | 74 | needs inspection | likely same pattern |
| `src/Form/ApiaryForm.php` | 98 | needs inspection | likely same pattern |
| `src/Entity/InventoryPurchase.php` | 180 | needs inspection | likely same pattern |
| `src/Entity/InventoryItem.php` | 468, 493 | needs inspection | likely same pattern |
| `src/Entity/Apiary.php` | 437 | needs inspection | likely same pattern |
| `src/Entity/ApiaryMembership.php` | 303 | needs inspection | likely same pattern |
| `src/Entity/HarvestYield.php` | 284, 348 | needs inspection | likely same pattern |
| `src/Entity/CalendarActionProductYield.php` | 143 | needs inspection | likely same pattern |
| `src/Entity/InventoryUsage.php` | 127 | needs inspection | likely same pattern |
| `src/Entity/HiveInspection.php` | 138, 157, 176 | needs inspection | likely same pattern |
| `src/Entity/CalendarActionItemRequirement.php` | 142 | needs inspection | likely same pattern |

### Submodules
| File | Line(s) | Fix |
|------|---------|-----|
| `modules/collective/collective.module` | 54 | Use double quotes |
| `modules/collective/src/Form/ApiClientRegenerateTokenForm.php` | 48 | Use double quotes |
| `modules/collective/src/Entity/ApiClient.php` | 179 | Use double quotes |
| `modules/nanoprobe/src/Form/SensorDeviceConfigDownloadForm.php` | 61 | Use double quotes |
| `modules/nanoprobe/src/Entity/SensorDevice.php` | 394 | Use double quotes |
| `modules/nanoprobe/src/Controller/SensorDeviceController.php` | 103 | Use double quotes |
| `modules/assimilate/assimilate.install` | 77 | Use double quotes |

## Acceptance criteria
- [ ] All ~25 instances fixed (converted `\` inside `'...'` to equivalent `"..."`)
- [ ] `phpcs` reports 0 "Avoid backslash escaping" warnings
- [ ] No functional changes — only quote character change in translatable strings

## Implementation notes
- The fix is always: change `'text with \'escape\''` to `"text with \\"escape\\""` or better `"text with 'literal quotes'"` (no escape needed for literal single quotes in double-quoted strings).
- Where the string contains no other escape sequences, prefer removing the backslash entirely: `'apiary\'s'` → `"apiary's"`.
- Each change is a one-liner; verify `php -l` after each file.
