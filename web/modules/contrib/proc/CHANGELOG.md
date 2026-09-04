# Changelog

All notable changes to this project will be documented in this file.

## [10.1.125] - 27 Aug 2026

- Fixed proc_update_10005 failing with "The user_id column cannot have NOT NULL constraints as it holds NULL values".

## [10.1.124] - 24 Aug 2026

- Fixed text field decryption when inline standalone decryption is enabled.
- Added JS api for text decryption.
- Added update for removing duplicated entries in proc_update_keyring_meta queue.

## [10.1.123] - 28 Jul 2026

- Fixed upgrade_status Drupal 11, 12 compatibility issues.
- Added upper limit for the amount of cached items in local storage.
- Removed @Translation annotation for adequacy to Drupal 11.
- Restored and normalized wrong password error message.
- Added service worker for semi-autonomous background re-encryption.
- Added autonomous triggering of background re-encryption.
- Fixed cutoff of retention period on proc_janitor removal of proc_reporting entries.
- Removed log entry when skipping queue item. Too much verbosity.
- Updated README.md of proc_janitor.
- Added maximum retention age control for proc_reporting. Clean up run by proc_janitor.
- Added di to ProcEncryptForm.
- [BUG FIX] Added payload approach, instead of only CSV, for defining recipients on encryption.
- Added background re-encryption.
- Resolved entity type definition mismatch warnings.
- Moved prebuilt view from install to optional configuration.
- Added drupal:file as a dependency.
- Fixed download file name for PDFs in media viewer.

## [10.1.122] - 22 Jun 2026

- Fixed race condition between proc_janitor and the queue worker that caused invalid update jobs to be re-added after cleanup.
- Added proc_update_10004 to re-run removal of invalid update jobs from keyrings.

## [10.1.121] - 18 Jun 2026

- Added exception to proc_update_10002 for allowing it to be executed/skipped when field_wished_recipients_set is not yet created.

## [10.1.120] - 15 Jun 2026

- Incremented version number of internal js libraries.
- Added version number to console.info of proc-field and proc-decrypt libraries.

## [10.1.119] - 15 Jun 2026

- Added original file name for download link when mime type is not recognized.
- Fixed memory exhaustion on large datasets during proc_update_10003.

## [10.1.118] - 11 Jun 2026

- Added clean up of update jobs on post delete.
- Added update for the removal of invalid update jobs.
- Added find-invalid-update-jobs drush command.

## [10.1.117] - 5 Jun 2026

- Added closing of decryption dialogs after decryption.
- Added configuration for allowing local cache of password in stand-alone mode decryption.

## [10.1.116] - 5 Jun 2026

- Removed redundant decryption success message.

## [10.1.115] - 4 Jun 2026

- Added Drupal.proc.dialogBuilder for allowing seamless changes to the inline decryption password dialog.
- Fixed validation bug on the manual removal of invalid update jobs.

## [10.1.114] - 23 May 2026

- Moved atomic js modules to js/modules/atomics folder.
- Updated README.md on inline decryption at standalone mode.

## [10.1.113] - 22 May 2026

- Added configuration for allowing inline decryption in stand-alone mode.
- Added proc media viewer js module.

## [10.1.112] - 21 May 2026

- Fixed bug on inline decryption of PDFs by adding explicit definition of mime types.
- Added error handling for covering failure caused by lack of plain-text file permissions.
- Added cache context.
- Gated info message about processed queued meta update after keyring-meta-update-log-enabled configuration.

## [10.1.111] - 8 May 2026

- Fixed invalidation of expired password local caches on inline decryption.
- Added ProcUpdateJobsCountService.

## [10.1.110] - 5 May 2026

- Added PDF rendering to the armoured field formatter.
- Fixed bug on decrypting in new page.
- Added shield icon to field UI admin page.
- Gated queue worker info log with a global configuration.
- Disabled queue worker info log by default.
- Documented new global option in README.
- Added helper functions and js modules for decryption on-the-fly in edit mode.
- Enriched docblocks in js files.

## [10.1.109] - 18 Apr 2026

- Added endpoint for the retrieval of my update jobs count.
- Restored decryption and encryption access checks for re-encryption operation.
- Added unified decryption access to cipher text download.
- Enforced CSRF request header token to POST requests.

## [10.1.108] - 14 Apr 2026

- Added proc_janitor submodule.
- Added global configuration for allowing breaking of the chain of trust on the update of wished recipients.
- Added option for showing created datetime instead only generation datetime.
- Added postDelete in proc entity for the removal of the cipher text source file.
- Added service class for removing given updade job from all keyrings.
- Added drush command and service class for the removal of unreferenced/orphan proc cipher entities.

