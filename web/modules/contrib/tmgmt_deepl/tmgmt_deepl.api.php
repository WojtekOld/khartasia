<?php

/**
 * @file
 * Hooks provided by the tmgmt_deepl module.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;

/**
 * Modify the DeeplTranslatorUi checkoutSettingsForm.
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\tmgmt\JobInterface $job
 *   The tmgmt job entity.
 */
function hook_tmgmt_deepl_checkout_settings_form_alter(array &$form, JobInterface $job): void {
  $form['additional_info'] = [
    '#markup' => t('Additional information shown in checkoutSettingsForm'),
  ];
}

/**
 * Modify the DeeplTranslatorUi buildConfigurationForm.
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
function hook_tmgmt_deepl_build_configuration_form_alter(array &$form, FormStateInterface $form_state): void {
  $form['additional_info'] = [
    '#markup' => t('Additional information shown in buildConfigurationForm'),
  ];
}

/**
 * Modify the DeeplTranslator hasCheckoutSettings method.
 *
 * @param bool $has_checkout_settings
 *   Whether job should have checkout settings.
 * @param \Drupal\tmgmt\JobInterface $job
 *   The tmgmt job entity.
 */
function hook_tmgmt_deepl_has_checkout_settings_alter(bool &$has_checkout_settings, JobInterface $job): void {
  $has_checkout_settings = TRUE;
}

/**
 * Alter deepl translation options before requesting a translation.
 *
 * @param \Drupal\tmgmt\Entity\Job $job
 *   TMGMT Job to be used for translation.
 * @param array $options
 *   The options array, which should be altered.
 */
function hook_tmgmt_deepl_translate_options_alter(Job $job, array &$options): void {
  if ($job->getSetting('custom_setting') == 1) {
    $options['xyz'] = 123;
  }
}
