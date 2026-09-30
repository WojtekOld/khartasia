<?php
use Drupal\node\Entity\Node;

// Liansi (4578) — ajouter une note sur l'usage populaire 連史/連四
$liansi = Node::load(4578);
$synonymes = $liansi->get('field_pap_synonyme')->getValue();
$synonymes[] = ['value' => "連史紙 (Lianshi zhi) — dans l'usage courant du Jiangxi, graphie souvent traitée comme équivalente à 連四紙 (辭源 : graphie corrompue). Khartasia maintient néanmoins la distinction institutionnelle avec la fiche « Lianshi zhi (Jiangxi) » (lignée Xishan/mûrier, Ming), les deux traditions ayant probablement convergé géographiquement après le transfert de la manufacture de Xishan vers la région de Yanshan, sans que cela efface leur origine distincte selon les sources Ming primaires."];
$liansi->set('field_pap_synonyme', $synonymes);
$liansi->setNewRevision(TRUE);
$liansi->setRevisionLogMessage("Note ajoutée sur la convergence Xishan/Yanshan — cf. Fiche Khartasia Formats Liansi/Jiajiang, doc. complémentaire sur 連史紙 comme graphie parfois interchangeable avec 連四紙 dans l'usage moderne du Jiangxi.");
$liansi->save();
echo "Note ajoutée à Liansi (4578).\n";

// Lianshi zhi jiangxien (4591) — note miroir
$lianshi = Node::load(4591);
$lianshi->setNewRevision(TRUE);
$lianshi->setRevisionLogMessage("Attention terminologique : les sources modernes du Jiangxi traitent parfois 連史紙 comme une simple graphie de 連四紙 (Liansi, bambou, Yanshan) — 辭源 le confirme. Cette fiche maintient la distinction institutionnelle fondée sur les sources Ming primaires (Tu Long, Liu Ruoyu : manufacture de Xishan, mûrier, Yongle 1403), les deux lignées ayant probablement convergé géographiquement après le transfert de la manufacture vers la région de Yanshan — sans que cela efface leur origine distincte. Ne pas fusionner les deux fiches sans réexamen approfondi.");
$lianshi->save();
echo "Note ajoutée à Lianshi zhi jiangxien (4591).\n";
