import subprocess

# Supprimer les views existantes
code = """
use Drupal\views\Entity\View;
foreach(['plante_papers','plante_noms_communs'] as $vid) {
  $v = View::load($vid);
  if($v) { $v->delete(); echo "Supprime: $vid\n"; }
}
"""
open('/var/www/html/scripts/delete_views.php','w').write('<?php\n' + code)
print('Script suppression OK')