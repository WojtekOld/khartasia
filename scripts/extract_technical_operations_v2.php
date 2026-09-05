<?php
/**
 * @file
 * Extraction des opérations techniques depuis field_mode_prep_ml.
 * CORRIGÉ : field_mode_prep_ml vit sur des nœuds `plante_texte` séparés,
 * liés à la plante via field_plante_ref — pas sur une traduction du
 * node `article` lui-même.
 *
 * Usage: ddev drush scr extract_technical_operations.php [nid]
 */

$nid = $argv[1] ?? null;
$target_nids = $nid ? [$nid] : [22, 32, 14, 25, 1741]; // Broussonetia, Edgeworthia, Hibiscus, Cannabis, Cinnamomum

echo "=== EXTRACTION DES OPÉRATIONS TECHNIQUES (corrigé) ===\n\n";

$canonical_steps = [
    'recolte' => ['récolte', 'récolté', 'coupe', 'coupé', 'arrachage', 'arrachée'],
    'pelage' => ['pelage', 'pelé', 'écorce', 'décortiquer', 'grattage', 'grattée'],
    'etuvage' => ['étuvage', 'étuvé', 'vapeur', 'chaudron', 'cuve fermée'],
    'trempage' => ['trempage', 'trempé', 'immersion', 'immergé'],
    'fermentation' => ['fermentation', 'fermenter', 'fermenté'],
    'cuisson' => ['cuisson', 'cuit', 'bouillir', 'ébouillanté', 'lessive'],
    'blanchiment' => ['blanchiment', 'blanchi', 'soleil', 'neige'],
    'battage' => ['battage', 'battu', 'écrasé', 'maillet', 'pilon', 'mortier', 'meule'],
    'ajout_dispersant' => ['dispersant', 'mucilage', 'agent', 'viscosité'],
    'formation' => ['feuille', 'mise en feuille', 'puisage', 'tamis', 'forme'],
    'pressage' => ['pressage', 'pressé', 'presse', 'charge'],
    'sechage' => ['séchage', 'séché', 'brossage', 'brossé', 'mur chauffé'],
];

foreach ($target_nids as $target_nid) {
    $node = \Drupal\node\Entity\Node::load($target_nid);
    if (!$node || $node->bundle() !== 'article') {
        echo "Nœud $target_nid non trouvé ou non valide\n";
        continue;
    }

    echo "--- " . $node->label() . " (nid=$target_nid) ---\n";

    // Trouver les nœuds plante_texte qui référencent cette plante
    $ids = \Drupal::entityQuery('node')
        ->condition('type', 'plante_texte')
        ->condition('field_plante_ref', $target_nid)
        ->accessCheck(FALSE)
        ->execute();

    if (!$ids) {
        echo "  Aucun texte plante_texte lié à cette plante\n\n";
        continue;
    }

    foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $texte_node) {
        if (!$texte_node->hasField('field_mode_prep_ml') || $texte_node->get('field_mode_prep_ml')->isEmpty()) {
            continue;
        }

        // Résoudre la langue via le terme de taxonomie référencé, pas via Drupal langcode
        $langue = 'inconnue';
        if ($texte_node->hasField('field_langue_texte') && !$texte_node->get('field_langue_texte')->isEmpty()) {
            $terme = $texte_node->get('field_langue_texte')->entity;
            if ($terme) {
                $langue = $terme->label();
            }
        }

        $text = strip_tags($texte_node->get('field_mode_prep_ml')->value);
        echo "  [$langue] (nid=" . $texte_node->id() . ") " . strlen($text) . " caractères\n";

        $found = [];
        foreach ($canonical_steps as $step => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    $found[$step] = $keyword;
                    break;
                }
            }
        }

        if (empty($found)) {
            echo "    Aucune opération standard identifiée\n";
            continue;
        }
        echo "    Opérations identifiées : " . implode(', ', array_keys($found)) . "\n";

        $variants = [];
        foreach (['nagashizuki', 'tamezuki', 'webal', 'ssangbal', 'jangpanji', 'naginata', 'dochim'] as $v) {
            if (stripos($text, $v) !== false) {
                $variants[] = $v;
            }
        }
        if (!empty($variants)) {
            echo "    Variantes régionales : " . implode(', ', $variants) . "\n";
        }
    }
    echo "\n";
}

echo "=== FIN DE L'EXTRACTION ===\n";
