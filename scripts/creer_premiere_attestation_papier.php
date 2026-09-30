<?php
use Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

// === 1. Nouveau type d'attestation : Datation ===
$existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type', 'name' => 'Datation']);
if (!$existant) {
  Term::create(['vid' => 'attestation_type', 'name' => 'Datation'])->save();
  echo "Type 'Datation' ajouté à attestation_type.\n";
} else {
  echo "Type 'Datation' déjà présent.\n";
}

// === 2. Champ field_att_periode, multi-valeurs, vers periode_dynastique ===
if (!FieldStorageConfig::loadByName('node', 'field_att_periode')) {
  FieldStorageConfig::create([
    'field_name' => 'field_att_periode',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
    'cardinality' => -1,
  ])->save();
  echo "Stockage field_att_periode créé (multi-valeurs).\n";
}
if (!FieldConfig::loadByName('node', 'attestation', 'field_att_periode')) {
  FieldConfig::create([
    'field_name' => 'field_att_periode',
    'entity_type' => 'node',
    'bundle' => 'attestation',
    'label' => 'Période(s)',
    'required' => FALSE,
    'description' => 'Période(s) dynastique(s) couvertes par cette attestation.',
    'settings' => ['handler_settings' => ['target_bundles' => ['periode_dynastique' => 'periode_dynastique']]],
  ])->save();
  echo "Champ field_att_periode rattaché à Attestation.\n";
}

\Drupal::service('cache.discovery')->deleteAll();

// === 3. Trouver les termes nécessaires ===
$type_datation = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type', 'name' => 'Datation']));
$goryeo = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => 'Goryeo']));
$joseon = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => 'Joseon']));
$coree_zhp = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production', 'name' => 'Corée']));

if (!$type_datation || !$goryeo || !$joseon || !$coree_zhp) {
  echo "ERREUR : un terme requis est introuvable — arrêt.\n";
  echo "  type_datation : " . ($type_datation ? "OK" : "MANQUANT") . "\n";
  echo "  Goryeo : " . ($goryeo ? "OK" : "MANQUANT") . "\n";
  echo "  Joseon : " . ($joseon ? "OK" : "MANQUANT") . "\n";
  echo "  Corée (zone_historique_production) : " . ($coree_zhp ? "OK" : "MANQUANT") . "\n";
  exit(1);
}

// === 4. Créer l'attestation elle-même ===
$existant_att = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_papier', 115)
  ->accessCheck(FALSE)
  ->execute();

if ($existant_att) {
  echo "\nUne attestation pour Acheongji existe déjà (nid=" . reset($existant_att) . ") — pas de doublon créé.\n";
} else {
  $att = Node::create([
    'type' => 'attestation',
    'title' => 'Datation — Acheongji',
    'field_att_type' => ['target_id' => $type_datation->id()],
    'field_att_papier' => ['target_id' => 115],
    'field_att_periode' => [
      ['target_id' => $goryeo->id()],
      ['target_id' => $joseon->id()],
    ],
    'field_att_lieu' => ['target_id' => $coree_zhp->id()],
    'field_att_valeur' => "Fabriqué en continu depuis la période Goryeo jusqu'à la période Joseon.",
    'field_att_statut' => 'attesté',
  ]);
  $att->save();
  echo "\nAttestation créée : nid=" . $att->id() . "\n";
  echo "  Type : Datation\n";
  echo "  Papier : Acheongji (nid=115)\n";
  echo "  Périodes : Goryeo + Joseon\n";
  echo "  Lieu : Corée\n";
  echo "  Note honnête : source = texte descriptif déjà présent sur la fiche (body), pas de référence bibliographique externe attachée — field_att_source laissé vide plutôt que d'en inventer une.\n";
}

echo "\n=== Vérification finale ===\n";
$verif = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', NULL, 'IS NOT NULL')->accessCheck(FALSE)->count()->execute();
echo "Attestations avec field_att_papier rempli, désormais : $verif\n";
