# Block configuration

Configure the following fields when placing or editing the Copyright Footer
block.

| Field | Purpose |
| --- | --- |
| Organization name | The organization shown in the notice. |
| Organization URL | An optional link for the organization name. |
| Year origin from | An optional four-digit first year. |
| Year to date | An optional four-digit last year. Leave empty to use the current year. |
| Version | An optional version prefixed with `ver.`. |
| Version URL | An optional link for the version. It is ignored when Version is empty. |
| Display "All Rights Reserved." | Select whether to append the phrase after the organization name, after the version, or not at all. |
| Custom copyright format | An optional plain-text token format. Leave empty for the translated default output. |

## Custom format tokens

The **Custom copyright format** field accepts these tokens:

| Token | Output |
| --- | --- |
| `[copyright]` | The copyright symbol `©`. |
| `[year]` | The single year or year range used by the default output. |
| `[start-year]` | The configured start year, or the current year when empty. |
| `[end-year]` | The configured end year, or the current year when empty. |
| `[organization-name]` | The organization name, linked when Organization URL is set. |
| `[version]` | The version with its `ver.` prefix, linked when Version URL is set. |

For example, `[copyright] [year] [organization-name] [version]` follows the
default structure.

The format is plain text: HTML is escaped and unsupported bracketed tokens are
rejected when the block configuration is saved.
