<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$chine = trouver_terme('pays', 'Chine');
$tang = trouver_terme('periode_dynastique', 'Tang');
$ming = trouver_terme('periode_dynastique', 'Ming');
$qing = trouver_terme('periode_dynastique', 'Qing');
$jiajiang_lieu = trouver_terme('zone_historique_production', 'Xian de Jiajiang');
$bambou_nid = 446; // Phyllostachys edulis

$type_dat = trouver_terme('attestation_type', 'Datation');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');
$axe_methode = trouver_terme('attestation_axe', 'Méthode de fabrication');

// === Références bibliographiques ===
$sources = [
  'renmin' => "人民日報, 《夹江竹纸，匠心妙手传千年》, 03/08/2024",
  'sichuan_en_ligne' => "四川在線, 《三纸融合千年纸乡的守与望》, 25/11/2024",
  'ihchina' => "ihchina.cn, fiche du projet national 《竹纸制作技艺》(夹江县, 2006)",
];
$refs = [];
foreach ($sources as $cle => $titre) {
  $existant = \Drupal::entityQuery('bibcite_reference')->condition('title', $titre)->accessCheck(FALSE)->execute();
  if ($existant) {
    $refs[$cle] = reset($existant);
  } else {
    $ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create([
      'type' => $cle === 'ihchina' ? 'website' : 'newspaper_article',
      'title' => $titre,
    ]);
    $ref->save();
    $refs[$cle] = $ref->id();
  }
  echo "Référence '$cle' : id={$refs[$cle]}\n";
}

// === Création de la fiche ===
$existant = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Papier de Jiajiang')->accessCheck(FALSE)->execute();
if ($existant) {
  $jiajiang_nid = reset($existant);
  echo "Papier de Jiajiang déjà existant, nid=$jiajiang_nid\n";
} else {
  $jiajiang = Node::create([
    'type' => 'papers',
    'title' => 'Papier de Jiajiang',
    'langcode' => 'fr',
    'status' => 0,
    'body' => [
      'value' => "Papier de bambou produit dans le xian de Jiajiang (Sichuan), dont l'origine remonte à la dynastie Tang, avec un apogée sous les Ming-Qing. La technique, conforme aux 72 opérations du Tiangong Kaiwu, est concentrée dans le bourg de Macun (马村镇 — récemment promu de 乡 à 镇), qui regroupe l'essentiel des ateliers artisanaux du xian (75 recensés en 2023). La stèle 蔡翁碑叙 (ère Daoguang) résume le procédé en 24 caractères. Inscrit au patrimoine culturel immatériel national en 2006 ; le xian porte depuis 2009 le label « pays du papier de calligraphie chinoise ». Le papier de calligraphie (书画纸) produit à Jiajiang représente plus de 60% du marché national — chiffre portant sur la production totale du xian (incluant la production mécanisée), à distinguer de la production strictement artisanale du bourg de Macun (environ 14 000 dao/an en 2023, 75 ateliers familiaux). Porteuse d'héritage : Chen Xiujun (atelier Zhuangyuan/状元书画纸厂). Certains ateliers (Huayi/华艺纸坊, Fanggou) ont rationalisé le procédé traditionnel à une vingtaine d'étapes ; d'autres (Huatou/华头镇) maintiennent l'intégralité des 72 opérations.",
      'format' => 'full_html',
    ],
    'field_genus_pap_entity' => [['target_id' => $bambou_nid]],
    'field_pap_local_scripture' => [['value' => '夹江竹纸']],
    'field_pap_synonyme' => [['value' => '书画纸 (papier de calligraphie et peinture)']],
  ]);
  if ($chine) $jiajiang->set('field_origin_taxo', ['target_id' => $chine->id()]);
  $jiajiang->save();
  $jiajiang_nid = $jiajiang->id();
  echo "Papier de Jiajiang créé, nid=$jiajiang_nid\n";
}

$compteur = 0;

