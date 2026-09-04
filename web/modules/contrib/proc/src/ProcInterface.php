<?php

namespace Drupal\proc;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Proc entity.
 *
 * We have this interface, so we can document proc specific constants and join
 * the other interfaces it extends.
 *
 * @ingroup proc
 */
interface ProcInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Content encryption strategies.
   *
   * Restrictive encryption strategy requires the existence of valid PGP key for
   * each and every recipient of a protected content.
   * When using the restrictive encryption strategy, protected content can only
   * be created when all recipients have a valid PGP key.
   *
   * Permissive encryption strategy filters-out from encryption recipients all
   * users without valid PGP key.
   * When using the permissive encryption strategy, protected content is created
   * skipping any user from the list of recipients that still has no PGP key.
   */
  const STRATEGY_RESTRICTIVE = 0;
  const STRATEGY_PERMISSIVE = 1;

  /**
   * Proc field operations.
   *
   * Disabled:
   * Only encryption:
   * Encryption and Signature:
   */
  const FIELD_OPS_DISABLED = 0;
  const FIELD_OPS_ONLY_ENCRYPTION = 1;
  const FIELD_OPS_ENCRYPTION_AND_SIGNATURE = 2;

  /**
   * Proc field input modes.
   *
   * File: A file field for encryption of documents.
   * Textarea: A textarea field for multi line strings encryption.
   * Textfield: A text field for encryption of strings.
   * Datefield: A datetime field for encryption of timestamp.
   */
  const INPUT_MODE_FILE = 0;
  const INPUT_MODE_TEXTAREA = 1;
  const INPUT_MODE_TEXTFIELD = 2;
  const INPUT_MODE_DATE = 3;

  /**
   * Decryption mode.
   *
   * New page: Opens the decrypt form in a new page.
   * Dialog: Opens the decrypt form in a dialog box.
   */
  const DECRYPT_MODE_NEW_PAGE = 0;
  const DECRYPT_MODE_DIALOG = 1;
  const DECRYPT_MODE_DISABLED = 2;
  const DECRYPT_MODE_INLINE = 3;

  /**
   * Password caching options.
   *
   * Disabled: No password caching.
   * Allow: Provides a checkbox option for users define password caching.
   * Apply: Hides the checkbox option and apply password caching by default.
   */
  const PASS_CACHE_OPS_DISABLED = 0;
  const PASS_CACHE_OPS_ALLOW = 1;
  const PASS_CACHE_OPS_APPLY = 2;

  /**
   * Proc encryption trigger format.
   *
   * Buttons: Show encrypt and decrypt buttons.
   * Checkbox: Use a checkbox switcher for encrypt and decrypt.
   */
  const TRIGGER_FORMAT_BUTTONS = 0;
  const TRIGGER_FORMAT_CHECKBOX = 1;

  /**
   * Fetcher filter types.
   *
   * Id: A list of user ids in a JSON format.
   *  e.g.: [{'id': 1}, {'id': 10}, {'id': 12}]
   * Name: A list of usernames in a JSON format.
   *  e.g. [{name: 'foo'}, {'name': 'bar'}, {'name': 'baz'}]
   */
  const FETCHER_FILTER_TYPE_ID = 0;
  const FETCHER_FILTER_TYPE_NAME = 1;

  /**
   * Common fields shared across multiple constants.
   */
  const COMMON_FIELDS = [
    'browser_fingerprint',
    'generation_timestamp',
    'generation_timespan',
    'signed',
  ];

  /**
   * Fields shared between HIDDEN_FIELDS and METADATA_KEYS.
   */
  const SHARED_FILE_FIELDS = [
    'source_file_name',
    'source_file_size',
    'source_file_type',
    'source_file_last_change',
    'host_page_url',
    'fetcher_endpoint',
    'proc_re_encryption_plugin',
  ];

  /**
   * Hidden fields.
   */
  const HIDDEN_FIELDS = [
    ...ProcInterface::SHARED_FILE_FIELDS,
    'cipher_text',
    ...ProcInterface::COMMON_FIELDS,
  ];

  /**
   * Field Query Metadata keys.
   */
  const FIELD_QUERY_METADATA_KEYS = [
    'proc_standalone_mode',
    'proc_form_triggering_field',
    'proc_field_delta',
    'proc_field_name',
    'proc_in_mode',
    'field-default-proc-id',
  ];

  /**
   * Metadata keys.
   */
  const METADATA_KEYS = [
    ...ProcInterface::SHARED_FILE_FIELDS,
    ...ProcInterface::COMMON_FIELDS,
  ];

  /**
   * Hidden fields for re-encryption.
   */
  const REENCRYPTION_HIDDEN_FIELDS = [
    'cipher_text',
    ...ProcInterface::COMMON_FIELDS,
  ];

  /**
   * Field types.
   */
  const FIELD_TYPES = [
    'file',
    'textarea',
    'textfield',
    'datetime',
  ];

  /**
   * Field keys.
   */
  const FIELD_KEYS = [
    'proc-file',
    'proc-text-area',
    'proc-text-field',
    'proc-date-field',
  ];

  /**
   * Proc encryption libraries.
   */
  const PROC_ENCRYPTION_LIBRARIES = [
    'encrypt',
    'encrypt-text',
    'generate-keys',
    'update',
    'decrypt',
    'update-batch',
  ];

  /**
   * The default maximum encryption size in bytes.
   */
  const DEFAULT_MAXIMUM_ENCRYPTION_SIZE = 50000000;

  /**
   * Enable stream wrapper storage.
   */
  const ENABLE_STREAM_WRAPPER_STORAGE = 1;

  /**
   * Hidden fields for key generation.
   */
  const KEY_GENERATION_HIDDEN_FIELDS = [
    'public_key',
    'encrypted_private_key',
    'generation_timestamp',
    'generation_timespan',
    'browser_fingerprint',
    'proc_email',
  ];

}
