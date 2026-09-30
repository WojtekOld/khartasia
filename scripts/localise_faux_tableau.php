<?php
// Broussonetia papyrifera (nid=22) — sa fiche plante_fibre liée (nid=3829)
// contient déjà des champs structurés très proches du contenu vu dans la capture.
// On vérifie où vit le faux tableau HTML sur le nœud Plantes lui-même.

$node = \Drupal\node\Entity\Node::load(22);
echo "=== Nœud 22 : " . $node->label() . " ===\n\n";

foreach ($node->getFields() as $name => $field) {
  if (str_starts_with($name, 'field_') || $name === 'body') {
    if (!$field->isEmpty()) {
      $def = $field->getFieldDefinition();
      $type = $def->getType();
      if (in_array($type, ['text_long', 'text_with_summary', 'string_long'])) {
        $val = $field->value ?? '';
        $contient_tableau = (stripos($val, '<table') !== false || stripos($val, 'Longueur moyenne') !== false || stripos($val, 'Herzberg') !== false);
        echo "[$name] (" . $type . ") " . strlen($val) . " caractères"
          . ($contient_tableau ? " <-- CONTIENT PROBABLEMENT LE TABLEAU" : "") . "\n";
      }
    }
  }
}

echo "\n=== Aperçu du champ body, si présent ===\n";
if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
  echo substr($node->get('body')->value, 0, 2000) . "\n";
} else {
  echo "Champ body vide ou absent.\n";
}
