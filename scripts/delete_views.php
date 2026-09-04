<?php

use Drupaliews\Entity\View;
foreach(['plante_papers','plante_noms_communs'] as $vid) {
  $v = View::load($vid);
  if($v) { $v->delete(); echo "Supprime: $vid
"; }
}
