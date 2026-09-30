<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$chine = trouver_terme('pays', 'Chine');
$song = trouver_terme('periode_dynastique', 'Song');
$ming = trouver_terme('periode_dynastique', 'Ming');
$qing = trouver_terme('periode_dynastique', 'Qing');
$yanshan = trouver_terme('zone_historique_production', 'Xian de Yanshan');
$type_dat = trouver_terme('attestation_type', 'Datation');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');
$axe_methode = trouver_terme('attestation_axe', 'Méthode de fabrication');

$bambou_nid = 446; // Phyllostachys edulis
$murier_nid = 22;  // Broussonetia papyrifera

// === 1. Création de la fiche Liansi ===
$existant = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Liansi')->accessCheck(FALSE)->execute();
if ($existant) {
  $liansi_nid = reset($existant);
  echo "Liansi déjà existant, nid=$liansi_nid\n";
} else {
  $liansi = Node::create([
    'type' => 'papers',
    'title' => 'Liansi',
    'langcode' => 'fr',
    'status' => 0,
    'body' => [
      'value' => "Papier de bambou (jeunes pousses) produit dans le xian de Yanshan (铅山县), au nord-est du Jiangxi — ateliers historiquement concentrés à Shitang, Chenfang et Hengjiang. Réputé pour sa finesse, sa blancheur durable sans jaunissement, sa résistance aux insectes et sa longévité exceptionnelle — surnommé « papier à la longévité millénaire » (寿纸千年). A notamment servi à la copie du Siku Quanshu sous le règne Qianlong (Qing). Technique de fabrication inscrite en 2006 sur la première liste du patrimoine culturel immatériel national chinois. Le mûrier était parfois ajouté sur demande du client, à la manière du papier Xuan voisin d'Anhui.",
      'format' => 'full_html',
    ],
    'field_genus_pap_entity' => [
      ['target_id' => $bambou_nid],
      ['target_id' => $murier_nid],
    ],
    'field_pap_local_scripture' => [['value' => '连四纸']],
    'field_pap_synonyme' => [
      ['value' => 'papier des quatre liens'],
      ['value' => '寿纸千年 (papier à la longévité millénaire)'],
    ],
  ]);
  if ($chine) $liansi->set('field_origin_taxo', ['target_id' => $chine->id()]);
  $liansi->save();
  $liansi_nid = $liansi->id();
  echo "Liansi créé, nid=$liansi_nid\n";
}

$compteur = 0;

// Datation
if ($type_dat) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Datation — Liansi',
    'field_att_type' => ['target_id' => $type_dat->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_periode' => array_filter([['target_id' => $song->id()], $ming ? ['target_id' => $ming->id()] : NULL, $qing ? ['target_id' => $qing->id()] : NULL]),
    'field_att_lieu' => $yanshan ? ['target_id' => $yanshan->id()] : NULL,
    'field_att_valeur' => "Tradition attestée depuis la dynastie Song. Usage notable sous les Qing : copie du Siku Quanshu (règne Qianlong). Technique inscrite en 2006 au patrimoine culturel immatériel national.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save(); $compteur++;
}

// Composition — Fibre principale : bambou
if ($type_comp && $axe_fibre) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Fibre principale) — Liansi',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_plante' => ['target_id' => $bambou_nid],
    'field_att_valeur' => "Fibre structurelle essentielle : bambou (jeunes pousses), donnant un papier plus résistant à l'humidité et aux insectes que le mûrier, mais aussi plus rigide.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save(); $compteur++;

  // Fibre secondaire optionnelle : mûrier
  $att2 = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Fibre secondaire) — Liansi',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_plante' => ['target_id' => $murier_nid],
    'field_att_valeur' => "Fibre d'appoint, non systématique : mûrier ajouté sur demande spécifique du client, à la manière du papier Xuan voisin de la région d'Anhui.",
    'field_att_statut' => 'probable',
  ]);
  $att2->save(); $compteur++;
}

