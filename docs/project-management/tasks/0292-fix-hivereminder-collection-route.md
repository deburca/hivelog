---
type: task
tags: [hivelog/task, bug]
status: done
priority: medium
project:
area: code-quality
created: 2026-11-17
branch:
release: "4.4.1"
depends-on:
blocked-by:
---
# Task 0292: Fix HiveReminder collection route — missing handleList()

## Summary

`entity.hive_reminder.collection` in `hivelog.routing.yml` declares an invalid `_controller`:
```yaml
    _controller: '\Drupal\hivelog\HiveReminderListBuilder::handleList'
```

No such method exists on `HiveReminderListBuilder`. The correct pattern used by all other entity collection routes is `_entity_list: 'hive_reminder'`, which automatically invokes the list builder from the entity type definition.

This causes CI to fail during `drush en hivelog -y`:
```
Method Drupal\hivelog\HiveReminderListBuilder::handleList() does not exist
```

**Root cause:** Task 0274 implemented HiveReminder with a custom `_controller` route instead of using the standard `_entity_list` convention. The local dev environment (which may use a slightly older or patched Drupal core) didn't enforce this, but the CI runner's Drupal version catches it as a hard error during module enablement.

## What to do

1. **Fix `hivelog.routing.yml`:** Change the HiveReminder collection route from:
   ```yaml
   defaults:
     _controller: '\Drupal\hivelog\HiveReminderListBuilder::handleList'
   ```
   to:
   ```yaml
   defaults:
     _entity_list: 'hive_reminder'
   ```

2. **Verify locally** that `ddev drush en hivelog -y` still works after the fix (it should — this is how every other entity collection route already works).

3. **Push and check CI** — the shard 1 install step should no longer abort during module enablement.

## Files to touch
- `hivelog.routing.yml` — one line change on the `entity.hive_reminder.collection` route
