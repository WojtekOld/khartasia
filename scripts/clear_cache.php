<?php
/**
 * @file
 * Script pour vider le cache Drupal.
 * 
 * Usage: ddev drush scr scripts/clear_cache.php
 */

echo "=== VIDAGE DU CACHE ===\n";

// Vider tous les caches
\Drupal::service('cache.render')->deleteAll();
\Drupal::service('cache.discovery')->deleteAll();
\Drupal::service('cache.bootstrap')->deleteAll();
\Drupal::service('cache.config')->deleteAll();
\Drupal::service('cache.data')->deleteAll();
\Drupal::service('cache.entity')->deleteAll();
\Drupal::service('cache.menu')->deleteAll();
\Drupal::service('cache.page')->deleteAll();
\Drupal::service('cache.dynamic_page_cache')->deleteAll();
\Drupal::service('cache.toolbar')->deleteAll();

// Reconstruire les routes
\Drupal::service('router.builder')->rebuild();

// Reconstruire les thèmes
\Drupal::service('theme.registry')->reset();

echo "✅ Tous les caches ont été vidés.\n";