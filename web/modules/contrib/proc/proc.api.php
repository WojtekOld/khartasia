<?php

/**
 * @file
 * Primary module hooks for proc module.
 */

declare(strict_types=1);

/**
 * Allow modules to alter decrypt link classes.
 *
 * @param array $cipher_text_data
 *   Alterable array of cipher text data.
 * @param array $context
 *   Form state of decryption form.
 */
function hook_decryption_link_classes_alter(array $cipher_text_data, array $context): void {
  // This might be needed for adjusting to different themes.
}

/**
 * Allow modules to alter the proc field element.
 *
 * @param array $element
 *   The alterable element.
 * @param array $form_state
 *   The form state of the encryption form.
 * @param array $form
 *   The form of the encryption form.
 */
function hook_proc_element_alter(array &$element, array &$form_state, array $form): void {
}

/**
 * Allow modules to alter the suffix of a password salt for decryption.
 *
 * @param string $suffix
 *   The alterable suffix of the password salt.
 * @param mixed $createdTimestamp
 *   The timestamp of when the password was created or null.
 */
function hook_proc_hash_salt_legacy_extension_alter(string &$suffix, mixed $createdTimestamp): void {
}

/**
 * Allow modules to alter the label of a proc entity.
 *
 * @param string $label
 *   The label of the proc entity being created or updated.
 * @param array $meta
 *   The metadata of the proc, including host page URL as context.
 * @param array $form_state
 *   The form state of the encryption form.
 */
function hook_proc_label_alter(string &$label, array $meta, array $form_state): void {
}
