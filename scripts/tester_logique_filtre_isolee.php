<?php
$node = \Drupal\node\Entity\Node::load(22);
$langues_disponibles = array_keys($node->getTranslationLanguages());
echo "Langues disponibles pour ce nœud : " . implode(', ', $langues_disponibles) . "\n\n";

// Simuler des liens comme Drupal les génère : toutes les langues activées
$language_manager = \Drupal::languageManager();
$toutes_langues = array_keys($language_manager->getLanguages());
echo "Toutes les langues activées sur le site : " . implode(', ', $toutes_langues) . "\n\n";

$links_simules = [];
foreach ($toutes_langues as $lc) {
  $links_simules[$lc] = ['title' => $lc, 'url' => NULL];
}

echo "=== Avant filtre (simulé, comme le bloc natif) ===\n";
foreach ($links_simules as $lc => $l) echo "  $lc\n";

echo "\n=== Application manuelle de la logique de filtre (sans passer par le hook/route) ===\n";
foreach ($links_simules as $langcode => $lien) {
  if (!in_array($langcode, $langues_disponibles)) {
    unset($links_simules[$langcode]);
  }
}
foreach ($links_simules as $lc => $l) echo "  $lc\n";

echo "\n=== La fonction hook existe-t-elle vraiment ? ===\n";
echo function_exists('khartasia_ui_language_switch_links_alter') ? "OUI, appelable.\n" : "NON — introuvable.\n";

echo "\n=== Ce hook est-il dans la liste des hooks que Drupal a découverts pour ce thème ? ===\n";
$module_handler = \Drupal::service('theme_handler');
$implementations = \Drupal::moduleHandler()->getModuleList();
// Vérifier via le système de découverte des hooks de thème
$theme_registry = \Drupal::service('theme.registry');
echo "Vérification alternative : recherche directe dans le fichier .theme chargé...\n";
$reflection = new ReflectionFunction('khartasia_ui_language_switch_links_alter');
echo "Fichier source : " . $reflection->getFileName() . "\n";
echo "Ligne : " . $reflection->getStartLine() . "\n";
