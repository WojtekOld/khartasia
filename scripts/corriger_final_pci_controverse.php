<?php
use Drupal\node\Entity\Node;

$sources = [
  'db36' => ['type' => 'government_report', 'title' => "DB36/T 2223-2026 - Geli biaozhi chanpin zhiliang yaoqiu Yanshan liansizhi, Administration du marche du Jiangxi, 23 avril 2026, vigueur 1er novembre 2026, enregistrement 129859-2026"],
  'conseil_etat_2006' => ['type' => 'government_report', 'title' => "Conseil des affaires d'Etat RPC, premiere liste du patrimoine culturel immateriel national, 20 mai 2006 - projet 421, reference VIII-71, Zhuzhi zhizuo jiyi, xian de Jiajiang Sichuan"],
  'deng_jinkun' => ['type' => 'newspaper_article', 'title' => "Article de presse 2011-2012 sur la controverse Shuangcheng zhi zheng - Deng Jinkun Gutian Liancheng Fujian vs bureau culturel de Yanshan sur l'identite Liansi Lianshi"],
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

$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Blancheur) — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids) as $att) {
  $valeur = $att->get('field_att_valeur')->value;
  $valeur_corrigee = str_replace('spécification IGP de 2016', 'norme locale DB36/T 2223-2026 (publiée le 23 avril 2026, en vigueur au 1er novembre 2026)', $valeur);
  $att->set('field_att_valeur', $valeur_corrigee);
  $att->set('field_att_source', ['target_id' => $refs['db36']]);
  $att->save();
  echo "Blancheur Liansi corrigee.\n";
}

foreach (['Composition (Grammage) — Liansi', 'Composition (pH) — Liansi'] as $titre) {
  $ids2 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', $titre)->accessCheck(FALSE)->execute();
  foreach (Node::loadMultiple($ids2) as $att) {
    $valeur = $att->get('field_att_valeur')->value;
    $valeur_corrigee = str_replace('IGP (鉛山連四紙, 質檢總局, 2016)', 'DB36/T 2223-2026', $valeur);
    $att->set('field_att_valeur', $valeur_corrigee);
    $att->set('field_att_source', ['target_id' => $refs['db36']]);
    $att->save();
    echo "$titre corrigee.\n";
  }
}

$ids3 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Datation — Papier de Jiajiang')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids3) as $att) {
  $valeur = $att->get('field_att_valeur')->value;
  $valeur_corrigee = str_replace('n° Ⅷ-72', 'projet n° 421, référence Ⅷ-71 (et non Ⅷ-72, corrigé)', $valeur);
  $att->set('field_att_valeur', $valeur_corrigee);
  $att->set('field_att_source', ['target_id' => $refs['conseil_etat_2006']]);
  $att->save();
  echo "Datation Jiajiang corrigee.\n";
}

$liansi = Node::load(4578);
$synonymes = $liansi->get('field_pap_synonyme')->getValue();
$synonymes[] = ['value' => "Controverse documentée (2011-2012, « guerre des deux villes », 雙城之爭) : Deng Jinkun, 11e génération de papetiers de Gutian (Liancheng, Fujian), affirme que Liansi (Yanshan) et Lianshi (Liancheng) sont deux papiers distincts. Le bureau culturel de Yanshan soutient à l'inverse qu'il s'agit du même papier — Liansi étant l'ancien nom, rebaptisé Lianshi (連接歷史的紙, « papier qui relie l'histoire ») sous les Ming. Un désaccord réel et sourcé, pas seulement une confusion populaire moderne."];
$liansi->set('field_pap_synonyme', $synonymes);
$liansi->setNewRevision(TRUE);
$liansi->setRevisionLogMessage("Ajout de la controverse documentee Deng Jinkun / bureau culturel de Yanshan (2011-2012).");
$liansi->save();
echo "Controverse Deng Jinkun ajoutee a Liansi.\n";
