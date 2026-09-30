<?php

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * ============================================================================
 * CONTENT TYPE "ATTESTATION" — patron générique de conception Khartasia
 * ============================================================================
 * Construit à l'issue d'une longue discussion de conception (session du
 * 06/09/2026). Principe : plutôt que de créer un nouveau type de contenu
 * satellite à chaque nouveau besoin analytique (composition, usage,
 * géographie, circuit commercial, dégradation, critères morphologiques),
 * un seul type générique porte toute affirmation SOURCÉE, DATÉE, avec un
 * STATUT DE CERTITUDE explicite — pour éviter qu'une donnée non fiable
 * (comme "variable" recopié sur 49 fiches field_pf_graff_c) se glisse
 * sans qu'on puisse jamais la distinguer d'une vraie donnée vérifiée.
 *
 * Ce mécanisme NE REMPLACE PAS les types satellites existants
 * (plante_texte, plante_geo, plante_fibre) — ils restent inchangés.
 * Il s'applique aux BESOINS FUTURS de même nature épistémique.
 *
 * Paragraphs a été explicitement écarté (déjà retiré de Wallpaper Canton
 * pour manque de souplesse relationnelle) — tout ici repose sur des
 * entity_reference classiques entre nœuds/termes, le patron déjà éprouvé
 * partout ailleurs dans Khartasia.
 * ============================================================================
 */

echo "=== ÉTAPE 1 : Vocabulaire 'Zone historique de production' ===\n";
// Distinct de field_origin_taxo (zone de croissance de la plante).
// Celui-ci sert à documenter OÙ un papier a été historiquement fabriqué,
// avec la possibilité d'être daté (une "Sichuan avant 1997" n'est pas le
// même territoire qu'une "Sichuan depuis 1997", suite à la création de
// Chongqing — cas vérifié ce soir, comme Mino/Gifu au Japon en 1876).
if (!Vocabulary::load('zone_historique_production')) {
  Vocabulary::create([
    'vid' => 'zone_historique_production',
    'name' => 'Zone historique de production',
    'description' => 'Lieu de fabrication attesté d\'un papier — distinct de la zone de croissance naturelle de la plante (field_origin_taxo, sur Plantes).',
  ])->save();
  echo "  Créé.\n";
} else {
  echo "  Déjà existant.\n";
}

// Amorçage avec la Chine et ses provinces (déjà vérifiées dans les textes
// réels du corpus ce soir — pas des exemples inventés).
$pays_seed = [
  'Chine' => ['Anhui', 'Fujian', 'Gansu', 'Guangdong', 'Guangxi', 'Guizhou', 'Hainan', 'Hebei', 'Hénan', 'Hubei', 'Hunan', 'Jiangsu', 'Jiangxi', 'Shaanxi', 'Shandong', 'Shanxi', 'Sichuan', 'Xizang/Tibet', 'Yunnan', 'Zhejiang'],
  'Corée' => [],
  'Japon' => [],
  'Thaïlande' => [],
];
foreach ($pays_seed as $pays => $provinces) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'zone_historique_production', 'name' => $pays, 'parent' => 0]);
  $pays_term = $existing ? reset($existing) : Term::create(['vid' => 'zone_historique_production', 'name' => $pays]);
  if (!$existing) $pays_term->save();
  foreach ($provinces as $prov) {
    $existing_prov = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'zone_historique_production', 'name' => $prov]);
    if (!$existing_prov) {
      Term::create(['vid' => 'zone_historique_production', 'name' => $prov, 'parent' => $pays_term->id()])->save();
    }
  }
}
echo "  Amorcé : Chine + " . count($pays_seed['Chine']) . " provinces, Corée/Japon/Thaïlande (vides, à compléter).\n\n";

echo "=== ÉTAPE 2 : Vocabulaire 'Axe d'attestation' ===\n";
// Sous-typage à l'intérieur de chaque field_att_type. Par exemple pour
// une Attestation de composition : est-ce la fibre principale, un
// colorant, ou un agent de traitement ? Pour une Attestation de critère
// morphologique : quel critère précisément (longueur, parois, etc.) ?
// Un seul vocabulaire pour tous les types, à plat — les valeurs ne se
// recoupent pas entre types, pas besoin de hiérarchie ici.
if (!Vocabulary::load('attestation_axe')) {
  Vocabulary::create([
    'vid' => 'attestation_axe',
    'name' => 'Axe d\'attestation',
    'description' => 'Sous-catégorie précise à l\'intérieur d\'un type d\'Attestation (ex : "longueur" pour un critère morphologique, "colorant" pour une composition).',
  ])->save();
  echo "  Créé.\n";
} else {
  echo "  Déjà existant.\n";
}

