<?php

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r)->id() : NULL;
}

function extraire_moyenne_depuis_html($html, $label) {
  // Cherche "<strong>Label</strong></div></td><td><div>VALEUR</div>" dans le tableau field_ident_fibre
  if (preg_match('/<strong>' . preg_quote($label, '/') . '<\/strong><\/div><\/td><td><div>(.*?)<\/div>/s', $html, $m)) {
    return trim(strip_tags($m[1]));
  }
  return NULL;
}

function extraire_herzberg_graffc($notes) {
  $herzberg = NULL;
  $graffc = NULL;
  if (preg_match('/Herzberg:\s*(.*?)\s*\|\s*Graff C:\s*(.*)/s', $notes, $m)) {
    $herzberg = trim($m[1]);
    $graffc = trim($m[2]);
  }
  return [$herzberg, $graffc];
}

function est_vide_ou_placeholder($val) {
  $val = trim($val ?? '');
  return $val === '' || preg_match('/^\.+$/', $val) || strtolower($val) === 'variable' || $val === '?';
}

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_fibre')
  ->accessCheck(FALSE)
  ->execute();

$total_avec_ident_fibre = 0;
$total_traites = 0;
$exemples_affiches = 0;

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $pf_node) {
  $pf_id = $pf_node->id();

  // Sauter Broussonetia, déjà fait manuellement
  if ($pf_id == 3829) continue;

  // Trouver la Plante liée (relation inverse : Plantes.field_fibres -> plante_fibre)
  $plante_ids = \Drupal::entityQuery('node')
    ->condition('type', 'article')
    ->condition('field_fibres', $pf_id)
    ->accessCheck(FALSE)
    ->execute();
  $plante_id = $plante_ids ? reset($plante_ids) : NULL;
  $plante = $plante_id ? \Drupal\node\Entity\Node::load($plante_id) : NULL;

  $ident_fibre_html = '';
  if ($plante && $plante->hasField('field_ident_fibre') && !$plante->get('field_ident_fibre')->isEmpty()) {
    $ident_fibre_html = $plante->get('field_ident_fibre')->value;
    $total_avec_ident_fibre++;
  }

  $longueur = $ident_fibre_html ? extraire_moyenne_depuis_html($ident_fibre_html, 'Longueur moyenne des fibres') : NULL;
  $largeur = $ident_fibre_html ? extraire_moyenne_depuis_html($ident_fibre_html, 'Largeur moyenne des fibres') : NULL;

  // Repli sur field_pf_long_min/max si pas de field_ident_fibre
  if (!$longueur && $pf_node->hasField('field_pf_long_min') && !$pf_node->get('field_pf_long_min')->isEmpty()) {
    $min = $pf_node->get('field_pf_long_min')->value;
    $max = $pf_node->hasField('field_pf_long_max') && !$pf_node->get('field_pf_long_max')->isEmpty() ? $pf_node->get('field_pf_long_max')->value : $min;
    $longueur = ($min == $max) ? "$min mm" : "$min-$max mm";
  }
  if (!$largeur && $pf_node->hasField('field_pf_larg_min') && !$pf_node->get('field_pf_larg_min')->isEmpty()) {
    $min = $pf_node->get('field_pf_larg_min')->value;
    $max = $pf_node->hasField('field_pf_larg_max') && !$pf_node->get('field_pf_larg_max')->isEmpty() ? $pf_node->get('field_pf_larg_max')->value : $min;
    $largeur = ($min == $max) ? "$min µm" : "$min-$max µm";
  }

  $notes = $pf_node->hasField('field_pf_notes') && !$pf_node->get('field_pf_notes')->isEmpty() ? strip_tags($pf_node->get('field_pf_notes')->value) : '';
  [$herzberg, $graffc] = extraire_herzberg_graffc($notes);

  $type_fibre = $pf_node->hasField('field_pf_type') ? trim($pf_node->get('field_pf_type')->value ?? '') : '';

  $criteres = [
    'Type de fibre' => est_vide_ou_placeholder($type_fibre) ? NULL : $type_fibre,
    'Longueur' => $longueur,
    'Largeur' => $largeur,
    'Extrémités' => $pf_node->hasField('field_pf_extremites') && !$pf_node->get('field_pf_extremites')->isEmpty() ? strip_tags($pf_node->get('field_pf_extremites')->value) : NULL,
    'Stries et dislocations' => $pf_node->hasField('field_pf_striations') && !$pf_node->get('field_pf_striations')->isEmpty() ? strip_tags($pf_node->get('field_pf_striations')->value) : NULL,
    'Cellules associées' => $pf_node->hasField('field_pf_cellules') && !$pf_node->get('field_pf_cellules')->isEmpty() ? strip_tags($pf_node->get('field_pf_cellules')->value) : NULL,
    'Caractéristiques particulières' => $pf_node->hasField('field_pf_particularites') && !$pf_node->get('field_pf_particularites')->isEmpty() ? strip_tags($pf_node->get('field_pf_particularites')->value) : NULL,
    'Coloration Herzberg' => est_vide_ou_placeholder($herzberg) ? NULL : $herzberg,
    'Coloration Graff C' => est_vide_ou_placeholder($graffc) ? NULL : $graffc,
  ];

  $total_traites++;

  // N'afficher en détail que les 5 premiers exemples, pour vérifier le format
  if ($exemples_affiches < 5) {
    echo "--- " . ($plante ? $plante->label() : "Plante introuvable") . " (plante_fibre nid=$pf_id) ---\n";
    foreach ($criteres as $nom => $val) {
      echo "  $nom : " . ($val ? substr($val, 0, 80) : "(rien à importer)") . "\n";
    }
    echo "\n";
    $exemples_affiches++;
  }
}

echo "=== Résumé ===\n";
echo "Fiches plante_fibre traitées (hors Broussonetia) : $total_traites\n";
echo "Dont avec field_ident_fibre exploitable (valeur moyenne) : $total_avec_ident_fibre\n";
