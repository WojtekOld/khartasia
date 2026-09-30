<?php
$node = \Drupal\node\Entity\Node::load(115); // Acheongji

echo "=== 1. Champ body : valeur réelle vs résumé ===\n";
$body_field = $node->get('body');
echo "Valeur (longueur) : " . strlen($body_field->value ?? '') . " caractères\n";
echo "Résumé (summary) : " . var_export($body_field->summary ?? NULL, TRUE) . "\n";
echo "Format : " . ($body_field->format ?? '(aucun)') . "\n";

echo "\n=== Configuration de l'affichage du champ body sur 'papers' ===\n";
$view_display = \Drupal\Core\Entity\Entity\EntityViewDisplay::load('node.papers.default');
$config_body = $view_display->getComponent('body');
print_r($config_body);

echo "\n=== 2. field_genus_pap_entity : combien de valeurs RÉELLEMENT maintenant ? ===\n";
$valeurs = $node->get('field_genus_pap_entity')->getValue();
echo "Nombre de valeurs : " . count($valeurs) . "\n";
foreach ($valeurs as $v) {
  $p = \Drupal\node\Entity\Node::load($v['target_id']);
  echo "  - " . ($p ? $p->label() : "RÉFÉRENCE CASSÉE (target_id={$v['target_id']})") . "\n";
}

echo "\n=== 3. Configuration des labels field_origin_taxo / field_famille_fibreuse ===\n";
foreach (['field_origin_taxo', 'field_famille_fibreuse'] as $champ) {
  $config = $view_display->getComponent($champ);
  echo "$champ : label = " . ($config['label'] ?? '(non défini)') . "\n";
}
