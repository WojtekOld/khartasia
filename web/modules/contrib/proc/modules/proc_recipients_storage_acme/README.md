# Proc Recipients Storage Acme

A realistic — but fictional — implementation of the Protected Content (proc)
**Recipient storage** plugin type. Where `proc_recipients_storage_example` is a
bare skeleton, this module shows a working plugin that resolves recipients from
a group membership and uses timestamps to filter out cryptographically
impossible recipients.

## The fictional scenario

There is an "eligibility group". Being a member of that group is what makes a
user a potential recipient of content encrypted through a proc field bound to
this plugin. The group has three members:

| User ID | Joined the group |
| ------- | ---------------- |
| 1       | 2022-01-01       |
| 2       | 2024-01-01       |
| 3       | 2026-01-01       |

This membership (user ID → join timestamp) is hardcoded in
`src/AcmeGroupMembership.php`. In a real application it would come from a group
module, a directory, an internal service, or a remote endpoint.

## What the module provides

1. **A fetcher endpoint** at `/proc_recipients_storage_acme/recipients` that
   returns the current, unfiltered eligibility list as JSON: `[1, 2, 3]`.
2. **A `RecipientStorage` plugin** (`AcmeRecipientStorage`) that recomputes a
   cipher's recipients from the group membership instead of storing them in the
   database, scoping them to the cipher's encryption time.

The module does **not** wire itself to any field. A site builder enables it and
then, on a proc field, sets **Endpoint for fetching user IDs of recipients** to
`/proc_recipients_storage_acme/recipients` and **Recipients storage plugin** to
*"Acme: group membership scoped by encryption time"*.

## How recipients are resolved (and why timestamps matter)

Recipients are **never stored per cipher**. Every time the system asks "who are
the recipients of this cipher?", the plugin's `getRecipients()`:

1. Reads the cipher's cryptographic timestamp from its meta
   (`meta[0]['generation_timestamp']`).
2. Returns the group members whose join timestamp is **at or before** that
   encryption timestamp.

Members who joined the group **after** the content was encrypted are excluded.
They were not eligible when the content was encrypted, so the content was never
encrypted for them — they are cryptographically impossible recipients for that
particular cipher, and reporting them as recipients would be misleading.

Example: a cipher encrypted on 2025-06-01 resolves to recipients `{1, 2}`. User
3 (who joins on 2026-01-01) is filtered out for that cipher, but would be
included for any cipher encrypted on or after their join date.

Because recipients are recomputed, `setRecipients()` deliberately keeps the
`field_recipients_set` database field empty so that no per-recipient rows are
ever written.

## Wished recipients

The wished (target) recipient set — the set used to drive re-encryption — is
intentionally left as **standard, database-backed storage** on
`field_wished_recipients_set`. `getWishedRecipients()` and
`setWishedRecipients()` behave exactly like the standard plugin.

The flow is: the proc field widget calls the fetcher endpoint, learns the
current eligibility list `[1, 2, 3]`, and writes it as the cipher's wished
recipients; proc then drives re-encryption from that wished set as usual.

## Trade-offs

Same as any recipient storage plugin that does not persist recipients per
cipher:

- The application must always be able to reproduce the exact recipient set a
  cipher was encrypted for. Here that is guaranteed by never mutating a member's
  join timestamp and only ever adding members with a later timestamp.
- Reverse lookups backed by the database table — the
  `proc:get-procs-user-is-recipient` (`gpur`) drush command and the recipient
  columns in the shipped Views — will not see ciphers governed by this plugin.
