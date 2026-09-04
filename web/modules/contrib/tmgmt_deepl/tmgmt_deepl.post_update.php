<?php

/**
 * @file
 * Post update functions for tmgmt_deepl module.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Advise creating Key entities for DeepL translators that used a direct key.
 *
 * The deprecated direct API key is removed from config by update_8004 (which
 * also records the affected translators in state) and, as a fallback, by
 * tmgmt_deepl_post_update_remove_obsolete_translator_settings. This function
 * only emits guidance and never writes config, so no intermediate save ever
 * validates a schema-invalid auth_key.
 *
 * @param array $sandbox
 *   Stores information for batch processing (not used in this simple case).
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
 *   Status message for Drush console output.
 */
function tmgmt_deepl_post_update_migrate_api_key_to_key_module(array &$sandbox = []): TranslatableMarkup|string {
  $logger = \Drupal::logger('tmgmt_deepl_update');
  $state = \Drupal::state();

  // Translators recorded by update_8004 when their direct key was removed.
  /** @var array<string, string> $pending */
  $pending = $state->get('tmgmt_deepl.pending_key_migration', []);
  $state->delete('tmgmt_deepl.pending_key_migration');

  // Fallback: detect translators that still carry a direct key in config (e.g.
  // sites upgraded before update_8004 recorded them). The key itself is removed
  // by the obsolete-settings post_update; here we only collect them for the
  // advisory message.
  $config_factory = \Drupal::configFactory();
  $translator_ids = \Drupal::entityQuery('tmgmt_translator')
    ->condition('plugin', 'deepl_api')
    ->accessCheck(FALSE)
    ->execute();
  $translator_ids = is_array($translator_ids) ? $translator_ids : [];
  foreach ($translator_ids as $translator_id) {
    assert(is_string($translator_id));
    if (isset($pending[$translator_id])) {
      continue;
    }
    $config = $config_factory->get("tmgmt.translator.$translator_id");
    if ($config->isNew()) {
      continue;
    }
    $settings = $config->get('settings');
    if (is_array($settings)
      && isset($settings['auth_key']) && $settings['auth_key'] !== ''
      && (!isset($settings['auth_key_entity']) || $settings['auth_key_entity'] === '')) {
      /** @var string $label */
      $label = $config->get('label');
      $pending[$translator_id] = $label;
    }
  }

  if ($pending === []) {
    return t('No DeepL translators required API key migration to the Key module.');
  }

  $translators_requiring_action = [];
  foreach ($pending as $translator_id => $label) {
    $logger->warning('DeepL translator "%label" (%id) used a deprecated direct API key. Please create a key entity (type: DeepL API Key or Authentication) with the API key and select it in the translator settings. The old key has been removed for security.', [
      '%label' => $label,
      '%id' => $translator_id,
    ]);
    $translators_requiring_action[] = sprintf('%s (%s)', $label, $translator_id);
  }

  $count = count($translators_requiring_action);
  $drush_message = t('%count DeepL translator(s) require manual API key migration to the Key module:', ['%count' => $count]);
  $drush_message .= "\n - " . implode("\n - ", $translators_requiring_action);
  $drush_message .= "\n" . t('ACTION REQUIRED: For each translator listed above, create a Key entity (type: DeepL API Key) containing its API key, then select it in the translator configuration in the TMGMT settings UI.');
  $drush_message .= "\n" . t('The old, insecurely stored keys have been removed from the configuration. See Drupal logs (channel: tmgmt_deepl_update) for details.');
  return $drush_message;
}

/**
 * Remove obsolete DeepL translator settings dropped from the 2.3.x schema.
 *
 * The 2.2.x schema defined url, url_usage, auth_key, test_url and
 * test_url_usage, and the legacy glossary submodule stored a per-translator
 * tmgmt_deepl_glossary setting. None of these are defined by the 2.3.x schema,
 * so they linger in configuration and trigger "missing schema" warnings. This
 * runs after the API key migration (alphabetical post_update ordering) and
 * strips them, leaving schema-compliant configuration.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup
 *   Status message for Drush console output.
 */
function tmgmt_deepl_post_update_remove_obsolete_translator_settings(): TranslatableMarkup {
  $config_factory = \Drupal::configFactory();
  $obsolete_keys = [
    'url',
    'url_usage',
    'auth_key',
    'test_url',
    'test_url_usage',
    'tmgmt_deepl_glossary',
  ];
  $deepl_plugins = ['deepl_api', 'deepl_free', 'deepl_pro'];
  $cleaned = 0;

  foreach ($config_factory->listAll('tmgmt.translator.') as $config_name) {
    assert(is_string($config_name));
    $config = $config_factory->getEditable($config_name);
    if (!in_array($config->get('plugin'), $deepl_plugins, TRUE)) {
      continue;
    }

    $settings = $config->get('settings');
    if (!is_array($settings)) {
      continue;
    }

    $changed = FALSE;
    foreach ($obsolete_keys as $obsolete_key) {
      if (array_key_exists($obsolete_key, $settings)) {
        unset($settings[$obsolete_key]);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $config->set('settings', $settings);
      $config->save();
      $cleaned++;
    }
  }

  return t('Removed obsolete legacy settings from @count DeepL translator(s).', [
    '@count' => $cleaned,
  ]);
}