$axes_seed = [
  // Axe composition — distingue fibre principale, colorant, agent de traitement
  // (distinction nécessaire depuis la découverte que field_genus_pap_entity
  // mélange plantes à fibre ET plantes tinctoriales sans le dire).
  'Fibre principale', 'Colorant', 'Agent de traitement',
  // Axe critère morphologique — les 9 lignes de la matrice de comparaison
  // (modèle proposé ce soir, calqué sur les colonnes Herzberg/Graff C
  // déjà en place sur plante_fibre).
  'Type de fibre', 'Longueur', 'Largeur', 'Parois cellulaires', 'Surface de la fibre',
  'Stries et dislocations', 'Extrémités', 'Éléments associés', 'Coloration Herzberg', 'Coloration Graff C',
];
foreach ($axes_seed as $nom) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'attestation_axe', 'name' => $nom]);
  if (!$existing) {
    Term::create(['vid' => 'attestation_axe', 'name' => $nom])->save();
  }
}
echo "  Amorcé : " . count($axes_seed) . " axes (composition + critères morphologiques).\n\n";

echo "=== ÉTAPE 3 : Vérification de Bibcite (source bibliographique) ===\n";
// Bibcite est confirmé installé (composer.json) mais son activation
// réelle n'avait jamais été vérifiée ce soir — on le fait maintenant,
// avec repli automatique en champ texte si le module n'est pas actif,
// pour ne jamais bloquer la construction sur cette inconnue.
$bibcite_disponible = \Drupal::moduleHandler()->moduleExists('bibcite_entity')
  && \Drupal::entityTypeManager()->hasDefinition('bibcite_reference');
echo "  Bibcite disponible : " . ($bibcite_disponible ? "OUI — field_att_source sera une référence Bibcite" : "NON — field_att_source sera un champ texte de repli") . "\n\n";

echo "=== ÉTAPE 4 : Content type 'Attestation' ===\n";
if (!NodeType::load('attestation')) {
  NodeType::create([
    'type' => 'attestation',
    'name' => 'Attestation',
    'description' => 'Affirmation sourcée, datée, avec statut de certitude explicite. Patron générique réutilisable pour tout nouvel axe analytique (composition, usage, géographie, circuit, dégradation, critères morphologiques...).',
  ])->save();
  echo "  Créé.\n";
} else {
  echo "  Déjà existant.\n";
}

/**
 * Définition des champs. Chaque champ porte un commentaire expliquant
 * son rôle — à relire avant de configurer l'affichage du formulaire,
 * pour ne pas avoir à redécouvrir cette logique plus tard.
 */
