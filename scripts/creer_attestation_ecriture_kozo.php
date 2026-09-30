<?php
use Drupal\node\Entity\Node;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

$type_ecriture_att = trouver_terme('attestation_type', 'Écriture vernaculaire');
$type_kanji = trouver_terme('type_ecriture', 'Kanji');
$nom_kozo = Node::load(105); // "Kozo", commun à Broussonetia papyrifera, confirmé canonique ce matin

if (!$type_ecriture_att || !$type_kanji || !$nom_kozo) {
  echo "ERREUR : un élément requis est introuvable.\n";
  echo "  Type Écriture vernaculaire : " . ($type_ecriture_att ? "OK" : "MANQUANT") . "\n";
  echo "  Type d'écriture Kanji : " . ($type_kanji ? "OK" : "MANQUANT") . "\n";
  echo "  Nœud Kozo (nid=105) : " . ($nom_kozo ? "OK" : "MANQUANT") . "\n";
  exit(1);
}

$existant = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_nom_vernaculaire', 105)
  ->accessCheck(FALSE)->execute();

if ($existant) {
  echo "Attestation déjà existante pour ce nom (nid=" . reset($existant) . ") — rien créé.\n";
  exit(0);
}

$att = Node::create([
  'type' => 'attestation',
  'title' => 'Écriture vernaculaire — Kozo (kanji)',
  'field_att_type' => ['target_id' => $type_ecriture_att->id()],
  'field_att_type_ecriture' => ['target_id' => $type_kanji->id()],
  'field_att_nom_vernaculaire' => ['target_id' => 105],
  'field_att_valeur' => "楮 — forme kanji du mot japonais « kozo », équivalente à la lecture katakana コウゾ déjà documentée sur cette fiche. Le kanji porte la lecture on'yomi チョ et la lecture kun'yomi こうぞ. Sources : Jisho.org, JapanDict, Nihongo Master (dictionnaires japonais en ligne, à recouper avec une source lexicographique imprimée avant citation savante).",
  'field_att_statut' => 'attesté',
]);
$att->save();

echo "Attestation créée : nid=" . $att->id() . "\n";
echo "Rattachée au nom vernaculaire 'Kozo' (nid=105)\n";
echo "Type d'écriture : Kanji\n";
