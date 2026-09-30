<?php

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR de connexion : " . $e->getMessage() . "\n";
  exit(1);
}

// === 1. Quels champs sont réellement rattachés au bundle 'papers' en D7 ? ===
$stmt = $pdo->query("
  SELECT field_name
  FROM field_config_instance
  WHERE bundle = 'papers' AND entity_type = 'node'
  ORDER BY field_name
");
$champs_papers = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "=== Champs rattachés à 'papers' en D7 (" . count($champs_papers) . ") ===\n";

$resultats = [];
foreach ($champs_papers as $champ) {
  $table = "field_data_$champ";
  try {
    // Vérifier que la table existe
    $check = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
    if (!$check) {
      echo "  $champ : table '$table' introuvable — ignoré.\n";
      continue;
    }

    // Trouver la colonne de valeur principale (souvent {champ}_value ou {champ}_target_id)
    $colonnes = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $col_valeur = NULL;
    foreach ($colonnes as $c) {
      if (str_ends_with($c, '_value') || str_ends_with($c, '_target_id') || str_ends_with($c, '_tid') || str_ends_with($c, '_nid')) {
        $col_valeur = $c;
        break;
      }
    }

    $total_lignes = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE entity_type = 'node' AND bundle = 'papers'")->fetchColumn();

    $rempli = 0;
    if ($col_valeur) {
      $rempli = $pdo->query("
        SELECT COUNT(*) FROM `$table`
        WHERE entity_type = 'node' AND bundle = 'papers'
        AND `$col_valeur` IS NOT NULL AND `$col_valeur` != ''
      ")->fetchColumn();
    }

    $resultats[$champ] = ['total' => $total_lignes, 'rempli' => $rempli, 'colonne' => $col_valeur];
    echo "  $champ : $rempli lignes remplies sur $total_lignes (colonne: $col_valeur)\n";
  } catch (Exception $e) {
    echo "  $champ : erreur — " . $e->getMessage() . "\n";
  }
}

echo "\n=== Champs équivalents déjà connus côté D11 (pour référence) ===\n";
echo "field_genus_pap_entity, field_genus_taxo_pap, field_origin_taxo,\n";
echo "field_pap_local_scripture, field_pap_synonyme, field_famille_fibreuse, body\n";
