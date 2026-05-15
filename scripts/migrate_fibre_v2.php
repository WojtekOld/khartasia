<?php
/**
 * migrate_fibre_v2.php — corrige longueur/largeur + Herzberg
 */
$storage = \Drupal::entityTypeManager()->getStorage('node');

// Supprimer les anciens plante_fibre et réinitialiser field_fibres
$old = $storage->loadByProperties(['type' => 'plante_fibre']);
foreach($old as $o) $o->delete();
echo "Anciens supprimés : " . count($old) . PHP_EOL;

$nids = \Drupal::entityQuery('node')
  ->condition('type','article')->accessCheck(FALSE)->execute();
$nodes = $storage->loadMultiple($nids);

function normalize($s) {
  $s = strtolower($s);
  $map = ['à'=>'a','á'=>'a','â'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
          'î'=>'i','ï'=>'i','ô'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
  foreach($map as $k=>$v) $s = str_replace($k,$v,$s);
  $s = preg_replace('/[^a-z0-9\s]/u','',$s);
  return trim(preg_replace('/\s+/',' ',$s));
}

function parse_fibre_html($html) {
  $data = [];
  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
  $rows = $dom->getElementsByTagName('tr');
  foreach($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    if($cells->length < 2) continue;
    $label = normalize(strip_tags($cells->item(0)->textContent));
    $value = trim(strip_tags($cells->item(1)->textContent));
    $value = preg_replace('/\s+/',' ',$value);
    if(empty($label)||empty($value)) continue;
    $data[$label] = $value;
  }
  return $data;
}

function parse_measure($val,&$min,&$max) {
  $val = str_replace(['µm','mm','μm',' '],'',$val);
  if(preg_match('/(\d+[\.,]?\d*)[–\-](\d+[\.,]?\d*)/',$val,$m)) {
    $min=(float)str_replace(',','.',$m[1]);
    $max=(float)str_replace(',','.',$m[2]);
  } elseif(preg_match('/\((\d+[\.,]?\d*)[–\-](\d+[\.,]?\d*)\)/',$val,$m)) {
    $min=(float)str_replace(',','.',$m[1]);
    $max=(float)str_replace(',','.',$m[2]);
  } elseif(preg_match('/^(\d+[\.,]?\d*)/',$val,$m)) {
    $min=$max=(float)str_replace(',','.',$m[1]);
  }
}

function map_herzberg($val) {
  $v = strtolower($val);
  if(strpos($v,'jaune')!==false)  return 'jaune';
  if(strpos($v,'vert')!==false)   return 'vert';
  if(strpos($v,'bleu')!==false)   return 'bleu';
  if(strpos($v,'rouge')!==false)  return 'rouge';
  if(strpos($v,'viol')!==false)   return 'violet';
  if(strpos($v,'orang')!==false)  return 'orange';
  return null;
}

function map_graff($val) {
  $v = strtolower($val);
  if(strpos($v,'positif')!==false||strpos($v,'positive')!==false) return 'positif';
  if(strpos($v,'negatif')!==false||strpos($v,'negative')!==false||
     strpos($v,'négatif')!==false) return 'negatif';
  return 'variable';
}

$created=0; $skipped=0;

foreach($nodes as $plante) {
  if(!$plante->hasField('field_ident_fibre')) { $skipped++; continue; }
  if($plante->get('field_ident_fibre')->isEmpty()) { $skipped++; continue; }

  $html  = $plante->get('field_ident_fibre')->value;
  $data  = parse_fibre_html($html);
  if(empty($data)) { $skipped++; continue; }

  $fibre = $storage->create([
    'type'=>'plante_fibre',
    'title'=>$plante->label().' — Fibres',
    'status'=>1,'uid'=>1,
  ]);

  $notes = '';
  foreach($data as $label => $val) {
    if(strpos($label,'type de fibre')!==false||strpos($label,'fibre type')!==false)
      $fibre->set('field_pf_type',$val);
    elseif(strpos($label,'longueur')!==false||strpos($label,'length')!==false) {
      $min=$max=null; parse_measure($val,$min,$max);
      if($min) $fibre->set('field_pf_long_min',$min);
      if($max) $fibre->set('field_pf_long_max',$max);
    }
    elseif(strpos($label,'largeur')!==false||strpos($label,'width')!==false) {
      $min=$max=null; parse_measure($val,$min,$max);
      if($min) $fibre->set('field_pf_larg_min',$min);
      if($max) $fibre->set('field_pf_larg_max',$max);
    }
    elseif(strpos($label,'extremit')!==false||strpos($label,'ends')!==false)
      $fibre->set('field_pf_extremites',$val);
    elseif(strpos($label,'striation')!==false||strpos($label,'cross')!==false||
           strpos($label,'noeud')!==false)
      $fibre->set('field_pf_striations',$val);
    elseif(strpos($label,'cellule')!==false||strpos($label,'associated')!==false)
      $fibre->set('field_pf_cellules',$val);
    elseif(strpos($label,'particul')!==false||strpos($label,'special')!==false)
      $fibre->set('field_pf_particularites',$val);
    elseif(strpos($label,'herzberg')!==false) {
      $mapped=map_herzberg($val);
      if($mapped) $fibre->set('field_pf_herzberg',$mapped);
      $notes .= 'Herzberg: '.$val.' | ';
    }
    elseif(strpos($label,'graff')!==false) {
      $fibre->set('field_pf_graff_c',map_graff($val));
      $notes .= 'Graff C: '.$val.' | ';
    }
  }
  if($notes) $fibre->set('field_pf_notes',rtrim($notes,' | '));
  $fibre->save();
  $plante->set('field_fibres',['target_id'=>$fibre->id()]);
  $plante->save();
  $created++;
}

echo "=== Migration v2 terminée ===\n";
echo "Créés : $created\nIgnorés : $skipped\n";