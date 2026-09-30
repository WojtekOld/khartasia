<?php
$block = \Drupal::entityTypeManager()->getStorage('block')->load('khartasia_ui_language_switcher');
if (!$block) {
  echo "Bloc introuvable.\n";
  exit(1);
}

$block->setVisibilityConfig('request_path', [
  'id' => 'request_path',
  'pages' => '<front>',
  'negate' => FALSE,
]);
$block->save();

echo "Visibilité mise à jour.\n";
$verif = \Drupal::entityTypeManager()->getStorage('block')->load('khartasia_ui_language_switcher');
print_r($verif->getVisibility());
