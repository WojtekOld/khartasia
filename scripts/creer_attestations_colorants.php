<?php
use Drupal\node\Entity\Node;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$type_comp = trouver_terme('attestation_type', 'Composition');
$axe_agent = trouver_terme('attestation_axe', 'Agent de traitement');

if (!$type_comp || !$axe_agent) {
  echo "ERREUR : type ou axe introuvable.\n";
  exit(1);
}

$cas = [
  [
    'papier_nid' => 349, 'titre' => 'Misugami',
    'valeur' => "Charge de poudre de coquillage (gofun), donnant un papier mou.",
    'statut' => 'attesté',
  ],
  [
    'papier_nid' => 375, 'titre' => 'Udagami',
    'valeur' => "Charge d'argile, rendant le papier souple et stable dimensionnellement (empêche l'extension et la rétraction).",
    'statut' => 'attesté',
  ],
  [
    'papier_nid' => 382, 'titre' => 'Bai jing jian',
    'valeur' => "Fortement chargé ou encollé en surface (le texte source ne tranche pas entre les deux).",
    'statut' => 'probable',
  ],
  [
    'papier_nid' => 483, 'titre' => 'Shengxuan',
    'valeur' => "Papier cru, délibérément sans encollage — absorbe l'eau et l'encre sans résistance. Trait distinctif par contraste avec Shuxuan (même famille, encollé).",
    'statut' => 'attesté',
  ],
  [
    'papier_nid' => 484, 'titre' => 'Shuxuan',
    'valeur' => "Encollé avec gélatine et alun (papier « cuit »), ce qui limite l'absorption d'eau et rigidifie légèrement la feuille.",
    'statut' => 'attesté',
  ],
  [
    'papier_nid' => 1155, 'titre' => 'Hakuuchigami',
    'valeur' => "Charge d'argile donnant une surface très lisse, nécessaire au battage des feuilles d'or et d'argent. Peut aussi recevoir un enduit de cendres de paille mélangées à du blanc d'œuf.",
    'statut' => 'attesté',
  ],
];

foreach ($cas as $c) {
  $existant = \Drupal::entityQuery('node')
    ->condition('type', 'attestation')
    ->condition('field_att_papier', $c['papier_nid'])
    ->condition('field_att_axe', $axe_agent->id())
    ->accessCheck(FALSE)->execute();
  if ($existant) {
    echo "{$c['titre']} : déjà existant.\n";
    continue;
  }

  $att = Node::create([
    'type' => 'attestation',
    'title' => "Agent de traitement — {$c['titre']}",
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_agent->id()],
    'field_att_papier' => ['target_id' => $c['papier_nid']],
    'field_att_valeur' => $c['valeur'],
    'field_att_statut' => $c['statut'],
  ]);
  $att->save();
  echo "{$c['titre']} : créée, nid=" . $att->id() . "\n";
}

echo "\n=== Vérification finale ===\n";
$total = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_axe', $axe_agent->id())
  ->accessCheck(FALSE)->count()->execute();
echo "Total attestations Agent de traitement : $total\n";