$champs = [

  // --- Typage ---
  'field_att_type' => [
    'type' => 'list_string',
    'label' => 'Type d\'attestation',
    'desc' => 'Le grand axe concerné : usage, géographie, composition, circuit commercial, dégradation matérielle, ou critère morphologique.',
    'settings' => ['allowed_values' => [
      'usage' => 'Usage', 'geographie' => 'Géographie', 'composition' => 'Composition',
      'circuit' => 'Circuit commercial', 'degradation' => 'Dégradation matérielle',
      'critere_morphologique' => 'Critère morphologique',
    ]],
    'required' => TRUE,
  ],
  'field_att_axe' => [
    'type' => 'entity_reference',
    'label' => 'Axe précis',
    'desc' => 'Sous-catégorie à l\'intérieur du type (ex : "Longueur" si type=critere_morphologique). Voir vocabulaire attestation_axe.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['attestation_axe'],
    'cardinality' => 1,
  ],

  // --- Ce sur quoi porte l'attestation ---
  'field_att_papier' => [
    'type' => 'entity_reference',
    'label' => 'Papier concerné',
    'desc' => 'Le papier (content type "papers") auquel cette attestation se rapporte. Une Attestation porte sur UN SEUL papier (cardinalité 1) — comparer plusieurs papiers est le rôle du moteur d\'identification, pas de l\'entité elle-même.',
    'target_type' => 'node',
    'target_bundles' => ['papers'],
    'cardinality' => 1,
  ],
  'field_att_plante' => [
    'type' => 'entity_reference',
    'label' => 'Plante concernée',
    'desc' => 'La plante (content type "article") concernée, si applicable (notamment pour composition et critère morphologique).',
    'target_type' => 'node',
    'target_bundles' => ['article'],
    'cardinality' => 1,
  ],

  // --- La donnée elle-même ---
  'field_att_valeur' => [
    'type' => 'string_long',
    'label' => 'Valeur observée',
    'desc' => 'Le contenu de l\'affirmation elle-même. Reste un texte libre (pas de champ numérique strict) : le format varie selon l\'axe ("6-20mm, moyenne 10mm" pour une longueur ; "Bleu foncé" pour une coloration) et forcer un type unique casserait des cas réels déjà rencontrés ce soir.',
  ],

  // --- Traçabilité de la source ---
  'field_att_type_source' => [
    'type' => 'list_string',
    'label' => 'Type de source',
    'desc' => 'Nature de la source de cette affirmation.',
    'settings' => ['allowed_values' => [
      'analyse' => 'Analyse (laboratoire, microscopie...)', 'texte_historique' => 'Texte historique',
      'temoignage' => 'Témoignage d\'artisan', 'enquete_terrain' => 'Enquête de terrain',
      'expertise' => 'Expertise professionnelle',
    ]],
  ],

  // --- Certitude ---
  'field_att_statut' => [
    'type' => 'list_string',
    'label' => 'Statut épistémique',
    'desc' => 'Degré de certitude de cette affirmation. OBLIGATOIRE — c\'est le garde-fou qui aurait empêché une valeur comme "variable" de se répandre sur 49 fiches sans que personne ne s\'en aperçoive (cas réel : field_pf_graff_c).',
    'settings' => ['allowed_values' => [
      'atteste' => 'Attesté', 'probable' => 'Probable', 'hypothetique' => 'Hypothétique',
      'refute' => 'Réfuté', 'inconnu' => 'Inconnu / à vérifier',
    ]],
    'required' => TRUE,
  ],

  // --- Datation ---
  'field_att_periode_debut' => ['type' => 'integer', 'label' => 'Période — début (année)', 'desc' => 'Année de début de validité de cette affirmation.'],
  'field_att_periode_fin' => ['type' => 'integer', 'label' => 'Période — fin (année)', 'desc' => 'Année de fin, vide si toujours en vigueur ou non déterminée.'],
  'field_att_approx' => ['type' => 'boolean', 'label' => 'Dates approximatives', 'desc' => 'Coché si les années ci-dessus sont des estimations, pas des dates précises.'],

  // --- Localisation ---
  'field_att_lieu' => [
    'type' => 'entity_reference',
    'label' => 'Lieu',
    'desc' => 'Zone historique de production concernée (voir vocabulaire zone_historique_production — PAS field_origin_taxo, qui reste réservé à la zone de croissance des plantes).',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['zone_historique_production'],
    'cardinality' => 1,
  ],

  // --- Mécanisme d'écart : DEUX sources qui se contredisent sur LE MÊME fait ---
  'field_att_concurrente' => [
    'type' => 'entity_reference',
    'label' => 'Attestation(s) concurrente(s)',
    'desc' => 'Lien vers une autre Attestation portant sur le MÊME fait (pas juste un fait similaire). Réservé au désaccord de sources, pas à la ressemblance entre espèces différentes (voir field_att_comparaison_ref ci-dessous pour ça).',
    'target_type' => 'node',
    'target_bundles' => ['attestation'],
    'cardinality' => -1,
  ],
  'field_att_relation_ecart' => [
    'type' => 'list_string',
    'label' => 'Nature de l\'écart',
    'desc' => 'Comment cette attestation se positionne par rapport à sa concurrente.',
    'settings' => ['allowed_values' => [
      'convergence' => 'Convergence', 'divergence' => 'Divergence',
      'contradiction' => 'Contradiction', 'silence' => 'Silence (absence attendue)',
    ]],
  ],
  'field_att_commentaire_ecart' => [
    'type' => 'string_long',
    'label' => 'Commentaire sur l\'écart',
    'desc' => 'Le "pourquoi" de la divergence/contradiction — une donnée de première importance, pas un détail annexe.',
  ],

  // --- Comparaison : ressemblance entre ESPÈCES DIFFÉRENTES, distinct de l'écart ci-dessus ---
  'field_att_comparaison_ref' => [
    'type' => 'entity_reference',
    'label' => 'Référence(s) de comparaison',
    'desc' => 'Autre(s) fiche(s) plante_fibre servant de point de comparaison ("proche du chanvre ou du lin"). Distinct du mécanisme d\'écart : ceci compare deux ESPÈCES différentes, pas deux sources en désaccord sur le même fait.',
    'target_type' => 'node',
    'target_bundles' => ['plante_fibre'],
    'cardinality' => -1,
  ],
  'field_att_comparaison_note' => [
    'type' => 'string_long',
    'label' => 'Note de comparaison',
    'desc' => 'Le texte de comparaison lui-même ("Beaucoup plus longue que les feuillus (~1 mm)").',
  ],
];