## [10.1.107] - 20 Mar 2026

- Added proc_reporting submodule.
- Fixed but on Request Re-encryption confirmation form.

## [10.1.106] - 16 Mar 2026

- Added opacity to the re-encryption link when clicked.

## [10.1.105] - 12 Mar 2026

- Fixed bug at the progress bar of batch re-encryption.
- Fixed bug on surpassing the configured limit of items per batch re-encryption.

## [10.1.104] - 10 Mar 2026

- Fixed bug on firing too early page reload after re-encryption batch.

## [10.1.103] - 5 Mar 2026

- Fixed bug on invalid indexes of update jobs.
- Restored status message at batch re-encryption page.
- Added page load at the end of re-encryption batch operation.

## [10.1.102] - 3 Mar 2026

- Fixed bug on closing the Request re-encryption confirmation dialog.

## [10.1.101] - 2 Mar 2026

- Added global configuration for changing the class or decrypt/re-encrypt link in standalone mode.

## [10.1.100] - 26 Feb 2026

- Fixed bug on batch re-encryption.

## [10.1.99] - 26 Feb 2026

- Removed dependency of history module.

## [10.1.98] - 26 Feb 2026

- Added batch update.
- Added proc_json_file_service.
- Added percentage feedback on the progress of batch re-encryption.
- Added validations for the metadata on batch re-encryption.
- Added ProcReEncLinkService class and its interface.
- Added configuration for batch re-encryption label.
- Added requirement of history module in composer.json.

## [10.1.97] - 30 Jan 2026

- Fixed bug on the selection of recipients for re-encryption.
- Added proc:add-recipient Drush command.
- Added ProcRequestReEncryptionConfirmForm class.

## [10.1.96] - 13 Jan 2026

- Added Update Jobs field and its validations to `proc/<proc ID>/edit` for keyrings. This allows administrators to manually set update jobs.

## [10.1.95] - 8 Jan 2026

- Fixed bug on type hint that in some scenarios prevented encoding in a field with existing default value.

## [10.1.94] - 5 Dec 2025

- Fixed important bug on re-encryption that caused wrong definition of recipients when multiple entities required multiple sets of recipients.

## [10.1.93] - 4 Dec 2025

- Fixed race condition bug that affected setting update jobs.
- Added the possibility of selecting proc entities for re-encryption by proc ID.
- Simplified re-encryption success status message.
- Added getCsrfToken().
- Simplified console log message on adding public keys to local storage.
- Simplified console log message on finding public keys already in local storage.
- Enriched cache bust parameters for proc field js.
- Added update for clearing current update jobs. They will have to be re-set after the update.
- Added update for clearing sets of wished recipients. They will have to be re-set after the update.

## [10.1.92] - 13 Nov 2025

- Fixed bug on cache clearing after changing update jobs.

## [10.1.91] - 13 Nov 2025

- Fixed bug on re-encryption of several procs at once.
- Added Drush commands for management of proc entities.

## [10.1.90] - 4 Nov 2025

- Added the Proc Metadata Transitioner submodule.
- Enriched console messages. Validated recipient IDs on re-encryption.
- Added error handling for failure on fetching re-encryption recipients.

## [10.1.89] - 4 May 2025

- Added subfolders in the js directory. Added versioning to js libraries for cache invalidation.

## [10.1.88] - 30 Apr 2025

- Bug fix. Added missing check for default value of proc field in ProcFieldProcessor.

## [10.1.87] - 29 Apr 2025

- Allowed only input or select elements to be used as source of dynamic fetcher filters, identified by id or name.

## [10.1.86] - 29 Apr 2025

- Removed enforced input element on fetcher filter.

## [10.1.85] - 25 Apr 2025

- Rolled back to static entity query for backwards compatibility at userHasKeyring().

## [10.1.84] - 25 Apr 2025

- Fixed bug on getting metadata.

## [10.1.83] - 23 Apr 2025

- Added in ProcKeysCacheTypeForm a warning on leaving the browser unlocked while password cache is active.
- Added origin field name to proc metadata.
- Fixed usage of default proc id on editing content.
- Added standard re-encryption plugin.
- Moved lost access recipients into the wished set of recipients.
- Fixed comparison of dates in re-encryption plugin.
- Added missing property to ProcEntityReferenceWidget.
- Added uid as default field key when fetcher endpoint retrieves objects instead of a flat list of user IDs.
- Added accepted json formats in the description of the fetcher endpoint field settings of the proc field widget.
- Updated list of wished recipients by fetching endpoint.
- Added X-CSRF-Token for updating set of wished recipients.
- Cached response of wished recipients. Refactored proc field js init method.
- Added My Update Form.
- Added update job proc IDs to my update form.
- Added removal of expired update jobs.
- Added number of files and max size sum for re-encryption.

