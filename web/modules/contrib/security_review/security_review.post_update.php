<?php

/**
 * @file
 * Post-update functions for Security Review.
 */

/**
 * Userpassword settings.
 */
function security_review_post_update_userpassword_settings(): void {
  $config = \Drupal::configFactory()->getEditable('security_review.check.security_review-username_same_as_password');
  $config->set('number_of_users', 100);
  $config->save();
}

/**
 * Add the hushed_view setting key.
 */
function security_review_post_update_hushed_view_setting(): void {
  \Drupal::configFactory()->getEditable('security_review.settings')
    ->set('views_access.hushed_views', [])
    ->save();
}

/**
 * Add account_creation to security_review skipped checks.
 */
function security_review_post_update_add_account_creation_skip(): void {
  $config = \Drupal::configFactory()->getEditable('security_review.settings');
  $skipped = $config->get('skipped') ?? [];
  if (!isset($skipped['account_creation'])) {
    $skipped['account_creation'] = [
      'skipped' => TRUE,
      'skipped_by' => '1',
      'skipped_on' => time(),
    ];
    $config->set('skipped', $skipped)->save();
  }
}
