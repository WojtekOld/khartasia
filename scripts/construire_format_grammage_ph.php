<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$type_comp = trouver_terme('attestation_type', 'Composition');
$type_circuit = trouver_terme('attestation_type', 'Circuit commercial');
$axe_format = trouver_terme('attestation_axe', 'Dimension / Format');

foreach (['Grammage', 'pH', 'Blancheur'] as $nom_axe) {
  if (!trouver_terme('attestation_axe', $nom_axe)) {
    Term::create(['vid' => 'attestation_axe', 'name' => $nom_axe])->save();
    echo "Axe '$nom_axe' créé.\n";
  }
}
$axe_grammage = trouver_terme('attestation_axe', 'Grammage');
$axe_ph = trouver_terme('attestation_axe', 'pH');
$axe_blancheur = trouver_terme('attestation_axe', 'Blancheur');

$sources = [
  'igp_2016' => ['type' => 'government_report', 'title' => "Spécification IGP (indication géographique protégée) du papier Liansi, 2016"],
  'catalogue_liansi' => ['type' => 'website', 'title' => "Catalogues et études académiques sur les dimensions du papier Liansi (sources convergentes, formats 60×110 cm et variantes)"],
  'catalogue_jiajiang' => ['type' => 'website', 'title' => "Norme générique du commerce du papier de calligraphie (GB/T 18739-2008), système de formats en chi"],
  'ming_prix' => ['type' => 'classical', 'title' => "Archives d'approvisionnement officiel du Jiangxi, ère Ming — prix du papier liansi et du papier de chancellerie (奏本紙)"],
];
$refs = [];
foreach ($sources as $cle => $info) {
  $existant = \Drupal::entityQuery('bibcite_reference')->condition('title', $info['title'])->accessCheck(FALSE)->execute();
  if ($existant) { $refs[$cle] = reset($existant); continue; }
  $ref = \Drupal::entityTypeManager()->getStorage('bibcite_reference')->create($info);
  $ref->save();
  $refs[$cle] = $ref->id();
  echo "Référence '$cle' créée, id={$refs[$cle]}\n";
}

$compteur = 0;
$liansi_nid = 4578;

if ($type_comp && $axe_format) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Format) — Liansi',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_format->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_valeur' => "Format constitutif du nom lui-même : 費著, 蜀箋譜 (Yuan) atteste que 連二/連三/連四 désignent des modes d'emploi du cliché de formage (抄紙簾), donc des formats de feuille — « Liansi » signifie littéralement une feuille formée « en quatre liaisons ». Dimensions couramment citées (60×110 cm, 100 feuilles/dao) rapportées par des sources convergentes mais non indépendamment vérifiées par une gazette ou un catalogue ancien — à traiter comme plausibles, non comme confirmées au même titre que l'étymologie.",
    'field_att_statut' => 'probable',
    'field_att_source' => ['target_id' => $refs['catalogue_liansi']],
  ]);
  $att->save(); $compteur++;
}

if ($type_comp && $axe_grammage) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Grammage) — Liansi',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_grammage->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_valeur' => "Environ 19 g/m² — papier extrêmement fin, fibres de bambou de 1 à 1,7 mm.",
    'field_att_statut' => 'probable',
  ]);
  $att->save(); $compteur++;
}

if ($type_comp && $axe_ph) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (pH) — Liansi',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_ph->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_valeur' => "Supérieur à 7,5 — papier alcalin, cohérent avec sa durabilité réputée (perte de blancheur au vieillissement d'environ 3,3%, contre 5% pour la norme nationale du papier Xuan).",
    'field_att_statut' => 'probable',
  ]);
  $att->save(); $compteur++;
}

if ($type_comp && $axe_blancheur) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Blancheur) — Liansi',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_blancheur->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_valeur' => "60,0% selon la spécification IGP de 2016 (à ne pas confondre avec un chiffre de 68% parfois avancé ailleurs, non conforme à cette norme). Une blancheur élevée n'est pas ici un indice de qualité : ce blanchiment modéré et naturel est précisément ce qui garantit la stabilité séculaire du papier — à l'inverse d'une blancheur commerciale élevée (comme celle du papier de Jiajiang, 85-90%), qui relève d'un autre registre, esthétique et non patrimonial.",
    'field_att_statut' => 'attesté',
    'field_att_source' => ['target_id' => $refs['igp_2016']],
  ]);
  $att->save(); $compteur++;
}

if ($type_circuit) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Circuit commercial (Prix) — Liansi',
    'field_att_type' => ['target_id' => $type_circuit->id()],
    'field_att_papier' => ['target_id' => $liansi_nid],
    'field_att_valeur' => "Sous les Ming, l'approvisionnement officiel du Jiangxi valorisait le papier liansi à 4 taels les 1 000 feuilles (soit 0,004 tael/feuille), contre 2,6 taels les 1 300 feuilles pour le papier de chancellerie courant (奏本紙) — un rapport d'environ 2 entre les deux. Prix brut d'archive, cité tel quel sans conversion en monnaie moderne.",
    'field_att_statut' => 'attesté',
    'field_att_source' => ['target_id' => $refs['ming_prix']],
  ]);
  $att->save(); $compteur++;
}

echo "\nAttestations Liansi (nouvelles) : $compteur\n";

$jiajiang_nid = 4596;
$compteur2 = 0;

if ($type_comp && $axe_format) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Format) — Papier de Jiajiang',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_format->id()],
    'field_att_papier' => ['target_id' => $jiajiang_nid],
    'field_att_valeur' => "Gamme évolutive, calibrée sur le système générique du commerce du papier de calligraphie (chi), non spécifique à Jiajiang : 五尺 (153×84 cm), 八尺 (248×129 cm), 丈二 (367×144 cm), 丈六 (503×193 cm). Un format annoncé de 526×180 cm (2025) n'est pas étayé par une source indépendante. Contrairement au Liansi, ce format n'est pas constitutif du nom — c'est une offre commerciale, pas une identité patrimoniale.",
    'field_att_statut' => 'probable',
    'field_att_source' => ['target_id' => $refs['catalogue_jiajiang']],
  ]);
  $att->save(); $compteur2++;
}

if ($type_comp && $axe_grammage) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Grammage) — Papier de Jiajiang',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_grammage->id()],
    'field_att_papier' => ['target_id' => $jiajiang_nid],
    'field_att_valeur' => "31 à 36 g/m², densité 0,35 ± 0,05 g/cm³ — plus épais que le Liansi.",
    'field_att_statut' => 'probable',
  ]);
  $att->save(); $compteur2++;
}

if ($type_comp && $axe_blancheur) {
  $att = Node::create([
    'type' => 'attestation', 'title' => 'Composition (Blancheur) — Papier de Jiajiang',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_blancheur->id()],
    'field_att_papier' => ['target_id' => $jiajiang_nid],
    'field_att_valeur' => "85 à 90% — blancheur commerciale et esthétique, à ne pas comparer directement à la blancheur fonctionnelle du Liansi (60%) : deux régimes de valeur différents, pas un indicateur de qualité supérieure.",
    'field_att_statut' => 'probable',
  ]);
  $att->save(); $compteur2++;
}

echo "Attestations Jiajiang (nouvelles) : $compteur2\n";
