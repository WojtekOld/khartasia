# Copyright Footer

A Copyright Footer module provides a block for a Copyright © footer.
You can configure the start year, organization, and version, and the current
year is filled in automatically.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/copyright_footer).

Submit bug reports and feature suggestions, or track changes in the
[work items](https://git.drupalcode.org/project/copyright_footer/-/work_items)
and
[merge requests](https://git.drupalcode.org/project/copyright_footer/-/merge_requests).


## Table of contents

- Requirements
- Installation
- Configuration
- Documentation
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.
It supports Drupal 11 and 12.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

Composer:

```bash
composer require drupal/copyright_footer
```


## Configuration

The module has no standalone configuration page. Configure it when placing the
block at `/admin/structure/block`.

1. Organization name
2. Organization URL (Leave blank if not necessary.)
3. Year origin from (Leave blank if not necessary. Use a 4-digit year.)
4. Year to date (Leave blank and the current year is shown automatically. Use a
   4-digit year.)
5. Version (Leave blank if not necessary.)
6. Version URL (Leave blank if not necessary. It works with the version number
   above. If you do not input the version number, this field is ignored.)
7. Display "All Rights Reserved." after the organization name, after the
   version, or not at all
8. Custom copyright format (Leave blank to keep the default translated output.)


### Custom format tokens

The optional Custom copyright format accepts these tokens:

- `[copyright]`: The copyright symbol `©`.
- `[year]`: The same single year or year range used by the default output.
- `[start-year]`: The configured start year, or the current year when empty.
- `[end-year]`: The configured end year, or the current year when empty.
- `[organization-name]`: The organization name, linked when its URL is set.
- `[version]`: The version with its `ver.` prefix, linked when its URL is set.

For example, `[copyright] [year] [organization-name] [version]` follows the
default structure.

The format is plain text. HTML is escaped, and unsupported bracketed tokens are
rejected when the block configuration is saved.


## Documentation

The version-controlled documentation covers installation, block configuration,
and custom format tokens.

- [Repository documentation](docs/index.md)
- [GitLab Pages documentation](https://project.pages.drupalcode.org/copyright_footer/4.x/)


## Maintainers

- Yas Naoi ([yas](https://www.drupal.org/u/yas))
