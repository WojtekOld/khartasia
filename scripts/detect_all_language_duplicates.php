<?php
echo "=== RECHERCHE DE DOUBLONS DE LANGUE SUR TOUT LE CORPUS plante_texte ===\n\n";

$ids = \Drupal::entityQuery('node')
    ->condition('type', 'plante_texte')
    ->accessCheck(FALSE)
    ->execute();

echo "Total plante_texte : " . count($ids) . "\n\n";

// Regrouper par (plante référencée, langue)
$groupes = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
    if (!$node->hasField('field_plante_ref') || $node->get('field_plante_ref')->isEmpty()) {
        continue;
    }
    $plante_id = $node->get('field_plante_ref')->target_id;

    $langue = 'inconnue';
    if ($node->hasField('field_langue_texte') && !$node->get('field_langue_texte')->isEmpty()) {
        $terme = $node->get('field_langue_texte')->entity;
        if ($terme) {
            $langue = $terme->label();
        }
    }

    $cle = $plante_id . '|' . $langue;
    $groupes[$cle][] = $node->id();
}

$doublons = array_filter($groupes, fn($v) => count($v) > 1);

echo "Groupes (plante, langue) en double : " . count($doublons) . "\n\n";

foreach ($doublons as $cle => $nids) {
    [$plante_id, $langue] = explode('|', $cle);
    $plante = \Drupal\node\Entity\Node::load($plante_id);
    $label = $plante ? $plante->label() : "plante $plante_id introuvable";
    echo "$label [$langue] : nids " . implode(', ', $nids) . "\n";
}

echo "\n=== FIN ===\n";
