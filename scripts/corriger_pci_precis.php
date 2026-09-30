<?php
use Drupal\node\Entity\Node;

$ref_conseil = \Drupal::entityQuery('bibcite_reference')->condition('title', 'Conseil des affaires', 'CONTAINS')->accessCheck(FALSE)->execute();
if (!$ref_conseil) {
  $ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create([
    'type' => 'government_report',
    'title' => "Conseil des affaires d'État (RPC), Avis sur la publication de la première liste du patrimoine culturel immatériel national, 20 mai 2006 (國務院關於公佈第一批國家級非物質文化遺產名錄的通知)",
  ]);
  $ref->save();
  $ref_conseil_id = $ref->id();
} else {
  $ref_conseil_id = reset($ref_conseil);
}

// Liansi — ajouter le numéro PCI précis
$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Datation — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids) as $att) {
  $ancien = $att->get('field_att_valeur')->value;
  $ajout = " Référence précise : « 鉛山連四紙製作技藝 », n° Ⅷ-66, première liste du patrimoine culturel immatériel national, Conseil des affaires d'État, 20 mai 2006 — distinct de la spécification de qualité IGP provinciale (norme locale du Jiangxi, gérée séparément par l'Administration du marché du Jiangxi).";
  $att->set('field_att_valeur', $ancien . $ajout);
  $att->set('field_att_source', ['target_id' => $ref_conseil_id]);
  $att->save();
  echo "Datation Liansi enrichie (n° PCI precis).\n";
}

// Jiajiang — corriger et enrichir
$ids2 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Datation — Papier de Jiajiang')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids2) as $att) {
  $att->set('field_att_valeur', "Origine sous les Tang, apogée Ming-Qing — sous Kangxi, les papiers « 長簾文卷 » et « 方細土連 » désignés comme papier de tribut (貢紙) ; sous Qianlong, désignation comme papier d'examen impérial (文闈卷紙) et papier de cour (宮廷用紙). Référence précise : « 竹紙製作技藝 », n° Ⅷ-72, première liste du patrimoine culturel immatériel national, Conseil des affaires d'État, 20 mai 2006 — inscription PARTAGÉE avec Fuyang (Zhejiang, tradition distincte de papier de bambou), pas exclusive à Jiajiang. Label « pays du papier de calligraphie chinoise » (中國書畫紙之鄉) décerné par le Ministère de la Culture en 2008 (et non 2009 comme précédemment avancé). Porteur national du patrimoine (代表性傳承人) : Yang Zhanyao (楊占堯) — Chen Xiujun (atelier Zhuangyuan) est sa belle-fille, 13e génération de cette lignée.");
  $att->set('field_att_source', ['target_id' => $ref_conseil_id]);
  $att->save();
  echo "Datation Jiajiang corrigée et enrichie.\n";
}
