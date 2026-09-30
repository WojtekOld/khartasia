<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom, $parent_id = NULL) {
  $props = ['vid' => $vid, 'name' => $nom];
  if ($parent_id !== NULL) $props['parent'] = $parent_id;
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($props);
  return $r ? reset($r) : NULL;
}

echo "=== Zhejiang existe-t-il dans zone_historique_production ? ===\n";
$zhejiang = trouver_terme('zone_historique_production', 'Zhejiang');
echo $zhejiang ? "OUI, tid={$zhejiang->id()}\n" : "NON, à créer.\n";

$chine_zhp = trouver_terme('zone_historique_production', 'Chine');
if (!$zhejiang && $chine_zhp) {
  $zhejiang = Term::create(['vid' => 'zone_historique_production', 'name' => 'Zhejiang', 'parent' => $chine_zhp->id()]);
  $zhejiang->set('field_niveau_zone', 'province_prefecture_do');
  $zhejiang->save();
  echo "Zhejiang créé, tid={$zhejiang->id()}\n";
}

echo "\n=== Création du Papier Shan theng ===\n";
$existant = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Shan theng')->accessCheck(FALSE)->execute();
if ($existant) {
  echo "Déjà existant, nid=" . reset($existant) . "\n";
  $papier_nid = reset($existant);
} else {
  $chine_pays = trouver_terme('pays', 'Chine');
  $papier = Node::create([
    'type' => 'papers',
    'title' => 'Shan theng',
    'langcode' => 'fr',
    'body' => [
      'value' => "Papier de fibres de rotin (Calamus rotang), fabriqué en Chine à partir du 3e siècle à Shanji (Zhejiang), où le rotin était planté le long de la rivière Shanji. Papier le plus populaire sous la dynastie Tang (618-907), utilisé comme tribut local dès le début du 8e siècle. Décrit comme lisse et durable, disponible en plusieurs couleurs — usage administratif codifié par couleur (blanc pour les décrets, bleu pour les offrandes taoïstes, jaune pour les ordres impériaux). Employé pour la calligraphie, la confection de livres, et les sachets à thé (texture hermétique). Production déclinante à partir de la dynastie Song (960-1279), le rotin — plante à croissance lente, non cultivée — s'épuisant, remplacé par le bambou dès le milieu de la dynastie Tang.",
      'format' => 'full_html',
    ],
    'field_genus_pap_entity' => ['target_id' => 2325],
  ]);
  if ($chine_pays) $papier->set('field_origin_taxo', ['target_id' => $chine_pays->id()]);
  $papier->save();
  $papier_nid = $papier->id();
  echo "Créé : nid=$papier_nid\n";
}

echo "\n=== Attestations ===\n";
$type_dat = trouver_terme('attestation_type', 'Datation');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');
$axe_colorant = trouver_terme('attestation_axe', 'Colorant');
$tang = trouver_terme('periode_dynastique', 'Tang');
$song = trouver_terme('periode_dynastique', 'Song');

// Datation : popularité sous les Tang
if ($type_dat && $tang) {
  $existant_att = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', $papier_nid)->condition('field_att_type', $type_dat->id())->accessCheck(FALSE)->execute();
  if (!$existant_att) {
    $att1 = Node::create([
      'type' => 'attestation',
      'title' => 'Datation — Shan theng',
      'field_att_type' => ['target_id' => $type_dat->id()],
      'field_att_papier' => ['target_id' => $papier_nid],
      'field_att_periode' => array_filter([['target_id' => $tang->id()], $song ? ['target_id' => $song->id()] : NULL]),
      'field_att_lieu' => $zhejiang ? ['target_id' => $zhejiang->id()] : NULL,
      'field_att_valeur' => "Origine 3e siècle à Shanji (Zhejiang) ; papier le plus populaire sous les Tang (618-907) ; déclin sous les Song (960-1279) par épuisement du rotin.",
      'field_att_statut' => 'attesté',
    ]);
    $att1->save();
    echo "Datation créée : nid={$att1->id()}\n";
  }
}

// Composition/Colorant : le code couleur administratif, fait rare et précis
if ($type_comp && $axe_colorant) {
  $existant_att2 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', $papier_nid)->condition('field_att_axe', $axe_colorant->id())->accessCheck(FALSE)->execute();
  if (!$existant_att2) {
    $att2 = Node::create([
      'type' => 'attestation',
      'title' => 'Composition (colorant) — Shan theng',
      'field_att_type' => ['target_id' => $type_comp->id()],
      'field_att_axe' => ['target_id' => $axe_colorant->id()],
      'field_att_papier' => ['target_id' => $papier_nid],
      'field_att_valeur' => "Code administratif par couleur : blanc pour les décrets de réquisition et punition, bleu pour les offrandes au temple taoïste de Daijingong, jaune pour les ordres impériaux.",
      'field_att_statut' => 'attesté',
    ]);
    $att2->save();
    echo "Composition (colorant) créée : nid={$att2->id()}\n";
  }
}

// Usage
if ($type_usage) {
  $existant_att3 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', $papier_nid)->condition('field_att_type', $type_usage->id())->accessCheck(FALSE)->execute();
  if (!$existant_att3) {
    $att3 = Node::create([
      'type' => 'attestation',
      'title' => 'Usage — Shan theng',
      'field_att_type' => ['target_id' => $type_usage->id()],
      'field_att_papier' => ['target_id' => $papier_nid],
      'field_att_valeur' => "Calligraphie, confection de livres, sachets à thé (texture ferme et hermétique, propice à la conservation des arômes).",
      'field_att_statut' => 'attesté',
    ]);
    $att3->save();
    echo "Usage créée : nid={$att3->id()}\n";
  }
}

echo "\nTerminé.\n";
