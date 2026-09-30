<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$chine = trouver_terme('pays', 'Chine');
$bambou_nid = 446;

$type_dat = trouver_terme('attestation_type', 'Datation');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');

$chongqing = trouver_terme('zone_historique_production', 'Chongqing');
if (!$chongqing) {
  $chongqing = Term::create(['vid' => 'zone_historique_production', 'name' => 'Chongqing']);
  $chongqing->save();
  echo "Chongqing cree, tid={$chongqing->id()}\n";
}
$pengshui = trouver_terme('zone_historique_production', 'Pengshui (Langxi)');
if (!$pengshui) {
  $pengshui = Term::create(['vid' => 'zone_historique_production', 'name' => 'Pengshui (Langxi)', 'parent' => $chongqing->id()]);
  $pengshui->save();
  echo "Pengshui cree, tid={$pengshui->id()}\n";
}
$zhongxian_lieu = trouver_terme('zone_historique_production', 'Zhongxian (Songbai)');
if (!$zhongxian_lieu) {
  $zhongxian_lieu = Term::create(['vid' => 'zone_historique_production', 'name' => 'Zhongxian (Songbai)', 'parent' => $chongqing->id()]);
  $zhongxian_lieu->save();
  echo "Zhongxian cree, tid={$zhongxian_lieu->id()}\n";
}

$sources = [
  'guangming' => ['type' => 'newspaper_article', 'title' => 'Guangming Ribao - Chongqing Pengshui, 29/01/2026'],
  'xinhua_cq' => ['type' => 'newspaper_article', 'title' => 'Xinhua Chongqing - Ayihe pan, 17/07/2026'],
  'chongqing_chenbao' => ['type' => 'newspaper_article', 'title' => 'Chongqing Chenbao - recherche transmission, 08/04/2025'],
  'paper_com_cn' => ['type' => 'website', 'title' => 'paper.com.cn 2009 - Zhongzhou tufa zaozhi'],
];
$refs = [];
foreach ($sources as $cle => $info) {
  $existant = \Drupal::entityQuery('bibcite_reference')->condition('title', $info['title'])->accessCheck(FALSE)->execute();
  if ($existant) { $refs[$cle] = reset($existant); continue; }
  $ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create($info);
  $ref->save();
  $refs[$cle] = $ref->id();
  echo "Reference '$cle' creee, id={$refs[$cle]}\n";
}

$existant = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Papier de Zhubanqiao')->accessCheck(FALSE)->execute();
if ($existant) {
  $zhu_nid = reset($existant);
  echo "Zhubanqiao deja existant, nid=$zhu_nid\n";
} else {
  $zhu = Node::create([
    'type' => 'papers', 'title' => 'Papier de Zhubanqiao', 'langcode' => 'fr', 'status' => 0,
    'body' => [
      'value' => "Papier de bambou grossier, a usage domestique et rituel, produit au hameau de Zhubanqiao, Langxi, Pengshui (Chongqing). Dix-huit foyers, tradition transmise sur onze generations. Liu Honghe est le 10e heritier de la lignee. Technique inscrite au patrimoine culturel immateriel municipal de Chongqing. Production non rentable comme activite autonome ; survit comme demonstration patrimoniale touristique.",
      'format' => 'full_html',
    ],
    'field_genus_pap_entity' => [['target_id' => $bambou_nid]],
  ]);
  if ($chine) $zhu->set('field_origin_taxo', ['target_id' => $chine->id()]);
  $zhu->save();
  $zhu_nid = $zhu->id();
  echo "Zhubanqiao cree, nid=$zhu_nid\n";
}

$c1 = 0;
if ($type_dat) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Datation - Papier de Zhubanqiao',
    'field_att_type' => ['target_id' => $type_dat->id()],
    'field_att_papier' => ['target_id' => $zhu_nid],
    'field_att_lieu' => ['target_id' => $pengshui->id()],
    'field_att_valeur' => "Lignee transmise sur onze generations. Liu Kaisheng (9e generation) nomme porteur de patrimoine municipal en 2009 ; technique inscrite au patrimoine culturel immateriel municipal de Chongqing.",
    'field_att_statut' => 'atteste',
    'field_att_source' => ['target_id' => $refs['xinhua_cq']],
  ]);
  $att->save(); $c1++;
}
if ($type_comp && $axe_fibre) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Fibre principale) - Papier de Zhubanqiao',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => $zhu_nid],
    'field_att_plante' => ['target_id' => $bambou_nid],
    'field_att_valeur' => "Bambou - papier grossier, non destine a la calligraphie.",
    'field_att_statut' => 'atteste',
  ]);
  $att->save(); $c1++;
}
if ($type_usage) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Usage - Papier de Zhubanqiao',
    'field_att_type' => ['target_id' => $type_usage->id()],
    'field_att_papier' => ['target_id' => $zhu_nid],
    'field_att_valeur' => "Usage domestique et rituel, format 22x44 cm. Aujourd'hui, site de demonstration patrimoniale touristique.",
    'field_att_statut' => 'atteste',
    'field_att_source' => ['target_id' => $refs['guangming']],
  ]);
  $att->save(); $c1++;
}
echo "Attestations Zhubanqiao : $c1\n";

$existant2 = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Papier de Zhongxian')->accessCheck(FALSE)->execute();
if ($existant2) {
  $zhong_nid = reset($existant2);
  echo "Zhongxian deja existant, nid=$zhong_nid\n";
} else {
  $zhong = Node::create([
    'type' => 'papers', 'title' => 'Papier de Zhongxian', 'langcode' => 'fr', 'status' => 0,
    'body' => [
      'value' => "Papier fabrique selon une methode traditionnelle locale au village de Songbai, xian de Zhongxian (Chongqing). Han Ji'an (ne en 1969), 14e generation d'une lignee familiale de papetiers, forme des l'age de 6 ans. Technique inscrite depuis 2009 sur la deuxieme liste du patrimoine culturel immateriel municipal de Chongqing. Dernier atelier actif, sans successeur declare ; production rabattue sur papiers rituels et d'hygiene. Fibre exacte non precisee dans les sources.",
      'format' => 'full_html',
    ],
  ]);
  if ($chine) $zhong->set('field_origin_taxo', ['target_id' => $chine->id()]);
  $zhong->save();
  $zhong_nid = $zhong->id();
  echo "Zhongxian cree, nid=$zhong_nid\n";
}

$c2 = 0;
if ($type_dat) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Datation - Papier de Zhongxian',
    'field_att_type' => ['target_id' => $type_dat->id()],
    'field_att_papier' => ['target_id' => $zhong_nid],
    'field_att_lieu' => ['target_id' => $zhongxian_lieu->id()],
    'field_att_valeur' => "Lignee familiale sur 14 generations. Han Ji'an, ne en 1969, forme des 6 ans. Technique inscrite en 2009 sur la deuxieme liste du patrimoine culturel immateriel municipal de Chongqing.",
    'field_att_statut' => 'atteste',
    'field_att_source' => ['target_id' => $refs['chongqing_chenbao']],
  ]);
  $att->save(); $c2++;
}
if ($type_usage) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Usage - Papier de Zhongxian',
    'field_att_type' => ['target_id' => $type_usage->id()],
    'field_att_papier' => ['target_id' => $zhong_nid],
    'field_att_valeur' => "Production actuelle rabattue sur papiers rituels et d'hygiene, qualite modeste.",
    'field_att_statut' => 'atteste',
    'field_att_source' => ['target_id' => $refs['paper_com_cn']],
  ]);
  $att->save(); $c2++;
}
echo "Attestations Zhongxian : $c2\n";