// Datation
if ($type_dat) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Datation — Papier de Jiajiang',
    'field_att_type' => ['target_id' => $type_dat->id()],
    'field_att_papier' => ['target_id' => $jiajiang_nid],
    'field_att_periode' => array_filter([$tang ? ['target_id' => $tang->id()] : NULL, $ming ? ['target_id' => $ming->id()] : NULL, $qing ? ['target_id' => $qing->id()] : NULL]),
    'field_att_lieu' => $jiajiang_lieu ? ['target_id' => $jiajiang_lieu->id()] : NULL,
    'field_att_valeur' => "Origine sous les Tang, apogée Ming-Qing. Inscription au patrimoine culturel immatériel national en 2006 ; label « pays du papier de calligraphie chinoise » en 2009.",
    'field_att_statut' => 'attesté',
    'field_att_source' => ['target_id' => $refs['ihchina']],
  ]);
  $att->save(); $compteur++;
}

// Composition — Fibre principale : bambou
if ($type_comp && $axe_fibre) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Fibre principale) — Papier de Jiajiang',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => $jiajiang_nid],
    'field_att_plante' => ['target_id' => $bambou_nid],
    'field_att_valeur' => "Bambou, matière exclusive de ce papier — même matrice technique que le Liansi de Yanshan (Jiangxi), tous deux documentés dans le Tiangong Kaiwu.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save(); $compteur++;
}

// Composition — Méthode de fabrication (mêmes étapes génériques que Liansi, cohérence de la matrice bambou)
if ($type_comp && $axe_methode) {
  $etapes_detail = [
    'Coupe' => "Coupe du bambou, jeunes pousses (procédé du Tiangong Kaiwu).",
    'Macération' => "Macération, étape du procédé traditionnel en 72 opérations.",
    'Cuisson' => "Cuisson des fibres, étape du procédé traditionnel.",
    'Lavage et blanchiment' => "Lavage et blanchiment.",
    'Battage' => "Battage, transformation en pâte.",
    'Formation de la feuille' => "Formation de la feuille au tamis.",
    'Pressage, séchage, collage' => "Pressage, séchage, collage.",
  ];
  foreach ($etapes_detail as $nom_etape => $detail) {
    $terme_etape = trouver_terme('etape_fabrication', $nom_etape);
    if ($terme_etape) {
      $att = Node::create([
        'type' => 'attestation', 'title' => "Composition (Méthode — $nom_etape) — Papier de Jiajiang",
        'field_att_type' => ['target_id' => $type_comp->id()],
        'field_att_axe' => ['target_id' => $axe_methode->id()],
        'field_att_papier' => ['target_id' => $jiajiang_nid],
        'field_att_valeur' => "$nom_etape — $detail Procédé traditionnel en 72 opérations (Tiangong Kaiwu) ; certains ateliers modernes (Huayi/Fanggou) l'ont rationalisé à une vingtaine d'étapes, d'autres (Huatou) le conservent intégralement.",
        'field_att_statut' => 'attesté',
        'field_att_source' => ['target_id' => $refs['sichuan_en_ligne']],
      ]);
      $att->save(); $compteur++;
    }
  }
}

// Usage
if ($type_usage) {
  $t_calligraphie = trouver_terme('usage_papier', 'Calligraphie');
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Usage — Papier de Jiajiang',
    'field_att_type' => ['target_id' => $type_usage->id()],
    'field_att_papier' => ['target_id' => $jiajiang_nid],
    'field_att_valeur' => "Papier de calligraphie et peinture (书画纸) — plus de 60% du marché national chinois de ce type de papier (production totale du xian, mécanisée incluse ; production artisanale du seul bourg de Macun bien plus modeste, ~14 000 dao/an en 2023).",
    'field_att_statut' => 'attesté',
    'field_att_source' => ['target_id' => $refs['renmin']],
  ]);
  $att->save(); $compteur++;
}

echo "Attestations créées : $compteur\n";
