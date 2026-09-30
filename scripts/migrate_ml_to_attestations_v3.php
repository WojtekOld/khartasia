<?php
/**
 * @file
 * Migration des champs multilingues vers des attestations structurées.
 * CORRIGÉ (v3) : $argv n'est pas peuplé par `drush scr` — l'argument réel
 * arrive dans $_SERVER['argv'], après [drush.php, scr, chemin_du_script].
 *
 * Usage: ddev drush scr scripts/migrate_ml_to_attestations_v2.php 22
 */

$cli_args = array_slice($_SERVER['argv'] ?? [], 3);
$nid = $cli_args[0] ?? null;

if (!$nid) {
    echo "Veuillez fournir un NID de plante (bundle article)\n";
    echo "Usage: ddev drush scr scripts/migrate_ml_to_attestations_v2.php [nid]\n";
    exit(1);
}

$node = \Drupal\node\Entity\Node::load($nid);
if (!$node || $node->bundle() !== 'article') {
    echo "Nœud $nid non trouvé ou non valide\n";
    exit(1);
}

echo "=== MIGRATION POUR : " . $node->label() . " (nid=$nid) ===\n\n";

$ids = \Drupal::entityQuery('node')
    ->condition('type', 'plante_texte')
    ->condition('field_plante_ref', $nid)
    ->accessCheck(FALSE)
    ->execute();

if (!$ids) {
    echo "Aucun nœud plante_texte trouvé pour cette plante\n";
    exit(0);
}

$patterns = [
    'recolte' => ['récolte', 'récolté', 'coupe', 'coupé', 'arrachage', 'arrachée', 'novembre', 'décembre'],
    'pelage' => ['pelage', 'pelé', 'écorce', 'décortiquer', 'grattage', 'grattée'],
    'etuvage' => ['étuvage', 'étuvé', 'vapeur', 'chaudron', 'cuve fermée'],
    'cuisson' => ['cuisson', 'cuit', 'bouillir', 'ébouillanté', 'lessive', 'alcaline'],
    'battage' => ['battage', 'battu', 'écrasé', 'maillet', 'pilon', 'mortier', 'meule'],
    'formation' => ['feuille', 'mise en feuille', 'puisage', 'tamis', 'nagashizuki', 'tamezuki'],
];

$sources_connues = ['Guixinzashi', 'Wulixiaoshi', 'Hou Han shu', 'Ojuyeonmunjangjeonsango'];

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $texte_node) {
    $langue = 'inconnue';
    if ($texte_node->hasField('field_langue_texte') && !$texte_node->get('field_langue_texte')->isEmpty()) {
        $terme = $texte_node->get('field_langue_texte')->entity;
        if ($terme) {
            $langue = $terme->label();
        }
    }

    echo "--- Langue : $langue (nid=" . $texte_node->id() . ") ---\n";

    if (!$texte_node->hasField('field_mode_prep_ml') || $texte_node->get('field_mode_prep_ml')->isEmpty()) {
        echo "  Champ field_mode_prep_ml vide sur ce nœud\n\n";
        continue;
    }

    $texte = strip_tags($texte_node->get('field_mode_prep_ml')->value);

    $segments = [];
    foreach ($patterns as $operation => $keywords) {
        foreach ($keywords as $keyword) {
            if (stripos($texte, $keyword) !== false) {
                $segments[] = "$operation (mot-clé: '$keyword')";
                break;
            }
        }
    }
    echo empty($segments) ? "  Aucun segment standard identifié\n" : "  Segments identifiés : " . implode(', ', $segments) . "\n";

    $sources = [];
    if ($texte_node->hasField('field_gen_culture_ml') && !$texte_node->get('field_gen_culture_ml')->isEmpty()) {
        $texte_culture = strip_tags($texte_node->get('field_gen_culture_ml')->value);
        foreach ($sources_connues as $src) {
            if (stripos($texte_culture, $src) !== false) {
                $sources[] = $src;
            }
        }
    }
    if (!empty($sources)) {
        echo "  Sources historiques (field_gen_culture_ml) : " . implode(', ', $sources) . "\n";
    }
    echo "\n";
}

echo "=== MIGRATION SIMULÉE (à adapter selon le schéma Attestation) ===\n";
echo "Pour chaque segment identifié, créer une attestation de type 'technique'\n";
echo "liée à la plante (field_att_plante) et à la source correspondante.\n";
