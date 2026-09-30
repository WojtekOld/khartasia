<?php
$module_handler = \Drupal::service('module_handler');
$modules = $module_handler->getModuleList();

echo "=== Modules custom déjà présents (hors core/contrib) ===\n";
foreach ($modules as $name => $extension) {
  $path = $extension->getPath();
  if (str_starts_with($path, 'modules/custom') || str_starts_with($path, 'web/modules/custom')) {
    echo "  - $name (chemin : $path)\n";
  }
}

echo "\n=== Contenu réel du dossier modules/custom, s'il existe ===\n";
$dir = '/var/www/html/web/modules/custom';
if (is_dir($dir)) {
  foreach (scandir($dir) as $item) {
    if ($item !== '.' && $item !== '..') {
      echo "  - $item\n";
    }
  }
} else {
  echo "  Le dossier n'existe pas encore.\n";
}
