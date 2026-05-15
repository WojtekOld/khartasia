<?php
$storage = \Drupal::entityTypeManager()->getStorage('node');
$fibres = $storage->loadByProperties(['type' => 'plante_fibre']);
$stats = ['type'=>0,'herzberg'=>0,'graff'=>0,'long'=>0,'larg'=>0,'extremites'=>0];
foreach($fibres as $f) {
  if(!$f->get('field_pf_type')->isEmpty())      $stats['type']++;
  if(!$f->get('field_pf_herzberg')->isEmpty())  $stats['herzberg']++;
  if(!$f->get('field_pf_graff_c')->isEmpty())   $stats['graff']++;
  if(!$f->get('field_pf_long_min')->isEmpty())  $stats['long']++;
  if(!$f->get('field_pf_larg_min')->isEmpty())  $stats['larg']++;
  if(!$f->get('field_pf_extremites')->isEmpty()) $stats['extremites']++;
}
echo "Total plante_fibre : " . count($fibres) . PHP_EOL;
foreach($stats as $k=>$v) echo $k . " renseigné : $v\n";

// Exemple détaillé
foreach($fibres as $f) {
  echo "\nExemple : " . $f->label() . PHP_EOL;
  echo "  Type     : " . $f->get('field_pf_type')->value . PHP_EOL;
  echo "  Herzberg : " . $f->get('field_pf_herzberg')->value . PHP_EOL;
  echo "  Graff C  : " . $f->get('field_pf_graff_c')->value . PHP_EOL;
  echo "  Long min : " . $f->get('field_pf_long_min')->value . PHP_EOL;
  echo "  Long max : " . $f->get('field_pf_long_max')->value . PHP_EOL;
  echo "  Notes    : " . substr($f->get('field_pf_notes')->value ?? '', 0, 100) . PHP_EOL;
  break;
}