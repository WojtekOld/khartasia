<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$chine = trouver_terme('pays', 'Chine');
$jiangxi = trouver_terme('zone_historique_production', 'Jiangxi');
$yanshan = trouver_terme('zone_historique_production', 'Xian de Yanshan');
$ming = trouver_terme('periode_dynastique', 'Ming');
$murier_nid = 22; // Broussonetia papyrifera

$type_dat = trouver_terme('attestation_type', 'Datation');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');

// === 1. Créer le terme géographique Xinjian (massif de Meiling) ===
$xinjian = trouver_terme('zone_historique_production', 'Xinjian (massif de Meiling)');
if (!$xinjian) {
  $xinjian = Term::create([
    'vid' => 'zone_historique_production',
    'name' => 'Xinjian (massif de Meiling)',
    'parent' => $jiangxi ? $jiangxi->id() : 0,
  ]);
  $xinjian->save();
  echo "Terme Xinjian créé, tid={$xinjian->id()}\n";
}

// === 2. Créer la fiche Lianshi zhi (jiangxien) ===
$existant = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Lianshi zhi (jiangxien)')->accessCheck(FALSE)->execute();
if ($existant) {
  $lianshi_nid = reset($existant);
  echo "Lianshi zhi (jiangxien) déjà existant, nid=$lianshi_nid\n";
} else {
  $lianshi = Node::create([
    'type' => 'papers',
    'title' => 'Lianshi zhi (jiangxien)',
    'langcode' => 'fr',
    'status' => 0,
    'body' => [
      'value' => "Papier d'écorce de mûrier (楮, chu) produit par la manufacture impériale établie à Xishan (massif de Meiling, xian de Xinjian, près de Nanchang, Jiangxi) sous l'ère Yongle (dès 1403) — Tu Long, 考槃餘事, y situe les productions les plus épaisses et les plus belles, dont le 連七 (Lianqi zhi) et le papier Guanyin. Utilisé pour les rapports adressés à la Direction des rites (司禮監) — Liu Ruoyu, 酌中志. Aux Ming moyens-tardifs, l'épuisement de l'écorce de chu à Meiling entraîne le transfert de la manufacture vers la préfecture de Xinzhou/Guangxin (région du xian de Yanshan) — sans date précise attestée dans les sources consultées. Distinct du papier homophone 連史紙 de Liancheng (Fujian, bourg de Gutian, papier de bambou apparu vers 1629) et du Liansi (連四紙) de bambou de Yanshan : continuité institutionnelle et nominale d'une lignée de papiers de prestige jiangxiens, non identité de fibre.",
      'format' => 'full_html',
    ],
    'field_genus_pap_entity' => [['target_id' => $murier_nid]],
    'field_pap_local_scripture' => [['value' => '連史紙']],
  ]);
  if ($chine) $lianshi->set('field_origin_taxo', ['target_id' => $chine->id()]);
  $lianshi->save();
  $lianshi_nid = $lianshi->id();
  echo "Lianshi zhi (jiangxien) créé, nid=$lianshi_nid\n";
}

$compteur = 0;

// Datation
if ($type_dat) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Datation — Lianshi zhi (jiangxien)',
    'field_att_type' => ['target_id' => $type_dat->id()],
    'field_att_papier' => ['target_id' => $lianshi_nid],
    'field_att_periode' => $ming ? [['target_id' => $ming->id()]] : [],
    'field_att_lieu' => $xinjian ? ['target_id' => $xinjian->id()] : NULL,
    'field_att_valeur' => "Manufacture impériale établie à Xishan (massif de Meiling, xian de Xinjian) dès l'ère Yongle (1403). Transférée vers la préfecture de Xinzhou/Guangxin (région de Yanshan) aux Ming moyens-tardifs, faute d'écorce de chu suffisante — date précise non attestée dans les sources consultées.",
    'field_att_statut' => 'probable',
  ]);
  $att->save(); $compteur++;
}

// Composition — Fibre principale : mûrier
if ($type_comp && $axe_fibre) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Fibre principale) — Lianshi zhi (jiangxien)',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => $lianshi_nid],
    'field_att_plante' => ['target_id' => $murier_nid],
    'field_att_valeur' => "Écorce de mûrier à papier (楮, chu) — matière de la manufacture de Xishan, distincte du bambou du Liansi de Yanshan.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save(); $compteur++;
}

// Usage
if ($type_usage) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Usage — Lianshi zhi (jiangxien)',
    'field_att_type' => ['target_id' => $type_usage->id()],
    'field_att_papier' => ['target_id' => $lianshi_nid],
    'field_att_valeur' => "Rapports adressés à la Direction des rites (司禮監) — cour impériale Ming.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save(); $compteur++;
}

echo "Attestations Lianshi zhi créées : $compteur\n";

// === 3. Corriger Lianqi zhi (nid=290) ===
$lianqi = Node::load(290);
if ($lianqi) {
  // Retirer le lien erroné vers Liansi, le remplacer par le lien vers Lianshi zhi
  $lianqi->set('field_variante_de', [['target_id' => $lianshi_nid]]);

  // Retirer le bambou ajouté par erreur — revenir au mûrier seul
  $composition_actuelle = $lianqi->get('field_genus_pap_entity')->getValue();
  $composition_corrigee = array_values(array_filter($composition_actuelle, function($c) {
    return $c['target_id'] != 446; // retirer Phyllostachys edulis
  }));
  $lianqi->set('field_genus_pap_entity', $composition_corrigee);
  $lianqi->save();
  echo "\nLianqi zhi corrigé : lien vers Lianshi zhi (pas Liansi), bambou retiré.\n";

  // Supprimer l'ancienne attestation Datation incorrecte, en créer une correcte
  $anciennes_att = \Drupal::entityQuery('node')
    ->condition('type', 'attestation')
    ->condition('field_att_papier', 290)
    ->condition('title', 'Datation — Lianqi zhi')
    ->accessCheck(FALSE)->execute();
  foreach (Node::loadMultiple($anciennes_att) as $a) {
    $a->delete();
    echo "Ancienne attestation Datation supprimée (nid={$a->id()}).\n";
  }

  if ($type_dat) {
    $att = Node::create([
      'type' => 'attestation', 'title' => 'Datation — Lianqi zhi',
      'field_att_type' => ['target_id' => $type_dat->id()],
      'field_att_papier' => ['target_id' => 290],
      'field_att_periode' => $ming ? [['target_id' => $ming->id()]] : [],
      'field_att_lieu' => $xinjian ? ['target_id' => $xinjian->id()] : NULL,
      'field_att_valeur' => "Variété épaisse et de qualité supérieure du Lianshi zhi (連史紙) jiangxien — 漢典 : « 连史纸的一种，质地较厚，品质优良 ». Attesté à la manufacture impériale de Xishan sous Yongle (Tu Long, 考槃餘事) et pour les usages de la chancellerie impériale (Liu Ruoyu, 酌中志). À ne pas confondre avec le Liansi (連四紙) de bambou de Yanshan, ni avec le 連史紙 homophone de Liancheng (Fujian).",
      'field_att_statut' => 'attesté',
    ]);
    $att->save();
    echo "Nouvelle attestation Datation créée pour Lianqi zhi.\n";
  }
}
