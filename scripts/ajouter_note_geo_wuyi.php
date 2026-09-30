<?php
$liansi = \Drupal\node\Entity\Node::load(4578);
$synonymes = $liansi->get('field_pap_synonyme')->getValue();
$synonymes[] = ['value' => "Note géographique : le xian de Yanshan borde directement la ville de Wuyishan et le xian de Guangze (Fujian), le long de la même chaîne des monts Wuyi qui traverse la frontière Jiangxi-Fujian — une proximité réelle, pas seulement régionale au sens large, qui rend historiquement compréhensible la confusion entre ce papier et le 連史紙 homophone de Liancheng (également sur ce massif, plus au sud)."];
$liansi->set('field_pap_synonyme', $synonymes);
$liansi->setNewRevision(TRUE);
$liansi->setRevisionLogMessage("Note ajoutée : Yanshan et Liancheng sont géographiquement proches (monts Wuyi, frontière Jiangxi-Fujian), pas de simples homophones sans lien territorial — confirmé par recherche (Yanshan borde directement Wuyishan/Guangze, Fujian).");
$liansi->save();
echo "Note géographique ajoutée à Liansi.\n";
