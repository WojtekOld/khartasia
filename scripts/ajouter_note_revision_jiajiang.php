<?php
$node = \Drupal\node\Entity\Node::load(4596);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Note pour la suite : les ateliers nommés dans le corps (Huayi/Fanggou, Huatou, Zhuangyuan) représentent des variantes de procédé, pas des papiers distincts — traité en généralité honnête dans les attestations Méthode plutôt qu'en fausse précision. Question ouverte, en attente : si Zhubanqiao (Liu Honghe) et Zhongxian (Han Ji'an) sont construits et confirment un motif récurrent de porteurs de savoir-faire nommés à travers plusieurs papiers, envisager une vraie entité 'Artisan/Porteur de savoir-faire' plutôt que du texte libre — pas à décider sur ce seul cas.");
$node->save();
echo "Note de révision enregistrée sur nid=4596.\n";
