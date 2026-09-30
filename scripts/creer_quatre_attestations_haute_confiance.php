<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$type_datation = trouver_terme('attestation_type', 'Datation');
if (!$type_datation) {
  echo "ERREUR : type 'Datation' introuvable.\n";
  exit(1);
}

$cas = [
  ['papier_nid' => 333, 'titre' => 'Hosokawashi', 'periodes' => ['Edo'], 'lieu' => 'Ogawa'],
  ['papier_nid' => 348, 'titre' => 'Minoshi', 'periodes' => ['Heian', 'Edo'], 'lieu' => 'Mino'],
  ['papier_nid' => 327, 'titre' => 'Danshi', 'periodes' => ['Nara', 'Heian', 'Edo'], 'lieu' => 'Echizen'],
  ['papier_nid' => 332, 'titre' => 'Hoshogami', 'periodes' => ['Edo'], 'lieu' => 'Echizen'],
];

foreach ($cas as $c) {
  echo "=== {$c['titre']} ===\n";

  $existant = \Drupal::entityQuery('node')
    ->condition('type', 'attestation')
    ->condition('field_att_papier', $c['papier_nid'])
    ->accessCheck(FALSE)
    ->execute();
  if ($existant) {
    echo "  Déjà traité (nid=" . reset($existant) . ") — ignoré.\n\n";
    continue;
  }

  $periode_ids = [];
  foreach ($c['periodes'] as $p) {
    $t = trouver_terme('periode_dynastique', $p);
    if (!$t) { echo "  ERREUR : période '$p' introuvable.\n"; continue 2; }
    $periode_ids[] = ['target_id' => $t->id()];
  }

  $lieu_terme = trouver_terme('zone_historique_production', $c['lieu']);
  if (!$lieu_terme) { echo "  ERREUR : lieu '{$c['lieu']}' introuvable.\n"; continue; }

  $papier = Node::load($c['papier_nid']);
  if (!$papier) { echo "  ERREUR : papier nid={$c['papier_nid']} introuvable.\n"; continue; }

  $att = Node::create([
    'type' => 'attestation',
    'title' => "Datation — {$c['titre']}",
    'field_att_type' => ['target_id' => $type_datation->id()],
    'field_att_papier' => ['target_id' => $c['papier_nid']],
    'field_att_periode' => $periode_ids,
    'field_att_lieu' => ['target_id' => $lieu_terme->id()],
    'field_att_valeur' => "Fabriqué à {$c['lieu']}, période(s) " . implode('-', $c['periodes']) . '.',
    'field_att_statut' => 'attesté',
  ]);
  $att->save();
  echo "  Créé : nid=" . $att->id() . " — période(s) " . implode(', ', $c['periodes']) . " — lieu {$c['lieu']}\n\n";
}

echo "=== Vérification finale ===\n";
$total = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', NULL, 'IS NOT NULL')->accessCheck(FALSE)->count()->execute();
echo "Total attestations avec field_att_papier rempli : $total\n";
