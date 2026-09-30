<?php
use Drupal\views\Entity\View;

$view = View::load('recherche_khartasia');
if (!$view) {
  echo "Vue 'recherche_khartasia' introuvable.\n";
  exit;
}

$display = $view->getDisplay('default');

echo "=== Base table ===\n";
echo $view->get('base_table') . "\n";

echo "\n=== Filtres ===\n";
foreach ($display['display_options']['filters'] ?? [] as $id => $f) {
  echo "  - $id [table: {$f['table']}, champ: {$f['field']}, plugin: {$f['plugin_id']}]\n";
}

echo "\n=== Champs exposés à la recherche (filtre 'search' ou 'fulltext' généralement) ===\n";
foreach ($display['display_options']['filters'] ?? [] as $id => $f) {
  if (str_contains($f['plugin_id'] ?? '', 'search') || str_contains($id, 'search') || str_contains($id, 'keys')) {
    echo "  Filtre de recherche trouvé : $id\n";
    print_r($f);
  }
}

echo "\n=== Utilise-t-elle Search API (index dédié) ou une simple requête SQL sur titre ? ===\n";
echo "Base table utilisée : " . $view->get('base_table') . "\n";
echo (str_contains($view->get('base_table'), 'search_api') ? "Search API : OUI — index dédié, potentiellement extensible.\n" : "Search API : NON — probablement limité au titre des nœuds via SQL simple.\n");
