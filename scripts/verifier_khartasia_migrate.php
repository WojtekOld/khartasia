<?php
$info = \Drupal::service('extension.list.module')->getExtensionInfo('khartasia_migrate');
echo "Description : " . ($info['description'] ?? '(aucune)') . "\n";
echo "Package : " . ($info['package'] ?? '(aucun)') . "\n";

echo "\n=== Fichiers dans le module ===\n";
$path = '/var/www/html/web/modules/custom/khartasia_migrate';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($rii as $file) {
  echo "  " . str_replace($path . '/', '', $file->getPathname()) . "\n";
}
