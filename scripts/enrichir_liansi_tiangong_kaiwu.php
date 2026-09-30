<?php
use Drupal\node\Entity\Node;

// === 1. Créer la référence bibliographique Tiangong Kaiwu ===
$existant = \Drupal::entityQuery('bibcite_reference')->condition('title', 'Tiangong Kaiwu', 'CONTAINS')->accessCheck(FALSE)->execute();
if ($existant) {
  $ref_id = reset($existant);
  echo "Référence Tiangong Kaiwu déjà existante, id=$ref_id\n";
} else {
  $ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create([
    'type' => 'classical',
    'title' => '《天工開物·造皮紙》(Tiangong Kaiwu, chap. Fabrication du papier d\'écorce) — via Wikisource',
  ]);
  $ref->save();
  $ref_id = $ref->id();
  echo "Référence Tiangong Kaiwu créée, id=$ref_id\n";
}

// === 2. Enrichir l'attestation Datation existante de Liansi ===
$att_ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', 'Datation — Liansi')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($att_ids) as $att) {
  $valeur_actuelle = $att->get('field_att_valeur')->value;
  $ajout = " Le Tiangong Kaiwu (XVIIe s.) mentionne explicitement le papier liansi (連四紙) de la préfecture de Guangxin (Yanshan), dont la variante la plus blanche est appelée 紅上紙 (hongshang zhi) — pont textuel direct entre cette tradition et le chapitre sur les papiers d'écorce.";
  $att->set('field_att_valeur', $valeur_actuelle . $ajout);
  // Ne pas écraser une source déjà présente — n'assigner que si vide
  if ($att->get('field_att_source')->isEmpty()) {
    $att->set('field_att_source', ['target_id' => $ref_id]);
  }
  $att->save();
  echo "Attestation Datation — Liansi enrichie (nid={$att->id()}).\n";
}

// === 3. Ajouter le synonyme 紅上紙 sur la fiche Liansi ===
$liansi = Node::load(4578);
if ($liansi) {
  $synonymes_actuels = $liansi->get('field_pap_synonyme')->getValue();
  $deja_present = FALSE;
  foreach ($synonymes_actuels as $s) {
    if (str_contains($s['value'], 'hongshang') || str_contains($s['value'], '紅上紙')) $deja_present = TRUE;
  }
  if (!$deja_present) {
    $synonymes_actuels[] = ['value' => '紅上紙 (hongshang zhi) — variante la plus blanche, selon le Tiangong Kaiwu'];
    $liansi->set('field_pap_synonyme', $synonymes_actuels);
    $liansi->save();
    echo "Synonyme 紅上紙 ajouté à Liansi.\n";
  } else {
    echo "Synonyme déjà présent.\n";
  }
}
