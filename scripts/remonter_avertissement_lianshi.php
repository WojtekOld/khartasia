<?php
$node = \Drupal\node\Entity\Node::load(4591);
$corps_actuel = $node->get('body')->value;

$phrase_distinction = "Distinct du papier homophone 連史紙 de Liancheng (Fujian, bourg de Gutian, papier de bambou apparu vers 1629) et du Liansi (連四紙) de bambou de Yanshan : continuité institutionnelle et nominale d'une lignée de papiers de prestige jiangxiens, non identité de fibre.";

if (str_contains($corps_actuel, $phrase_distinction)) {
  $reste = trim(str_replace($phrase_distinction, '', $corps_actuel));
  $nouveau_corps = $phrase_distinction . ' ' . $reste;
  $node->set('body', ['value' => $nouveau_corps, 'format' => $node->get('body')->format]);
  $node->save();
  echo "Corps réordonné avec succès.\n";
} else {
  echo "Phrase exacte introuvable — le texte a peut-être déjà changé.\n";
}
