<?php
use Drupal\node\Entity\Node;

$existant = \Drupal::entityQuery('bibcite_reference')->condition('title', 'Tsinghua', 'CONTAINS')->accessCheck(FALSE)->execute();
if (!$existant) {
  $ref_tsinghua = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create([
    'type' => 'presentation',
    'title' => "Université Tsinghua, Académie des beaux-arts (2021), salon « 鉛山連四紙 » — étymologie 連二/連三/連四 (費著《蜀箋譜》), grammages 玉錦堂",
  ]);
  $ref_tsinghua->save();
  $tsinghua_id = $ref_tsinghua->id();
} else {
  $tsinghua_id = reset($existant);
}

$existant2 = \Drupal::entityQuery('bibcite_reference')->condition('title', 'Zhang Daqian', 'CONTAINS')->accessCheck(FALSE)->execute();
if (!$existant2) {
  $ref_zhang = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create([
    'type' => 'web_article',
    'title' => "學習強國 (2019) — Zhang Daqian et les formats 四尺/五尺 à Jiajiang (蜀箋, 大風堂)",
  ]);
  $ref_zhang->save();
  $zhang_id = $ref_zhang->id();
} else {
  $zhang_id = reset($existant2);
}

$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Grammage) — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids) as $att) {
  $att->set('field_att_valeur', "19,0 ± 2,0 g/m² selon la spécification IGP (鉛山連四紙, 質檢總局, 2016). La gamme réelle des produits varie selon l'usage : papier de restauration ~14 g/m², papier de marquage ~18 g/m², papier de calligraphie 28-30 g/m² (salon Tsinghua, 玉錦堂, 2021) — \"19 g/m²\" est une valeur de référence IGP, pas un chiffre unique couvrant toute la production.");
  $att->set('field_att_statut', 'attesté');
  $att->set('field_att_source', ['target_id' => $tsinghua_id]);
  $att->save();
  echo "Grammage Liansi corrigé.\n";
}

$ids2 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (pH) — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids2) as $att) {
  $att->set('field_att_valeur', "≥ 7,5 selon la spécification IGP (鉛山連四紙, 質檢總局, 2016) — papier alcalin.");
  $att->set('field_att_statut', 'attesté');
  $ref_igp = \Drupal::entityQuery('bibcite_reference')->condition('title', 'IGP', 'CONTAINS')->accessCheck(FALSE)->execute();
  if ($ref_igp) $att->set('field_att_source', ['target_id' => reset($ref_igp)]);
  $att->save();
  echo "pH Liansi corrigé.\n";
}

$ids3 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Blancheur) — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids3) as $att) {
  $att->set('field_att_valeur', "60,0% selon la spécification IGP de 2016 (鉛山連四紙, 質檢總局) — à ne pas confondre avec un chiffre de 68% parfois avancé ailleurs, non conforme à cette norme. Résistance au vieillissement : baisse de blancheur ≤5,0% (norme propre au Liansi lui-même, pas une comparaison directement établie avec le papier Xuan dans la spécification IGP). Une blancheur modérée n'est pas ici un indice de moindre qualité : ce blanchiment naturel est précisément ce qui garantit la stabilité séculaire du papier — à l'inverse de la blancheur commerciale et esthétique du papier de Jiajiang (85-90%), qui relève d'un autre registre, non patrimonial.");
  $att->save();
  echo "Blancheur Liansi corrigée.\n";
}

$ids4 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Format) — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids4) as $att) {
  $att->set('field_att_source', ['target_id' => $tsinghua_id]);
  $att->save();
  echo "Format Liansi : source ajoutée.\n";
}

$ids5 = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Composition (Format) — Papier de Jiajiang')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($ids5) as $att) {
  $valeur_actuelle = $att->get('field_att_valeur')->value;
  $ajout = " Tolérance commerciale usuelle : ±3 cm sur un format 四尺. Ce calibrage sur le système du marché du Xuan n'est pas qu'une convention générique : Zhang Daqian a spécifiquement fait passer les formats de Jiajiang au 四尺 et 五尺 dans les années 1930-40 (蜀箋, 大風堂) — un fait historique daté.";
  $att->set('field_att_valeur', $valeur_actuelle . $ajout);
  $att->save();
  echo "Format Jiajiang enrichi (Zhang Daqian).\n";
}

echo "\nTerminé.\n";
