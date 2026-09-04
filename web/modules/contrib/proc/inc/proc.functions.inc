<?php

/**
 * @file
 * proc.functions.inc
 */

/**
 * Helper function for defining labels used in JS.
 *
 * @return array
 *   Array containing labels.
 */
function _proc_js_labels(): array {
  return [
    'proc_fileapi_err_msg' => t('Your browser does not support file API.'),
    'proc_button_state_processing' => t('Processing...'),
    'proc_max_encryption_size' => t('Error. Maximum file size allowed:'),
    'proc_max_encryption_size_unit' => t('bytes'),
    'proc_save_button_label' => t('Save'),
    'proc_size' => t('Size:'),
    'proc_type' => t('Type:'),
    'proc_last_modified' => t('Last modified:'),
    // Visible as data-drupal-selector=password-strength-text.
    'proc_minimal_password_strength' => t('Strong'),
    'proc_generate_keys_submit_label' => t('Generate encryption keys'),
    'proc_submit_saving_state' => t('Saving...'),
    'proc_password_match' => t('You must type in both password fields the same password'),
    'proc_password_required' => t('Password is required.'),
    'proc_introducing_decryption' => t('Introducing key passphrase for decryption...'),
    'proc_open_file_state' => t('Open'),
    'proc_decryption_success' => t('Decryption successful.'),
    'proc_decryption_size_mismatch' => t('Error: size mismatch.'),
    'proc_button_update_label' => t('Update'),
    'proc_introducing_signature' => t('Introducing key passphrase for signature...'),
    'proc_pass_weak' => t('Your password must be stronger.'),
    'proc_caches_unsupported' => t('Error: this browser does not support cache API. Please use an updated browser.'),
  ];
}

/**
 * Helper function for getting keyring data.
 *
 * @param string $item_id
 *   User ID | Proc ID.
 * @param string|null $type
 *   user_id | id | NULL.
 *
 * @return array
 *   Array containing pubkey and encrypted privkey.
 *
 * @deprecated in proc:10.1.83 and is removed from proc:11.0.0. Instead, you
 * should use ProcKeyManagerInterface::getKeys().
 *
 * @see https://www.drupal.org/node/3519242
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 *
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
 */
function _proc_get_keys(string $item_id, ?string $type = NULL): array {
  @trigger_error(
    '_proc_get_keys() is deprecated in proc:10.1.83 and is removed from proc:11.0.0. Instead, you should use ProcKeyManagerInterface::getKeys(). See https://www.drupal.org/node/3519242',
    E_USER_DEPRECATED
  );
  if (!isset($type)) {
    $type = 'user_id';
  }

  try {
    $query = Drupal::entityQuery('proc')
      ->accessCheck(TRUE)
      ->condition('type', 'cipher', '!=')
      ->condition('status', 1)
      ->condition($type, $item_id)
      ->sort('id', 'DESC')
      ->range(0, 1);
  }
  catch (\Exception $e) {
    // Log error:
    \Drupal::logger('proc')->error($e->getMessage());
    return [];
  }

  $key_id = $query->execute();
  if (empty($key_id)) {
    return [];
  }
  $key_id = array_values($key_id)[0];
  $entity = Drupal::entityTypeManager()->getStorage('proc')->load($key_id);
  // Private key:
  $keyring_keys = [];
  $keyring_keys['encrypted_private_key'] = $entity->get('armored')
    ->getValue()[0]['privkey'];
  $keyring_keys['public_key'] = $entity->get('armored')
    ->getValue()[0]['pubkey'];
  $keyring_keys['created'] = $entity->get('created')->getValue()[0]['value'];
  $keyring_keys['changed'] = $entity->get('changed')->getValue()[0]['value'];
  $keyring_keys['keyring_cid'] = $key_id;
  $keyring_keys['keyring_type'] = $entity->get('type')->getValue()[0]['value'];

  return $keyring_keys;
}

/**
 * Helper function to get max file size for encryption in bytes.
 *
 * @return float|int
 *   Dynamically defined max file size.
 *
 * @deprecated in proc:10.1.82 and is removed from proc:11.0.0. Instead, you
 * should use \Drupal\Component\Utility\Environment::getUploadMaxSize().
 *
 * @see https://www.drupal.org/node/3519231
 *
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
 */
function _proc_get_post_max_size_bytes(): float|int {
  @trigger_error('_proc_get_post_max_size_bytes() is deprecated in proc:10.1.82 and is removed from proc:11.0.0. Instead, you should use \Drupal\Component\Utility\Environment::getUploadMaxSize(). See https://www.drupal.org/node/3519231', E_USER_DEPRECATED);
  $post_max_size = substr_replace(ini_get('post_max_size'), "", -1);
  return $post_max_size * 1000000;
}
