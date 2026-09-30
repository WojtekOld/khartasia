<?php
$node = \Drupal\node\Entity\Node::load(22); // Broussonetia papyrifera

echo "=== field_tax_genus vs field_tax_family sur ce nœud : références indépendantes ? ===\n";
$genus_tid = $node->get('field_tax_genus')->target_id;
$family_tid = $node->get('field_tax_family')->target_id;
echo "field_tax_genus pointe vers tid=$genus_tid\n";
echo "field_tax_family pointe vers tid=$family_tid\n";

$genus_term = \Drupal\taxonomy\Entity\Term::load($genus_tid);
$genus_parent = array_column($genus_term->get('parent')->getValue(), 'target_id');
echo "Le PARENT du terme genus (tid=$genus_tid) est : " . implode(',', $genus_parent) . "\n";
echo "Est-ce le même que field_tax_family (tid=$family_tid) ? " . (in_array($family_tid, $genus_parent) ? "OUI — dérivé de la hiérarchie" : "NON — référence indépendante") . "\n";

echo "\n=== La facette de recherche 'field_tax_family' utilise-t-elle la hiérarchie ? ===\n";
$view = \Drupal\views\Entity\View::load('recherche_khartasia');
$filtre = $view->getDisplay('default')['display_options']['filters']['field_tax_family'] ?? NULL;
echo "Réglage 'hierarchy' : " . var_export($filtre['hierarchy'] ?? NULL, TRUE) . "\n";

echo "\n=== Autres nœuds Plantes : field_tax_family est-il TOUJOURS indépendant du parent de field_tax_genus ? ===\n";
$ids = \Drupal::entityQuery('node')->condition('type', 'article')->range(0, 10)->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  if (!$n->hasField('field_tax_genus') || $n->get('field_tax_genus')->isEmpty()) continue;
  $g = $n->get('field_tax_genus')->target_id;
  $f = $n->hasField('field_tax_family') ? $n->get('field_tax_family')->target_id : NULL;
  $gt = \Drupal\taxonomy\Entity\Term::load($g);
  $gp = $gt ? array_column($gt->get('parent')->getValue(), 'target_id') : [];
  $coherent = in_array($f, $gp);
  echo "  {$n->label()} : genus_parent=" . implode(',', $gp) . " family_field=$f — " . ($coherent ? "cohérent (dérivé)" : "indépendant") . "\n";
}