foreach ($champs as $name => $def) {
  // Stockage du champ
  if (!FieldStorageConfig::loadByName('node', $name)) {
    $storage_def = [
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $def['type'],
    ];
    if ($def['type'] === 'entity_reference') {
      $storage_def['settings'] = ['target_type' => $def['target_type']];
      $storage_def['cardinality'] = $def['cardinality'] ?? 1;
    }
    FieldStorageConfig::create($storage_def)->save();
  }

  // Instance du champ sur le bundle attestation
  if (!FieldConfig::loadByName('node', 'attestation', $name)) {
    $field_def = [
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'attestation',
      'label' => $def['label'],
      'description' => $def['desc'], // <-- visible dans le formulaire d'édition Drupal, sous le champ
      'required' => $def['required'] ?? FALSE,
    ];
    if (isset($def['settings'])) {
      $field_def['settings'] = $def['settings'];
    }
    if ($def['type'] === 'entity_reference' && isset($def['target_bundles'])) {
      $field_def['settings']['handler_settings'] = [
        'target_bundles' => array_combine($def['target_bundles'], $def['target_bundles']),
      ];
    }
    FieldConfig::create($field_def)->save();
    echo "  Champ créé : $name ({$def['label']})\n";
  } else {
    echo "  Champ déjà existant : $name\n";
  }
}

// Si Bibcite est confirmé disponible, on ajoute field_att_source en plus
// des champs ci-dessus (fait séparément car son type dépend du contexte).
if (!FieldStorageConfig::loadByName('node', 'field_att_source')) {
  if ($bibcite_disponible) {
    FieldStorageConfig::create([
      'field_name' => 'field_att_source', 'entity_type' => 'node', 'type' => 'entity_reference',
      'settings' => ['target_type' => 'bibcite_reference'],
    ])->save();
  } else {
    FieldStorageConfig::create([
      'field_name' => 'field_att_source', 'entity_type' => 'node', 'type' => 'string_long',
    ])->save();
  }
}
if (!FieldConfig::loadByName('node', 'attestation', 'field_att_source')) {
  FieldConfig::create([
    'field_name' => 'field_att_source', 'entity_type' => 'node', 'bundle' => 'attestation',
    'label' => 'Source', 'required' => TRUE,
    'description' => $bibcite_disponible
      ? 'Référence bibliographique Bibcite. OBLIGATOIRE — toute attestation doit être sourcée.'
      : 'Référence de la source (Bibcite non actif : texte libre en repli). OBLIGATOIRE — toute attestation doit être sourcée.',
  ])->save();
  echo "  Champ créé : field_att_source (Source" . ($bibcite_disponible ? ", via Bibcite" : ", texte de repli") . ")\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "\n=== TERMINÉ ===\n";
echo "Prochaine étape : configurer l'affichage du formulaire (admin/structure/types/manage/attestation/form-display)\n";
echo "et l'affichage de vue, comme fait ce soir pour plante_texte.\n";