## [10.1.82] - 18 Feb 2025

- Added several js modules for decryption and update operations.
- Added triggerCacheDecryption js module.
- Added js modules for splitting steps of decryption.
- Added re-encryption.
- Fixed condition checking for opLink at handleDecryptionResult().
- Added console info showing proc IDs being updated and their resolved recipients.
- Submitted metadata on re-encryption.
- Fixed wrong password error message.
- Added method for checking PGP opening format.
- Added previous recipients to re-encryption success message.
- Added buildDecryptionLink method for operations in form base.
- Fixed selection of recipients on re-encryption.
- Implemented getPassSessionCachingSalt() for decryption of lists.
- Implemented messages API.
- Removed getDrupalSettings() in favor of methods covering each subset of encyrption operation settings.
- Added check of field on decryption.
- Normalized query string variable name.
- Reorganized js settings for encryption and update.
- Simplified drupalSettings definition at decryption form.
- Fixed bug in field formatter.
- Fixed cache usage on stand-alone decryption mode.
- Restored switch to text encryption library.
- Implemented generateAjaxSettings().
- Removed usage of deprecated items in navigator object.
- Issue #3474333 by abautu: Incorrect match of element by id/name. Added select selectors to the document.querySelector expressions.
- Replaced FileSystemInterface::EXISTS_REPLACE by FileExists::Rename for avoiding data loss on rare hash collisions. Issue raised by suranga.gamage.
- Replaced deprecated FormElement by FormElementBase.
- Added parent constructor to ProcKeysGenerationForm class.
- Added formatSize method into ProcForm class.
- Added ProcFieldProcessor service.
- Replaced static call to ::fromUri by new Url object.
- Replaced ::escape by renderInIsolation at ProcOpFormBase class.
- Added ProcDecAccessCheck class.
- Fixed redirection on key generation form submit. Added d.11 on info file.
- Replaced Html::escape by htmlspecialchars.
- Added unit tests for the keys generation form.
- Added unit tests for several classes.
- Added default bundle to proc entities.
- Avoided repeated items across constants.
- Fixed phrasing in the parameter of a route.
- Fixed bug on usage of proc field with default value.


## [10.1.81] - 11 Sep 2024

- Added rendering of SVG image files at armoured field formatter.

## [10.1.80] - 9 Sep 2024

- Fixed bug in absence of CC recipients by direct fetcher.
- Added decryption of multiple files in a single click.

## [10.1.79] - 23 Aug 2024

- Fixed bug on key creation.
- Added fetcher endpoint to cipher metadata.
- Required permission for encryption by anonymous user.

## [10.1.78] - 6 Aug 2024

- Fixed bug on trying to access a proc with id ZERO.

## [10.1.77] - 2 Aug 2024

- Disabled autocomplete on proc file fields for non-admin users by default.

## [10.1.76] - 1 Aug 2024

- Fixed bug on submission of existing content by users not included in the set
of recipients.

## [10.1.75] - 31 Jul 2024

- Removed the decrypt button from disabled text fields.

## [10.1.74] - 22 Jul 2024

- Removed reset button if field is disabled.

## [10.1.73] - 19 Jul 2024

- Added configuration for the label of reset button.

## [10.1.72] - 18 Jul 2024 at 20:45 CEST

- Removed yet another type declaration for backwards compatibility.

## [10.1.71] - 18 Jul 2024 at 20:39 CEST

- Removed yet another type declaration for backwards compatibility.

## [10.1.70] - 17 Jul 2024

- Fixed bug on the selection of recipients under permissive policy.

## [10.1.69] - 11 Jul 2024

- Added multimedia support on armoured formatter.
- Added original file name to download of unrecognized mime type files.
- Added temporary download link to unrecognized mime types in decryption of lists.

## [10.1.68] - 5 Jul 2024

- Added option for a reset button in single-valued proc file fields.

## [10.1.67] - 4 Jul 2024 at 13:55 CEST

- Rolled back from FormElementBase to FormElement.

## [10.1.66] - 4 Jul 2024 at 12:39 CEST

- Replaced ProcAjaxModalMsgController by directly appending a message.
- Added fall back to default label of encrypt button.
- Added autocomplete attribute on password field.
- Increased default value for maximum size of encryption.

## [10.1.65] - 18 Jun 2024

- Fixed bug on the change of a proc field label.
- Added on-the-fly decryption of default text.

## [10.1.64] - 25 May 2024

- Fixed selection of delta on proc file field widget being shown in a dialog.