// Composition — Méthode de fabrication, une attestation par étape
if ($type_comp && $axe_methode) {
  $etapes_detail = [
    'Coupe' => "Coupe du bambou au printemps, sur les jeunes pousses.",
    'Macération' => "Macération dans l'eau de rivière, pendant plusieurs mois.",
    'Cuisson' => "Cuisson avec de la chaux, pour dissoudre les fibres.",
    'Lavage et blanchiment' => "Lavage puis blanchiment par exposition au soleil.",
    'Battage' => "Battage, transformation en pâte homogène.",
    'Formation de la feuille' => "Formation de la feuille au tamis, geste spécifique.",
    'Pressage, séchage, collage' => "Pressage, séchage, puis collage.",
  ];
  foreach ($etapes_detail as $nom_etape => $detail) {
    $terme_etape = trouver_terme('etape_fabrication', $nom_etape);
    if ($terme_etape) {
      $att = Node::create([
        'type' => 'attestation', 'title' => "Composition (Méthode — $nom_etape) — Liansi",
        'field_att_type' => ['target_id' => $type_comp->id()],
        'field_att_axe' => ['target_id' => $axe_methode->id()],
        'field_att_papier' => ['target_id' => $liansi_nid],
        'field_att_valeur' => "$nom_etape — $detail",
        'field_att_statut' => 'attesté',
      ]);
      $att->save(); $compteur++;
    }
  }
}

// Usage
if ($type_usage) {
  $usages_ids = [];
  foreach (['Documents administratifs et décrets', 'Ordres impériaux', 'Confection de livres', 'Calligraphie'] as $u) {
    $t = trouver_terme('usage_papier', $u);
    if ($t) $usages_ids[] = $t->label();
  }
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Usage — Liansi',
    'field_att_type' => ['target_id' => $type_usage->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_valeur' => implode(', ', $usages_ids) . " — réservé traditionnellement aux usages les plus nobles.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save(); $compteur++;
}

echo "Attestations Liansi créées : $compteur\n";

// === 2. Correction de Lianqi zhi (nid=290) ===
$lianqi = Node::load(290);
if ($lianqi) {
  // Ajouter le lien structuré vers Liansi
  $lianqi->set('field_variante_de', ['target_id' => $liansi_nid]);
  // Ajouter le bambou comme fibre de base (sans retirer le mûrier existant)
  $composition_actuelle = $lianqi->get('field_genus_pap_entity')->getValue();
  $a_deja_bambou = FALSE;
  foreach ($composition_actuelle as $c) { if ($c['target_id'] == $bambou_nid) $a_deja_bambou = TRUE; }
  if (!$a_deja_bambou) {
    $composition_actuelle[] = ['target_id' => $bambou_nid];
    $lianqi->set('field_genus_pap_entity', $composition_actuelle);
  }
  $lianqi->save();
  echo "\nLianqi zhi (nid=290) : lien vers Liansi ajouté, bambou ajouté à la composition.\n";

  // Attestation Datation pour Lianqi zhi
  if ($type_dat) {
    $att = Node::create([
      'type' => 'attestation', 'title' => 'Datation — Lianqi zhi',
      'field_att_type' => ['target_id' => $type_dat->id()],
      'field_att_papier' => ['target_id' => 290],
      'field_att_periode' => $ming ? [['target_id' => $ming->id()]] : [],
      'field_att_lieu' => $yanshan ? ['target_id' => $yanshan->id()] : NULL,
      'field_att_valeur' => "Variété de qualité supérieure du Liansi, plus épaisse. Manufacture impériale établie à Xinjian (Jiangxi) au début de l'ère Yongle (1403), transférée vers Yanshan sous les ères Longqing-Wanli (fin XVIe s.).",
      'field_att_statut' => 'probable',
    ]);
    $att->save();
    echo "Attestation Datation créée pour Lianqi zhi.\n";
  }
}
else {
  echo "\nLianqi zhi (nid=290) introuvable.\n";
}
