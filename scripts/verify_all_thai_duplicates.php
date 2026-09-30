<?php
$paires = [
  [3585, 3878, 'Broussonetia papyrifera'],
  [3586, 3879, 'Broussonetia kazinoki'],
  [3587, 3880, 'Musa textilis'],
  [3588, 3881, 'Spirogyra turfosa'],
  [3589, 3882, 'Areca catechu'],
];

$champs_a_comparer = ['field_aire_utilis_ml', 'field_gen_culture_ml', 'field_graphie_locale_ml',
  'field_intr_paper_ml', 'field_mode_prep_ml', 'field_view_pap_plant_ml', 'field_plante_ref', 'field_langue_texte'];

echo "=== VÉRIFICATION DES 5 PAIRES AVANT SUPPRESSION ===\n\n";

$tout_identique = true;

foreach ($paires as [$nid1, $nid2, $label]) {
  $n1 = \Drupal\node\Entity\Node::load($nid1);
  $n2 = \Drupal\node\Entity\Node::load($nid2);

  if (!$n1 || !$n2) {
    echo "$label : un des deux nœuds ($nid1 ou $nid2) introuvable — À VÉRIFIER MANUELLEMENT\n\n";
    $tout_identique = false;
    continue;
  }

  $diffs = [];
  foreach ($champs_a_comparer as $champ) {
    if (!$n1->hasField($champ)) continue;
    $v1 = json_encode($n1->get($champ)->getValue());
    $v2 = json_encode($n2->get($champ)->getValue());
    if ($v1 !== $v2) {
      $diffs[] = $champ;
    }
  }

  $titre_identique = $n1->label() === $n2->label();
  $cree1 = date('Y-m-d H:i', $n1->getCreatedTime());
  $cree2 = date('Y-m-d H:i', $n2->getCreatedTime());

  if (empty($diffs) && $titre_identique) {
    echo "$label (nid=$nid1 créé $cree1 / nid=$nid2 créé $cree2) : IDENTIQUES sur tous les champs — doublon confirmé\n";
  } else {
    echo "$label (nid=$nid1 / nid=$nid2) : DIFFÉRENCE trouvée — champs différents : " . implode(', ', $diffs) . (!$titre_identique ? " + titre différent" : "") . "\n";
    $tout_identique = false;
  }
}

echo "\n" . ($tout_identique ? "=== Les 5 paires sont confirmées identiques — suppression sûre du plus récent de chaque paire ===" : "=== ATTENTION : au moins une paire diffère — ne pas supprimer en masse ===") . "\n";
