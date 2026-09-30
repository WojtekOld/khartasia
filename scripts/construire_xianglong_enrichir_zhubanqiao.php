<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$chine = trouver_terme('pays', 'Chine');
$bambou_nid = 446;
$chongqing = trouver_terme('zone_historique_production', 'Chongqing');

$type_dat = trouver_terme('attestation_type', 'Datation');
$type_comp = trouver_terme('attestation_type', 'Composition');
$type_usage = trouver_terme('attestation_type', 'Usage');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');
$axe_methode = trouver_terme('attestation_axe', 'Méthode de fabrication');

// Géographie Xianglong
$hechuan = trouver_terme('zone_historique_production', 'Hechuan (Xianglong)');
if (!$hechuan) {
  $hechuan = Term::create(['vid' => 'zone_historique_production', 'name' => 'Hechuan (Xianglong)', 'parent' => $chongqing->id()]);
  $hechuan->save();
  echo "Hechuan cree, tid={$hechuan->id()}\n";
}

$sources = [
  'hualong' => ['type' => 'newspaper_article', 'title' => 'Hualongwang - Chengyu Jingjiwang, Xianglong yaozhi feiyi, 11/12/2018'],
  'chenbao_2017' => ['type' => 'newspaper_article', 'title' => 'Chongqing Chenbao - Xiaowang yu zhulin, Xianglong yaozhi, 23/04/2017'],
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

// === XIANGLONG ===
$existant = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Papier de Xianglong')->accessCheck(FALSE)->execute();
if ($existant) {
  $xl_nid = reset($existant);
  echo "Xianglong deja existant, nid=$xl_nid\n";
} else {
  $xl = Node::create([
    'type' => 'papers', 'title' => 'Papier de Xianglong', 'langcode' => 'fr', 'status' => 0,
    'body' => [
      'value' => "Papier rituel brule (草紙/火紙, technique dite yaozhi/舀紙) fabrique a partir de bambou cizhu (慈竹) d'un an (一年青), dans les villages de Zaoli et Daya, bourg de Xianglong, district de Hechuan (Chongqing), vallee de la riviere Liuxi. Couleur doree, odeur de bambou. Environ 1000 mu de bambou dedie. Une centaine d'ateliers dans les annees 1980, tous fermes depuis - technique aujourd'hui eteinte, plus aucun atelier actif. Plus d'un siecle de tradition attestee ; apogee dans les annees 1980 ; extinction par concurrence du papier rituel mecanise, exigences environnementales, depart des jeunes generations. Inscrite en 2010 sur la deuxieme liste du patrimoine culturel immateriel du district de Hechuan (niveau district, pas municipal). Artisan cite : Zhou Mingwu (74 ans en 2018).",
      'format' => 'full_html',
    ],
    'field_genus_pap_entity' => [['target_id' => $bambou_nid]],
  ]);
  if ($chine) $xl->set('field_origin_taxo', ['target_id' => $chine->id()]);
  $xl->save();
  $xl_nid = $xl->id();
  echo "Xianglong cree, nid=$xl_nid\n";
}

$c = 0;
if ($type_dat) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Datation - Papier de Xianglong',
    'field_att_type' => ['target_id' => $type_dat->id()],
    'field_att_papier' => ['target_id' => $xl_nid],
    'field_att_lieu' => ['target_id' => $hechuan->id()],
    'field_att_valeur' => "Plus d'un siecle de tradition attestee. Apogee dans les annees 1980 (environ 80 ateliers). Extinction totale depuis - concurrence du papier mecanise, exigences environnementales, exode rural. Inscrite en 2010 sur la deuxieme liste du patrimoine culturel immateriel du DISTRICT de Hechuan (pas municipal - niveau plus local que Zhubanqiao ou Zhongxian).",
    'field_att_statut' => 'atteste',
    'field_att_source' => ['target_id' => $refs['hualong']],
  ]);
  $att->save(); $c++;
}
if ($type_comp && $axe_fibre) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Fibre principale) - Papier de Xianglong',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => $xl_nid],
    'field_att_plante' => ['target_id' => $bambou_nid],
    'field_att_valeur' => "Bambou cizhu (慈竹) d'un an specifiquement (一年青) - distinction fine de maturite du bambou, pas seulement l'espece.",
    'field_att_statut' => 'atteste',
  ]);
  $att->save(); $c++;
}
if ($type_comp && $axe_methode) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Methode) - Papier de Xianglong',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_methode->id()],
    'field_att_papier' => ['target_id' => $xl_nid],
    'field_att_valeur' => "Environ douze etapes : battage du bambou (捶竹), trempage a la chaux ~3 mois (泡竹), lavage (洗竹), fermentation ~15 jours (发汗), meule tiree par un boeuf, levee au tamis avec deux feuilles par geste (舀紙), pressage (压紙), sechage au soleil (晒紙). Conditionnement en boites de 30 feuilles, 50 boites par botte. Cadence maximale observee : environ 15 kg/jour/personne. Parallele technique avec Zhubanqiao : levee double (deux feuilles par geste) et macération a la chaux sans cuisson - indice d'une aire technique commune du Chongqing oriental.",
    'field_att_statut' => 'atteste',
    'field_att_source' => ['target_id' => $refs['chenbao_2017']],
  ]);
  $att->save(); $c++;
}
if ($type_usage) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Usage - Papier de Xianglong',
    'field_att_type' => ['target_id' => $type_usage->id()],
    'field_att_papier' => ['target_id' => $xl_nid],
    'field_att_valeur' => "Papier rituel destine a etre brule (草紙/火紙) - a l'oppose total du papier de lettre (書畫紙) de Jiajiang, comme Zhubanqiao et Zhongxian.",
    'field_att_statut' => 'atteste',
  ]);
  $att->save(); $c++;
}
echo "Attestations Xianglong : $c\n";

// === Enrichir Zhubanqiao avec le numero PCI precis et les nouveaux details ===
$zhu_ids = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('title', 'Papier de Zhubanqiao')->accessCheck(FALSE)->execute();
if ($zhu_ids) {
  $zhu_nid = reset($zhu_ids);
  $att_dat_ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Datation - Papier de Zhubanqiao')->accessCheck(FALSE)->execute();
  if (!$att_dat_ids) {
    $att_dat_ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Datation — Papier de Zhubanqiao')->accessCheck(FALSE)->execute();
  }
  foreach (Node::loadMultiple($att_dat_ids) as $att) {
    $ancien = $att->get('field_att_valeur')->value;
    $ajout = " Precision : inscrite sur la PREMIERE liste du patrimoine culturel immateriel municipal de Chongqing (n. VIII-14, dossier depose 2006). Production historiquement deplacee quatre fois (grotte Oujiadong, puis bord de riviere, puis mi-pente, puis site actuel). Annees 1950 : adoption de la maceration a la chaux vive, abandon de la cuisson. Annees 1970 : Liu Kaisheng (9e generation) innove avec deux feuilles par levee au lieu d'une.";
    $att->set('field_att_valeur', $ancien . $ajout);
    $att->save();
    echo "Datation Zhubanqiao enrichie.\n";
  }
}

echo "\nTermine.\n";
