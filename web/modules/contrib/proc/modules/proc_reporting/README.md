## INTRODUCTION
The Proc Reporting module adds monitoring reports for the Protected Content
(`proc`) ecosystem.

It provides two admin reports:
- Hourly snapshots of pending re-encryption tasks per user.
- Daily counters per user for:
  - Encrypt operations.
  - Cipher access/download operations.
  - Re-encrypt operations.

The module is designed as a `proc` submodule and should be enabled only on
projects where operational monitoring is required.

## INSTALLATION
Install as any regular Drupal module.

Typical commands:
```bash
drush en proc_reporting -y
drush cr
```

## PERMISSIONS
The module defines one permission:
- `view proc reporting`

This permission is marked as restricted and should be granted to trusted admin
roles only.

## WHAT IS TRACKED
The module stores two datasets:

1) **Pending update tasks snapshots (hourly):**
- User ID.
- User name.
- Number of pending update tasks at snapshot time.
- Snapshot generation timestamp.

2) **Daily operation counters (per user, per day):**
- Date.
- User ID.
- User name.
- Encrypt count.
- Cipher access/download count.
- Re-encrypt count.

### Tracking semantics
- **Encrypt:** counted when a new `proc` entity of type `cipher` is created.
- **Re-encrypt:** counted when a `cipher` entity is updated and its cipher file
  reference changes.
- **Cipher access/download:** counted server-side when cipher text payload is
  returned by Proc JSON API (`/api/proc/getcipher/{cipher_id}`).

The counter for cipher access/download is incremented by the number of cipher
items returned in the API response.

## CRON BEHAVIOUR
The module runs an hourly snapshot through `hook_cron()`.

To avoid excessive writes, snapshot generation is throttled to at most once per
hour using Drupal state (`proc_reporting.last_snapshot_run`).

If needed, trigger cron manually:
```bash
drush cron
```

## ADMIN REPORTS
After enabling the module and granting permission, reports are available under
**Administration > Reports**:

- `Protected Content: Update Tasks Monitoring`
  - Route: `/admin/reports/proc-reporting/update-tasks`
  - Shows hourly snapshots with pagination.

- `Protected Content: Daily Operations`
  - Route: `/admin/reports/proc-reporting/daily-operations`
  - Shows per-day counters with pagination.
  - Includes filters for date and user ID.

## STORAGE TABLES
The module creates the following database tables:
- `proc_reporting_update_tasks_snapshot`
- `proc_reporting_daily_operations`

These tables are created by `proc_reporting.install` through `hook_schema()`.

## UNINSTALL NOTES
If the module is uninstalled, Drupal schema uninstallation removes its custom
reporting tables and their data.

## TROUBLESHOOTING
If reports remain empty:
1. Confirm `proc_reporting` is enabled.
2. Confirm users have generated relevant activity (encrypt/update/access).
3. Run cron manually and refresh reports.
4. Verify the viewing role has `view proc reporting` permission.

