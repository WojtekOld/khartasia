<?php
/**
 * @file
 * Création du vocabulaire Technique dans Drupal.
 * 
 * Usage: ddev drush scr scripts/create_technical_vocabulary.php
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

echo "=== CRÉATION DU VOCABULAIRE TECHNIQUE ===\n\n";

// 1. Créer le vocabulaire
$vid = 'technical_operations';
$vocabulary = Vocabulary::load($vid);

if (!$vocabulary) {
    $vocabulary = Vocabulary::create([
        'vid' => $vid,
        'name' => 'Opérations techniques',
        'description' => 'Étapes et variantes des procédés papetiers (vocabulaire hiérarchique)',
        'weight' => 0,
    ]);
    $vocabulary->save();
    echo "✅ Vocabulaire '$vid' créé\n";
} else {
    echo "ℹ️ Vocabulaire '$vid' existe déjà\n";
}

// 2. Structure hiérarchique des opérations
$structure = [
    'recolte' => [
        'name' => 'Récolte',
        'children' => [
            'recolte_printemps' => ['name' => 'Récolte de printemps'],
            'recolte_automne' => ['name' => 'Récolte d\'automne'],
            'recolte_hiver' => ['name' => 'Récolte d\'hiver'],
        ],
    ],
    'preparation' => [
        'name' => 'Préparation des fibres',
        'children' => [
            'pelage' => ['name' => 'Pelage'],
            'decorticage' => ['name' => 'Décorticage'],
            'etuvage' => ['name' => 'Étuvage'],
            'trempage' => ['name' => 'Trempage'],
            'fermentation' => ['name' => 'Fermentation'],
        ],
    ],
    'cuisson' => [
        'name' => 'Cuisson alcaline',
        'children' => [
            'cuisson_cendres' => ['name' => 'Lessive de cendres'],
            'cuisson_soude' => ['name' => 'Lessive de soude (NaOH)'],
            'cuisson_chaux' => ['name' => 'Lait de chaux'],
            'cuisson_carbonate' => ['name' => 'Carbonate de sodium'],
        ],
    ],
    'blanchiment' => [
        'name' => 'Blanchiment',
        'children' => [
            'blanchiment_soleil' => ['name' => 'Exposition au soleil'],
            'blanchiment_neige' => ['name' => 'Exposition sur neige'],
            'blanchiment_eau' => ['name' => 'Immersion dans l\'eau courante'],
        ],
    ],
    'battage' => [
        'name' => 'Battage/Défibration',
        'children' => [
            'battage_mortier' => ['name' => 'Mortier et pilon'],
            'battage_maillets' => ['name' => 'Maillets/battoirs en bois'],
            'battage_meule' => ['name' => 'Meule animale'],
            'battage_pile' => ['name' => 'Pile hollandaise'],
            'battage_naginata' => ['name' => 'Naginata (pile japonaise)'],
        ],
    ],
    'formation' => [
        'name' => 'Formation de la feuille',
        'children' => [
            'nagashizuki' => ['name' => 'Nagashizuki (couches successives)'],
            'tamezuki' => ['name' => 'Tamezuki (prise unique)'],
            'webal' => ['name' => 'Webal (coréen, 2 couches)'],
            'ssangbal' => ['name' => 'Ssangbal (coréen, nagashizuki adapté)'],
            'jangpanji' => ['name' => 'Jangpanji (coréen, grand format)'],
        ],
    ],
    'pressage' => [
        'name' => 'Pressage',
        'children' => [
            'pressage_charge' => ['name' => 'Sous charge/poids'],
            'pressage_levier' => ['name' => 'Presse à levier'],
        ],
    ],
    'sechage' => [
        'name' => 'Séchage',
        'children' => [
            'sechage_bois' => ['name' => 'Brossage sur planche de bois'],
            'sechage_mur' => ['name' => 'Séchage sur mur chauffé'],
            'sechage_metal' => ['name' => 'Plaques métalliques chauffantes'],
            'sechage_soleil' => ['name' => 'Séchage au soleil'],
        ],
    ],
    'finitions' => [
        'name' => 'Finitions',
        'children' => [
            'rognage' => ['name' => 'Rognage'],
            'calandrage' => ['name' => 'Calandrage/Dochim'],
            'tri' => ['name' => 'Tri'],
        ],
    ],
];

// 3. Créer les termes
$parent_map = [];

foreach ($structure as $key => $parent_data) {
    // Créer ou charger le parent
    $parent_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $parent_data['name'], 'vid' => $vid]);
    $parent = reset($parent_term);
    
    if (!$parent) {
        $parent = Term::create([
            'vid' => $vid,
            'name' => $parent_data['name'],
            'weight' => 0,
        ]);
        $parent->save();
        echo "  ✅ Parent: {$parent_data['name']}\n";
    }
    
    // Créer les enfants
    foreach ($parent_data['children'] as $child_key => $child_data) {
        $child_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $child_data['name'], 'vid' => $vid]);
        $child = reset($child_term);
        
        if (!$child) {
            $child = Term::create([
                'vid' => $vid,
                'name' => $child_data['name'],
                'parent' => $parent->id(),
                'weight' => 0,
            ]);
            $child->save();
            echo "    ✅ Enfant: {$child_data['name']}\n";
        }
    }
}

echo "\n=== FIN DE LA CRÉATION ===\n";