<?php
$plante = \Drupal\node\Entity\Node::load(22);
echo "=== Zones géographiques référencées par Broussonetia (nid=22) ===\n\n";

if (!$plante->hasField('field_zones_geo') || $plante->get('field_zones_geo')->isEmpty()) {
  echo "field_zones_geo est vide sur le nœud 22.\n";
  exit;
}

foreach ($plante->get('field_zones_geo') as $item) {
  $geo = $item->entity;
  if (!$geo) {
    echo "Référence cassée (nœud cible introuvable)\n\n";
    continue;
  }

  echo "--- nid=" . $geo->id() . " : \"" . $geo->label() . "\" ---\n";
  echo "Statut : " . ($geo->isPublished() ? "publié" : "NON PUBLIÉ") . "\n";

  foreach ($geo->getFields() as $name => $field) {
    if (!str_starts_with($name, 'field_')) continue;
    if ($field->isEmpty()) {
      echo "  $name : (vide)\n";
      continue;
    }
    $def = $field->getFieldDefinition();
    if ($def->getType() === 'entity_reference') {
      $target = $field->entity;
      echo "  $name : " . ($target ? $target->label() : '(référence cassée)') . "\n";
    } else {
      $val = $field->value ?? '';
      echo "  $name : " . substr(strip_tags($val), 0, 100) . "\n";
    }
  }
  echo "\n";
}
