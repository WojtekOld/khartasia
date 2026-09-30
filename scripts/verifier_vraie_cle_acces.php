<?php
// D'abord, inspecter la structure réelle pour une vue connue comme protégée
$view = \Drupal::entityTypeManager()->getStorage('view')->load('watchdog');
$displays = $view->get('display');
echo "=== Structure réelle de watchdog/page ===\n";
print_r($displays['page']['display_options']['access'] ?? 'AUCUNE CLÉ ACCESS TROUVÉE');

echo "\n\n=== Nouvelle vérification, avec la bonne clé ===\n";
$views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
foreach ($views as $vid => $view) {
  if (!$view->status()) continue;
  $displays = $view->get('display');
  foreach ($displays as $display_id => $display) {
    $access = $display['display_options']['access'] ?? NULL;
    $type_acces = $access['type'] ?? 'HÉRITÉ_DE_DEFAULT_OU_ABSENT';
    if ($type_acces === 'none') {
      echo "$vid / $display_id : accès=none (vraiment ouvert)\n";
    }
  }
}
