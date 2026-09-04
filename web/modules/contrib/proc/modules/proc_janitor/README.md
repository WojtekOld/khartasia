# PROC Janitor (proc_janitor) Module

## Contents

- [Introduction](#introduction)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running Maintenance](#running-maintenance)
- [Warning](#warning)

## Introduction

`proc_janitor` is a `proc` submodule for cleaning up orphan protected-content
cipher entities.

PROC Janitor helps keep your site clean by:

- Removing unreferenced `proc` entities of type `cipher`.
- Optionally keeping recent orphans by age.
- Cleaning related keyring update-job references for removed entities.
- Purging old `proc_reporting` data (update task snapshots and daily operations) beyond a configurable retention period.

## Installation

Enable the module as usual:

```bash
drush en proc_janitor -y
drush cr
```

## Configuration

Go to:

- **Administration > Configuration > System > Protected Content > Janitor**
- Route: `/admin/config/system/proc/janitor`

Settings:

- **Run maintenance task during cron**
  - Enables automatic cleanup when cron runs.
- **Minimum age for orphan cipher entities (seconds)**
  - Only orphan ciphers older than this value will be removed.
- **Maximum age for orphan cipher entities (seconds)**
  - Only orphan ciphers up to this age will be removed.
  - Set `0` to disable the maximal age restriction.
- **Maximum batch operations**
  - Used for manual GUI runs; controls how the manual job is split into chunks.
- **Maximum orphan entities per manual batch run**
  - Caps how many orphan entities are processed when cleanup is launched from the GUI.
  - Set `0` for no manual batch cap.
- **Cron cleanup lock timeout (seconds)**
  - Safety timeout to avoid overlapping cleanup runs if a previous one is still
    active.
- **Maximum orphan entities per cron run**
  - Upper limit of orphan cipher entities processed in one cron execution.
  - Use this to keep memory/CPU consumption bounded on large datasets.
  - The same limit is also used for each storage delete chunk during cron cleanup.
- **Maximum age for reporting data (days)**
  - Data older than this number of days in the `proc_reporting` tables (Update Tasks Monitoring and Daily Operations) will be purged during maintenance.
  - Set `0` to disable reporting data cleanup.
  - Only effective when the `proc_reporting` submodule is enabled.
  - Default: 30 days.

## Running Maintenance

### Automatic

Enable **Run maintenance task during cron** and keep cron running normally.

Run cron manually if needed:

```bash
drush cron
```

### Manual (GUI batch)

You can run maintenance from:

- **Administration > Configuration > System > Protected Content > Janitor > Run janitor maintenance task**
- Route: `/admin/config/system/proc/janitor/run-maintenance`

## Warning

There are scenarios where it is completely acceptable that a proc cipher entity exists independently of a host content.
This is always the case of standalone encryption. However, if your project does not use standalone encryption, you might
want to enable this module and take advantage of automated identification and removal of unreferenced encrypted content.
