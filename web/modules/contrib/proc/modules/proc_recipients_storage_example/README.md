# Proc Recipients Storage Example

This is the skeleton example for the Protected Content (proc)
**Recipient storage** plugin type. It shows how an implementing application can
take over the storage and retrieval of a cipher's recipients instead of letting
proc keep one row per recipient in the `field_recipients_set` database table.

## Why

When content is encrypted to a large, predictable audience (e.g. every user in
groups A and B) the `proc__field_recipients_set` table might grow very fast
(number of ciphers × number of recipients). If the recipient set can be
recomputed from a deterministic business rule, the application can provide the
recipients on demand and avoid storing them at all.

## How it works

1. A `RecipientStorage` plugin (see
   `src/Plugin/RecipientStorage/ExampleRecipientStorage.php`) implements
   `getRecipients()` / `setRecipients()` (the current recipient set) and
   `getWishedRecipients()` / `setWishedRecipients()` (the wished/target set used
   for re-encryption).
2. On a proc field widget, set **Recipients storage plugin** to this plugin.
3. New ciphers created through that widget record the plugin id in their `meta`,
   and every later read/write of their recipients — current and wished — is
   routed through the plugin by the `proc.recipient_storage` broker.

Existing ciphers and any field left on *"- None -"* keep using the standard
database-backed storage, so nothing changes for them.

## Implementing your own

Copy this module, rename it and the plugin (new `id`, class and namespace), and
replace `getRecipients()` with a call to your rule (an internal service or a
remote endpoint). Keep `setRecipients()` clearing `field_recipients_set` so no
rows are written.

The wished methods (`getWishedRecipients()` / `setWishedRecipients()`) govern
the target set used for re-encryption. This skeleton keeps them backed by the
standard `field_wished_recipients_set` database field, which is enough for most
cases. If your application owns that target set too (for example to drive
recipient removals from a rule — see the limitation below), override them to
read from / write to your own store.

## Trade-offs

- The application must **always** be able to return the exact recipient set a
  cipher was encrypted for. If the rule or its data changes, recipients may be
  offered cryptographically impossible access or lose possible access.
- Reverse lookups backed by the database table — the
  `proc:get-procs-user-is-recipient` (`gpur`) drush command and the recipient
  columns in the shipped Views — will not see ciphers governed by this plugin.
- Both `field_recipients_set` (current recipients) and
  `field_wished_recipients_set` (the target set for re-encryption) are governed
  by the plugin. The skeleton keeps the wished set database-backed; override
  the wished methods if your application owns that set too.

## Removing a recipient via re-encryption

Adding recipients through re-encryption works transparently under a recipient
storage plugin. **Removing** a recipient is also possible, but it must be driven
by the target (wished) set — the plugin cannot infer a removal on its own.

Re-encryption is driven by two states:

1. The **current** recipients of a cipher — what `getRecipients()` returns.
2. The **target** recipients the content should be (re-)encrypted for now — what
   `getWishedRecipients()` returns (normally the `field_wished_recipients_set`
   set).

The plugin exposes both states: `getRecipients()` / `setRecipients()` for
(1) and `getWishedRecipients()` / `setWishedRecipients()` for (2).

- **Adding** works because the new, larger target set comes from outside the
  plugin (typically the field's *Recipients fetcher endpoint*, compared against
  the current recipients by the client): current `{1,2}` + endpoint `{1,2,3}`
  produces a wished set of `{1,2,3}`, and re-encryption then encrypts for all
  three.
- **Removing** requires the *smaller* target set `{1,2}` to be produced from the
  current `{1,2,3}`. With the standard database storage, proc can diff the
  stored per-cipher recipient list against the freshly computed set to detect
  the removal. A plugin that does **not** keep that per-cipher current list has
  nothing on the server to diff against, so it cannot *infer* that a recipient
  should be dropped.

Consequently, when a recipient storage plugin does not persist the current
recipient set per cipher, the **application itself must set the reduced target
set** through the wished methods, for example by:

- configuring the field's *Recipients fetcher endpoint* to return the reduced,
  up-to-date membership (the client then writes it as the wished set), or
- overriding `setWishedRecipients()` / `getWishedRecipients()` in your plugin so
  the wished set comes from your own store (e.g. the current group membership),
  making removals explicit without a diff.

This is the price of not storing recipients per cipher: the application becomes
the single source of truth for "who should be a recipient now", removals
included. Because the wished set is now part of the plugin contract, the
application can own it end-to-end instead of having to write the
`field_wished_recipients_set` database field directly.

> This concern is specific to removals. Re-encryption triggered by key
> rotation ("lost access" because a recipient's key is newer than the content)
> is handled by the re-encryption plugin from the current recipient set alone,
> so it does not require the application to supply a second state.
