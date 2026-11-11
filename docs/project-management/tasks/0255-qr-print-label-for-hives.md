# Task 0061: QR code label print for hives

## Problem (as-is)

The app supports scanning QR codes on hive labels. There is no way to generate or print a QR code label from the app. Users must create/print labels externally and rely on manual entry or other tools.

## Desired (to-be)

Add a "Print label" option in the Hive detail screen (or via the long-press menu) that generates a printable QR code label containing the hive's unique identifier. The QR payload should match whatever HiveLog expects for scanning (`/api/v1` discovery + hive ID, or just the raw hive ID with server URL prefix).

### Acceptance criteria

- [ ] "Print label" option available on the hive detail page (under a Settings / Actions section)
- [ ] Label preview shows QR code + hive name/ID in readable text
- [ ] Uses iOS `UIPrintInteractionController` for AirPrint support
- [ ] Label format matches HiveLog's expected scan format (coordinate with server-side scanning logic)

### Related ADRs

- N/A — new feature
