<?php
/**
 * @file
 * Détection des écarts inter-linguistiques dans field_mode_prep_ml.
 * CORRIGÉ : requête sur les nœuds plante_texte réels (bundle plante_texte),
 * pas sur bundle='article' qui ne porte pas ce champ.
 *
 * Usage: ddev drush scr check_interlingual_gaps_v2.php
 */

echo "=== CONTRÔLE DES ÉCARTS INTER-LINGUISTIQUES (corrigé) ===\n\n";

// Toutes les plantes (article) qui ont au moins un plante_texte lié
$plante_ids = \Drupal::entityQuery('node')
    ->condition('type', 'article')
    ->accessCheck(FALSE)
    ->range(0, 30)
    ->execute();

$results = [];

foreach ($plante_ids as $plante_id) {
    $plante = \Drupal\node\Entity\Node::load($plante_id);
    if (!$plante) {
        continue;
    }

    $texte_ids = \Drupal::entityQuery('node')
        ->condition('type', 'plante_texte')
        ->condition('field_plante_ref', $plante_id)
        ->condition('field_mode_prep_ml', '', '<>')
        ->accessCheck(FALSE)
        ->execute();

    if (count($texte_ids) < 2) {
        continue; // il faut au moins 2 langues pour comparer
    }

    $langues = [];
    $longueurs = [];
    foreach (\Drupal\node\Entity\Node::loadMultiple($texte_ids) as $texte_node) {
        $langue = 'inconnue';
        if ($texte_node->hasField('field_langue_texte') && !$texte_node->get('field_langue_texte')->isEmpty()) {
            $terme = $texte_node->get('field_langue_texte')->entity;
            if ($terme) {
                $langue = $terme->label();
            }
        }
        $texte = strip_tags($texte_node->get('field_mode_prep_ml')->value);
        $langues[] = $langue;
        $longueurs[$langue] = strlen($texte);
    }

    $results[] = [
        'nid' => $plante_id,
        'label' => $plante->label(),
        'langues' => $langues,
        'longueurs' => $longueurs,
    ];
}

echo "Plantes avec données multilingues (≥2 langues) : " . count($results) . "\n\n";

foreach ($results as $result) {
    echo "--- " . $result['label'] . " (nid=" . $result['nid'] . ") ---\n";
    echo "Langues : " . implode(', ', $result['langues']) . "\n";

    $min = min($result['longueurs']);
    foreach ($result['longueurs'] as $langue => $len) {
        $bar = str_repeat('#', max(1, round($len / 100)));
        echo "  $langue : $len caractères $bar\n";
    }
    foreach ($result['longueurs'] as $langue => $len) {
        if ($min > 0 && $len > $min * 2) {
            echo "  Écart notable : $langue est significativement plus long que les autres\n";
        }
    }
    echo "\n";
}

echo "=== FIN DU CONTRÔLE ===\n";
