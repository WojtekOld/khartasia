<?php
$blocks = \Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => 'khartasia_ui']);
foreach ($blocks as $block) {
  $plugin_id = $block->getPluginId();
  $region = $block->getRegion();
  $status = $block->status() ? 'activé' : 'désactivé';
  echo "{$block->id()} : plugin=$plugin_id, région=$region, statut=$status\n";
}
