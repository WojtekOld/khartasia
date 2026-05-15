<?php
$ids = \Drupal::service('plugin.manager.migration')->getDefinitions();
foreach ($ids as $id => $def) {
  if (strpos($id, 'd6_') === 0) {
    try {
      $migration = \Drupal::service('plugin.manager.migration')->createInstance($id);
      // Supprimer la config
      $config = \Drupal::configFactory()->getEditable('migrate_plus.migration.' . $id);
      if (!$config->isNew()) {
        $config->delete();
        echo "Deleted config: $id\n";
      }
    } catch (\Exception $e) {
      echo "Skip: $id\n";
    }
  }
}
echo "Done\n";
