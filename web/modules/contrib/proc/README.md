# Protected Content (proc) Module

## Contents

- [Introduction](#introduction)
- [Usage on Standalone Mode](#usage-on-standalone-mode)
  - [Inline Decryption in Standalone Mode](#inline-decryption-in-standalone-mode)
- [Usage on Field Mode](#usage-on-field-mode)
- [Field formatters](#field-formatters)
- [Background Re-encryption](#background-re-encryption)
- [Auxiliary Paths](#auxiliary-paths)
- [API Paths](#api-paths)
- [JavaScript API](#javascript-api)
- [Canonical Entity Paths](#canonical-entity-paths)
- [Global Configuration](#global-configuration)
- [Widget Configuration](#widget-configuration)
- [Submodules](#submodules)
- [Drush Commands](#drush-commands)
- [Installation](#installation)
- [OpenPGP.js](#openpgpjs)
- [Caveats](#caveats)
- [Future Roadmap](#future-roadmap)
- [Learn More About Protected Content](#learn-more-about-protected-content)
- [Similar Project](#similar-project)
- [Maintainers](#maintainers)

## Introduction

The Protected Content (proc) module enables client-side encryption and decryption of content. It supports two modes of usage:

- **Standalone Mode**: Provides forms to encrypt and decrypt content.
- **Field Mode**: Provides a field type to encrypt and decrypt text or files from within any fieldable form.

## Usage on Standalone Mode

- Access `/proc/generate-keys` to generate keys for the current user.
- Access `/proc/add/<uids_csv>` to encrypt a file for the users identified in `<uids_csv>` (a comma-separated list of user IDs). Protected Content will provide an exclusive access link (in the format `/proc/<proc ID>` ) for the recipients to decrypt the file.
- Access `proc/my-update/<proc_ids_csv>` to update the recipients of the files pending update. If the CSV list is omitted, the content is automatically selected from the update jobs of the current user.
- Access `/proc/my-update-batch` to update the recipients of files pending update in batch mode. The batch process provides a progress bar.

### Inline Decryption in Standalone Mode

When the global setting **Enable inline decryption in standalone mode** is turned on (see [Global Configuration](#global-configuration)), decrypting a protected content item at `/proc/<proc ID>` no longer triggers a file download. Instead, the decrypted content is rendered directly in a modal dialog, using a MIME-aware viewer that supports the following content types:

| Content type | Viewer |
|---|---|
| `text/plain` | Scrollable `<pre>` block |
| `image/*` (including SVG) | Inline image |
| `video/*` | HTML5 video player with controls |
| `audio/*` | HTML5 audio player with controls |
| `application/pdf` | Embedded PDF iframe |
| Everything else | Download link fallback |

When this setting is disabled (default), the original download behaviour is preserved.

## Usage on Field Mode

- Add a field of type `Proc Entity Reference Field` to any form and configure it according to your needs.

## Field formatters

The `Proc Entity Reference Field` field type provides the following formatters:

- **Label**: displays the label of the encrypted content. Options:
  - ***Link label to the referenced entity***: the link points to the entity details page.
  - ***Open in a new tab***
  - ***Switch link destination to the decrypt form***
  - ***Use decrypt modal form***
- **Armoured**: allows the embedded decryption of multimedia content (image, video, audio and text) on page load.

## Background Re-encryption

When a user has pending re-encryption jobs (update jobs), Protected Content can automatically process a configurable number of them in the background every time the user decrypts content.

Because the background re-encryption runs in a Web Worker, it executes without blocking the user interface. The user can continue navigating or working normally.

Background re-encryption is triggered by all decryption paths:

- **Standalone decryption** - when a user decrypts content at `/proc/<proc ID>`.
- **Field decryption** - when a user decrypts content from a `Proc Entity Reference Field` widget on any entity form.
- **Armoured formatter decryption** - when encrypted content is decrypted inline on page load via the armoured field formatter.

### Autonomous Re-encryption (Service Worker)

In addition to the decryption-triggered Web Worker described above, Protected Content can process pending jobs autonomously through a [Service Worker](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API). When enabled, and once a password has been cached in the browser session, the Service Worker picks up newly assigned jobs while a project tab remains open - without requiring the user to decrypt again. Unlike the Web Worker, the Service Worker survives navigation between pages and coordinates as a single instance across tabs.

The passphrase is never persisted: it is read from the (encrypted) session cache, decrypted client-side, and handed to the Service Worker via `postMessage`, which holds it in memory only. A Service Worker requires a secure context (HTTPS, or `localhost`/`127.0.0.1`); on unsupported or non-secure contexts the feature is simply inactive.

The Service Worker also serves cipher texts cache-first (intercepting `/api/proc/getcipher/*`).

## Auxiliary Paths

- `/proc/key-cache-settings` allows you to configure the key cache settings. The available options are: `No cache`, `1 hour cache`, `2 hours cache`.
- `/proc/my-keys-overview` lists all keys generated by the current user.

## API Paths

- `/api/proc/getpubkey/{entity_ids}/{search_by}` returns as json the public keys identified by `{entity_ids}` (a comma-separated list of IDs). The `{search_by}` parameter can be `user_id` xor `id` (meaning, key ID).
- `/api/proc/getcipher/{cipher_ids}` returns as json the cipher texts identified by `{cipher_ids}` (a comma-separated list of IDs) or, depending on the corresponding entity, the metadata of a public key.
- `POST /api/proc/store-recipients` accepts a JSON payload `{"recipients": [1, 2, 3]}` and stores the recipient list in a server-side temporary store. Returns `{"token": "<32-char-hex>"}`. Used by the payload recipients delivery mode to avoid passing large CSV lists in the URL.

## JavaScript API

Protected Content exposes a client-side API so other modules can decrypt an encrypted **text** item without dealing with recipients, keys, passwords or the OpenPGP details themselves.

To use it, attach the `proc/proc-api` library from the consuming module (for example in a render array or a library dependency):

```php
$build['#attached']['library'][] = 'proc/proc-api';
```

Then call the entry point with the ID of a proc cipher entity:

```js
const result = await Drupal.proc.api.decryptText(cipherTextId);
// result = {
//   plain_text: '…the decrypted text…',   // empty string on failure
//   message: [ '…status or error strings…' ]
// }
```

The method returns a Promise that resolves to an object with two keys:

- `plain_text` - the decrypted text, or an empty string when decryption did not happen.
- `message` - an array of human-readable status/error messages (empty on success).

Because it may need to prompt for a password, the call is asynchronous; always `await` it (or use `.then()`).

Typical `message` outcomes when decryption does not happen include: the item is not an encrypted text, the current user is not a recipient, the user has no key or a key that is newer than the content (the content must be re-encrypted first), or the password dialog was cancelled.

## Canonical Entity Paths

- `admin/content/procs` lists all protected content entities: encrypted content and keys.
- `proc/<entity ID>/details` shows the details of a protected content entity. Notably, it shows the cache type of the key and the recipients of the content.
- `proc/<entity ID>/edit` allows you to edit, in the metadata layer only, the recipients of encrypted content or the type of the protected content entity.
- `proc/<entity ID>/delete` allows you to delete a protected content entity.

## Global Configuration

A global configuration form is available at `/admin/config/system/proc`. The following global settings are available:

- Global stream wrapper
- Enable stream wrapper globally
- RSA key size
- Maximum size for encryption (in bytes)
- Block size (lines of armored cipher text)
- Enable block size limit
- Global standard label for the encrypt link
- Global label for encryption on a file field when there is a default value
- Classes for the encrypt link
- Enable local password cache for fields
- Enable local password cache in stand-alone mode - when enabled, successful decryption in stand-alone mode (direct `/proc/<proc ID>` page or modal-loaded decrypt form) stores the password in the browser session cache and reuses it for subsequent decryption attempts in the same session
- Encryption strategy
- Suppress decrypt link from the encryption success message
- Suppress the entire encryption success message
- Use entity creation date for metadata message
- Allow non recipients to trigger automatic filling of the wished set of recipients field
- Enable queue info log for keyring meta updates
- **Enable inline decryption in standalone mode** - when enabled, decrypted files are rendered in a MIME-aware dialog viewer instead of being downloaded (see [Inline Decryption in Standalone Mode](#inline-decryption-in-standalone-mode))
- Maximum number of files for re-encryption at My Update form
- Maximum sum size (MB) of files for re-encryption at My Update form
- **Enable background re-encryption during decryption** - master toggle that enables or disables background re-encryption via web worker after a successful decryption (see [Background Re-encryption](#background-re-encryption))
- **Background re-encryption jobs during decryption** - the number of pending update jobs to process in the background per decryption event (default: 2, set to 0 to disable)
- **Recipients delivery mode** - controls how recipient user IDs are passed to the encryption form in field mode. *CSV in URL (legacy)* passes them as a comma-separated list in the URL path (transparent and manually editable, but fails with many recipients due to URL length limits). *Payload (token)* stores recipients server-side in a temporary store and passes a short token in the URL (supports unlimited recipients). Standalone mode always uses CSV regardless of this setting.

## Widget Configuration

Widget configurations are available when managing the form display of an entity where the `Proc Entity Reference Field` is used. The following settings are available:

- Machine name of the field containing the user IDs of recipients
- Machine name of the field containing user IDs of CC recipients
- Define the user IDs of recipients in a CSV list
- Endpoint for fetching user IDs of recipients
- Identifier of form elements to be used as parameters for the endpoint
- Label for the encrypt link
- Label for the decrypt link
- Type of identifier
- Modes of operation
- Modes of input
- Modes of decryption
- Password caching
- Trigger format
- Submit element ID for the master field
- Trigger decryption of another encrypted field
- Relabeling pattern
- Re-encryption plugin
- Recipients delivery mode override - allows a specific field to force CSV mode regardless of the global recipients delivery mode setting. Useful for fields with a direct/manual fetcher or a small static recipient list where the payload approach is unnecessary.

## Plugin Types

- Proc Re-encryption Recipients Set (ProcReEncRecSet) allows the update the wished set of recipients in case business rules requires a change in the set of recipients or keys renewal.
- Proc Relabelling (ProcRelabelling) allows the relabelling of the encrypted content. This is useful for avoiding final user direct exposure of sensitive file names.

## Submodules

proc ships with three optional submodules that can be enabled independently:

- **proc_janitor** - Adds maintenance routines for cleaning up orphaned proc cipher entities and related managed files.

- **proc_metadata_transitioner** - A maintenance tool for back-filling cipher text metadata (specifically the `fetcher_endpoint` entry) on proc cipher entities that were created before version 10.1.79. It is only needed when legacy protected content must be made compatible with the current re-encryption process. It provides both a GUI (`/admin/config/content/proc/metadata_transitioner`) and Drush commands for adding or removing the missing metadata in batches.

- **proc_reporting** - Adds operational monitoring reports for admins. It takes hourly snapshots of pending re-encryption tasks per user and maintains daily counters of encrypt, cipher access/download, and re-encrypt operations per user. The reports are accessible under **Administration > Reports** and are protected by the `view proc reporting` permission.

## Drush Commands

The module provides a set of Drush commands for managing protected content entities, keyrings, and update jobs from the command line.

| Command | Alias | Description |
|---|---|---|
| `proc:remove-wished` | `prw` | Remove contents of the wished set of recipients field in a given proc cipher text entity. |
| `proc:get-update-jobs` | `guj` | Get update jobs, if any, from a given user ID. |
| `proc:get-update-workers` | `guw` | Get update workers, if any, from a given proc ID. |
| `proc:remove-recipient` | `prr` | Remove a given recipient from a given proc by their IDs. |
| `proc:remove-update-jobs` | `ruj` | Remove update jobs from a given user ID. If proc_id is given, remove only that occurrence. |
| `proc:add-update-job` | `auj` | Add an update job for a user by given user and proc IDs. |
| `proc:remove-all-jobs` | `raj` | Remove all occurrences of a given update job across all keyrings. |
| `proc:get-procs-user-is-recipient` | `gpur` | Get IDs of procs where a given user is a recipient. |
| `proc:user-keys-labels` | `pql` | Given a CSV list of user IDs, return a CSV list of the labels of their keyrings. |
| `proc:procs-host-ids` | `phis` | Given a CSV list of proc IDs, a host entity type and field machine name, return a list of host entity IDs. |
| `proc:procs-labels` | `plb` | Given a CSV list of proc IDs, return a CSV list of their labels. |
| `proc:find-cipher-missing-meta` | `pcm` | Return proc IDs of cipher entities missing required meta entries. |
| `proc:count-cipher-wished-recipients` | `pcwr` | Count cipher proc entities with non-empty wished recipients set. |
| `proc:get-unreferenced-cipher-procs` | `gucp` | Return IDs of cipher proc entities not referenced by fields of type proc_entity_reference_field. |
| `proc:add-recipient` | `par` | Add a given user as a recipient to a given proc by their IDs. |
| `proc:add-wished-recipient` | `pwr` | Add a given user as a wished recipient to a given proc by their IDs. |
| `proc:get-wished-recipients` | `gwr` | Get values of the wished recipients set for a given proc ID. |
| `proc:find-invalid-update-jobs` | `fiuj` | Scan update jobs by user and output CSV of those referencing non-existent cipher entities. |

## Installation

Install as usual. OpenPGP.js v5.0.1 is shipped with the module. See the disclaimer below.

## OpenPGP.js

A copy of minified `openpgp.js` is included in the module at `js/openpgp.min.js`. It is used for encrypting and decrypting content. The version of `openpgp.js` is 5.0.1, licensed under LGPL-3.0. Source code is available at [OpenPGP.js](https://openpgpjs.org/). The minified version is available at [unpkg](https://unpkg.com/openpgp@5.0.1/dist/openpgp.min.js).

## Caveats

Proc uses a cache first strategy for handling public keys for encryption and cipher texts for decryption. It will try to download a cipher text only when the download is needed. However, in development context, if you reset the proc table you will have to clear manually your browser's cache to avoid errors when decrypting again a protected content which entity ID was already used previously.

## Future Roadmap

- Text area field encryption and decryption.
- Cryptographic signature and signature verification.
- Multiple signatures per content with recursive encryption.

## Learn More About Protected Content

- [Protected Content, Secure Open Source Day - Haarlem (2019)](https://youtu.be/rVWrkZPGj3s "Protected Content, Secure open source day - Haarlem (2019)")
- [Protected Content: End-to-End PGP Encryption for Drupal, Drupal Camp - Kyiv (2019)](https://youtu.be/Gx8uxEpi4Po "End-to-End PGP Encryption for Drupal, Drupal Camp - Kyiv (2019)")
- [Protected Content by Asymmetrical Client-Side Encryption, Drupal Dev Days - Ghent (2022)](https://drupalcamp.be/en/drupal-dev-days-2020/session/protected-content-asymmetrical-client-side-encryption "Protected Content by Asymmetrical Client-Side Encryption, Drupal Dev Days - Ghent (2022)")
- [A Pretty Good Content Protection (Workshop), DrupalCon - Prague (2022)](https://events.drupal.org/prague2022/sessions/pretty-good-content-protection-workshop "A Pretty Good Content Protection (Workshop), DrupalCon - Prague (2022)")
- [Securing Drupal Content with Client-Side Encryption: A Zero Trust Approach (Workshop), Drupal Con - Vienna (2025)](https://www.youtube.com/watch?v=97mIoZB9HZ4)

## Similar Project

- [Client-Side File Crypto](https://www.drupal.org/project/client_side_file_crypto "Client-Side File Crypto")

## Maintainers

- Rodrigo Panchiniak Fernandes - [Profile](https://www.drupal.org/user/411448)
- Duarte Briz (duartebriz) - [Profile](https://www.drupal.org/u/duartebriz)
- Reva Gomes (revagomes) - [Profile](https://www.drupal.org/u/revagomes)
