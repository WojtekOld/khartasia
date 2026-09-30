<?php
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

function trouver_terme($vid, $nom, $parent_id = NULL) {
  $props = ['vid' => $vid, 'name' => $nom];
  if ($parent_id !== NULL) $props['parent'] = $parent_id;
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($props);
  return $r ? reset($r) : NULL;
}

// === 1. Nouveaux axes ===
foreach (['Méthode de fabrication', 'Dimension / Format'] as $nom) {
  if (!trouver_terme('attestation_axe', $nom)) {
    Term::create(['vid' => 'attestation_axe', 'name' => $nom])->save();
    echo "Axe créé : $nom\n";
  } else {
    echo "Axe déjà présent : $nom\n";
  }
}

$axe_methode = trouver_terme('attestation_axe', 'Méthode de fabrication');
$axe_dimension = trouver_terme('attestation_axe', 'Dimension / Format');
$axe_agent = trouver_terme('attestation_axe', 'Agent de traitement');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');

if (!$axe_agent) { echo "ERREUR : axe 'Agent de traitement' introuvable — vérifier le nom exact.\n"; exit(1); }
if (!$type_comp) { echo "ERREUR : type Composition introuvable.\n"; exit(1); }

// === 2. Attestations pour Maniaigami (nid=1157) ===
$papier_nid = 1157;

// Trouver le vrai nœud Plante par titre, pas un identifiant supposé
$noeud_plante_ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Wikstroemia sikokiania', 'CONTAINS')
  ->accessCheck(FALSE)->execute();
$noeud_plante_id = $noeud_plante_ids ? reset($noeud_plante_ids) : NULL;
if (!$noeud_plante_id) {
  echo "ERREUR : nœud Plante 'Wikstroemia sikokiania' introuvable — vérifier le titre exact.\n";
  exit(1);
}
echo "Nœud Plante trouvé : nid=$noeud_plante_id\n";

$attestations = [
  [
    'titre' => 'Composition (fibre) — Maniaigami',
    'type' => $type_comp->id(),
    'axe' => $axe_fibre ? $axe_fibre->id() : NULL,
    'plante' => $noeud_plante_id,
    'valeur' => 'Fibre structurelle : Wikstroemia sikokiania.',
    'statut' => 'attesté',
  ],
  [
    'titre' => 'Agent de traitement — Maniaigami',
    'type' => $type_comp->id(),
    'axe' => $axe_agent->id(),
    'valeur' => "Argile colorée ajoutée à la pâte, donnant une teinte grise bleutée. Utilisée notamment comme support pour la dorure/argenture (les métaux paraissent plus brillants sur ce fond).",
    'statut' => 'attesté',
  ],
];

if ($axe_methode) {
  $attestations[] = [
    'titre' => 'Méthode de fabrication — Maniaigami',
    'type' => $type_comp->id(),
    'axe' => $axe_methode->id(),
    'valeur' => 'Tamezuki (méthode par eau stagnante, la plus ancienne des deux techniques de formation de feuille japonaises, par opposition à nagashizuki).',
    'statut' => 'attesté',
  ];
}

foreach ($attestations as $a) {
  $existant = \Drupal::entityQuery('node')
    ->condition('type', 'attestation')
    ->condition('field_att_papier', $papier_nid)
    ->condition('title', $a['titre'])
    ->accessCheck(FALSE)->execute();
  if ($existant) {
    echo "{$a['titre']} : déjà existant.\n";
    continue;
  }

  $values = [
    'type' => 'attestation',
    'title' => $a['titre'],
    'field_att_type' => ['target_id' => $a['type']],
    'field_att_papier' => ['target_id' => $papier_nid],
    'field_att_valeur' => $a['valeur'],
    'field_att_statut' => $a['statut'],
  ];
  if (!empty($a['axe'])) $values['field_att_axe'] = ['target_id' => $a['axe']];
  if (!empty($a['plante'])) $values['field_att_plante'] = ['target_id' => $a['plante']];

  $att = Node::create($values);
  $att->save();
  echo "{$a['titre']} : créée, nid=" . $att->id() . "\n";
}

echo "\nNote : le lieu 'Nashio' n'a pas été construit — introuvable dans les sources vérifiées ce soir, laissé de côté plutôt qu'inventé.\n";

echo "\n=== Vérification finale ===\n";
$total = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', $papier_nid)->accessCheck(FALSE)->count()->execute();
echo "Total attestations pour Maniaigami : $total\n";
