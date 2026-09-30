<?php
use Drupal\node\Entity\Node;

function trouver_terme_source($nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type_source', 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$references = [
  'handian' => ['type' => 'website', 'title' => '漢典 (zdic.net), entrée « 連七紙 »'],
  'tulong' => ['type' => 'classical', 'title' => '屠隆, 《考槃餘事·紙箋》(Ming)'],
  'liuruoyu' => ['type' => 'classical', 'title' => '劉若愚, 《酌中志》(Ming)'],
  'hunan' => ['type' => 'newspaper_article', 'title' => '湖南日報 (hunantoday.cn), 28/05/2019 — recherches de Xiao Fabiao'],
  'ciyuan' => ['type' => 'website', 'title' => '《辭源》via Université Tsinghua (ad.tsinghua.edu.cn, 2021)'],
  'chinadaily' => ['type' => 'newspaper_article', 'title' => '中國日報網 (chinadaily.com.cn), 06/06/2024'],
];

$refs_creees = [];
foreach ($references as $cle => $info) {
  $ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create([
    'type' => $info['type'],
    'title' => $info['title'],
  ]);
  $ref->save();
  $refs_creees[$cle] = $ref->id();
  echo "$cle : créé, id={$ref->id()}\n";
}

// Lier "Texte historique" comme type de source pour les attestations Ming
$type_source_historique = trouver_terme_source('Texte historique');

// Attribution des attestations concernées
$liens = [
  'Datation — Liansi' => 'chinadaily', // datation/statistiques Daoguang
  'Datation — Lianshi zhi (jiangxien)' => 'hunan', // localisation Xishan/Meiling
  'Datation — Lianqi zhi' => 'tulong', // citation directe Tu Long
];

foreach ($liens as $titre_att => $cle_ref) {
  $ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', $titre_att)->accessCheck(FALSE)->execute();
  foreach (Node::loadMultiple($ids) as $att) {
    $att->set('field_att_source', ['target_id' => $refs_creees[$cle_ref]]);
    if ($type_source_historique) {
      $att->set('field_att_type_source', ['target_id' => $type_source_historique->id()]);
    }
    $att->save();
    echo "Attestation « $titre_att » liée à $cle_ref.\n";
  }
}
