<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r) : NULL;
}

// === Type d'attestation Synonyme ===
$type_syn = trouver_terme('attestation_type', 'Synonyme');
if (!$type_syn) {
  $type_syn = Term::create(['vid' => 'attestation_type', 'name' => 'Synonyme']);
  $type_syn->save();
  echo "Type 'Synonyme' créé.\n";
} else {
  echo "Type 'Synonyme' déjà présent.\n";
}

$papier_nid = 4564; // Shan theng

$synonymes = [
  ['car' => '剡藤', 'pinyin' => 'Shàn téng', 'sens' => "Nom principal, littéralement « le rotin de la rivière Shan » — 剡 renvoie à la rivière Shan (剡溪), dans l'actuelle Shengzhou (Zhejiang), région d'origine du papier ; 藤 signifie « liane, rotin », la matière première."],
  ['car' => '剡溪藤', 'pinyin' => 'Shàn xī téng', 'sens' => "Variante explicitant le nom de la rivière (溪, xī), employée une fois la production étendue au-delà de la seule rivière Shan."],
  ['car' => '藤紙', 'pinyin' => 'téng zhǐ', 'sens' => "Papier de rotin — terme générique pour ce type de papier."],
  ['car' => '剡紙', 'pinyin' => 'shàn zhǐ', 'sens' => "Papier de Shan — nommé d'après la région."],
  ['car' => '溪藤', 'pinyin' => 'xī téng', 'sens' => "Rotin de la rivière — autre désignation par l'origine."],
  ['car' => '藤角紙', 'pinyin' => 'téng jiǎo zhǐ', 'sens' => "Papier de rotin — autre nom historique."],
  ['car' => '玉葉紙', 'pinyin' => 'yù yè zhǐ', 'sens' => "Papier feuille de jade — surnom évoquant sa blancheur, sa finesse et sa qualité précieuse."],
];

$source = "Source : Joseph Needham, Science and Civilisation in China (ouvrage de référence).";

foreach ($synonymes as $s) {
  $titre = "Synonyme — Shan theng ({$s['car']})";
  $existant = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('title', $titre)->accessCheck(FALSE)->execute();
  if ($existant) {
    echo "{$s['car']} : déjà existant.\n";
    continue;
  }
  $att = Node::create([
    'type' => 'attestation',
    'title' => $titre,
    'field_att_type' => ['target_id' => $type_syn->id()],
    'field_att_papier' => ['target_id' => $papier_nid],
    'field_att_valeur' => "{$s['car']} ({$s['pinyin']}) — {$s['sens']} $source",
    'field_att_statut' => 'attesté',
  ]);
  $att->save();
  echo "{$s['car']} ({$s['pinyin']}) : créée, nid=" . $att->id() . "\n";
}

echo "\n=== Vérification finale ===\n";
$total = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_papier', $papier_nid)
  ->condition('field_att_type', $type_syn->id())
  ->accessCheck(FALSE)->count()->execute();
echo "Total attestations Synonyme pour Shan theng : $total (attendu 7)\n";
