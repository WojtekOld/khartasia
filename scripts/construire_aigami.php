<?php
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

function trouver_terme($vid, $nom, $parent_id = NULL) {
  $props = ['vid' => $vid, 'name' => $nom];
  if ($parent_id !== NULL) $props['parent'] = $parent_id;
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($props);
  return $r ? reset($r) : NULL;
}

// === 1. Terme Tokushima, sous Shikoku (déjà existant) ===
$shikoku = trouver_terme('zone_historique_production', 'Shikoku');
if (!$shikoku) { echo "ERREUR : Shikoku introuvable.\n"; exit(1); }

$tokushima = trouver_terme('zone_historique_production', 'Tokushima', $shikoku->id());
if (!$tokushima) {
  $tokushima = Term::create(['vid' => 'zone_historique_production', 'name' => 'Tokushima', 'parent' => $shikoku->id()]);
  $tokushima->set('field_niveau_zone', 'province_prefecture_do');
  $tokushima->save();
  echo "Tokushima créé sous Shikoku.\n";
} else {
  echo "Tokushima déjà existant.\n";
}

// === 2. Vérifier les types d'attestation nécessaires ===
$type_geo = trouver_terme('attestation_type', 'Géographie');
$type_comp = trouver_terme('attestation_type', 'Composition');
if (!$type_geo || !$type_comp) {
  echo "ERREUR : type Géographie ou Composition introuvable — noms exacts à vérifier.\n";
  exit(1);
}

$axe_colorant = trouver_terme('attestation_axe', 'Colorant');
$axe_fibre = trouver_terme('attestation_axe', 'Fibre principale');
if (!$axe_colorant || !$axe_fibre) {
  echo "ERREUR : axe Colorant ou Fibre principale introuvable.\n";
  exit(1);
}

// === 3. Attestation Géographie ===
$existant_geo = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')->condition('field_att_papier', 1907)
  ->condition('field_att_type', $type_geo->id())
  ->accessCheck(FALSE)->execute();

if (!$existant_geo) {
  $att = Node::create([
    'type' => 'attestation',
    'title' => 'Géographie — Aigami',
    'field_att_type' => ['target_id' => $type_geo->id()],
    'field_att_papier' => ['target_id' => 1907],
    'field_att_lieu' => ['target_id' => $tokushima->id()],
    'field_att_valeur' => 'Spécialité de la préfecture de Tokushima.',
    'field_att_statut' => 'attesté',
  ]);
  $att->save();
  echo "Attestation Géographie créée : nid=" . $att->id() . "\n";
} else {
  echo "Attestation Géographie déjà existante.\n";
}

// === 4. Attestation Composition — Fibre ===
$existant_fibre = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')->condition('field_att_papier', 1907)
  ->condition('field_att_axe', $axe_fibre->id())
  ->accessCheck(FALSE)->execute();

if (!$existant_fibre) {
  $att = Node::create([
    'type' => 'attestation',
    'title' => 'Composition (fibre) — Aigami',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_fibre->id()],
    'field_att_papier' => ['target_id' => 1907],
    'field_att_plante' => ['target_id' => 22], // Broussonetia papyrifera
    'field_att_valeur' => 'Fibre structurelle : Broussonetia papyrifera.',
    'field_att_statut' => 'probable',
  ]);
  $att->save();
  echo "Attestation Composition (fibre) créée : nid=" . $att->id() . "\n";
}

// === 5. Attestation Composition — Colorant, sources multiples explicitement notées ===
$existant_colorant = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')->condition('field_att_papier', 1907)
  ->condition('field_att_axe', $axe_colorant->id())
  ->accessCheck(FALSE)->execute();

if (!$existant_colorant) {
  $att = Node::create([
    'type' => 'attestation',
    'title' => 'Composition (colorant) — Aigami',
    'field_att_type' => ['target_id' => $type_comp->id()],
    'field_att_axe' => ['target_id' => $axe_colorant->id()],
    'field_att_papier' => ['target_id' => 1907],
    'field_att_valeur' => "Indigo — source végétale non tranchée par le texte source : quatre plantes tinctoriales référencées (Indigofera tinctoria, Isatis tinctoria, Strobilanthes flaccidifolia, Polygonum tinctorium). Statut hypothétique tant qu'une source ne précise pas laquelle.",
    'field_att_statut' => 'hypothétique',
  ]);
  $att->save();
  echo "Attestation Composition (colorant) créée : nid=" . $att->id() . "\n";
}

echo "\n=== Vérification finale ===\n";
$total = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', 1907)->accessCheck(FALSE)->count()->execute();
echo "Total attestations pour Aigami : $total\n";
