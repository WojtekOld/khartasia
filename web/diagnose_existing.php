<?php
/**
 * diagnose_existing.php
 * Version exécutable sur les données actuelles de Khartasia
 * Précurseur du KhartasiaContradictionDetector
 */

class KhartasiaAuditor {

  // Détecte les "bruits" dans common_names (content type, pas taxonomy)
  public function auditCommonNames() {
    $report = [
      'total' => 0,
      'sans_taxo' => [],
      'sans_langue' => [],
      'doublons_exact' => [],
      'doublons_cross_langue' => [],
      'noms_generiques' => [],  // "bambou", "kozo" sans précision
      'noms_lieux' => [],       // contiennent toponyme connu
    ];

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'common_names')
      ->accessCheck(FALSE)->execute();
    $report['total'] = count($nids);

    // Index pour détection doublons
    $by_titre = [];
    $toponymes = ['xuan', 'xuan zhi', 'mino', 'echizen', 'tosa',
                  'inshu', 'ogawa', 'jingxian', 'xuancheng',
                  'anhui', 'yunnan', 'sichuan'];
    $generiques = ['bambou', 'bamboo', '竹', 'kozo', 'mulberry',
                   'paper mulberry', 'mûrier', 'murier', '楮', '构'];

    foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $node) {
      $titre = trim($node->label());
      $titre_lower = mb_strtolower($titre);
      $nid = $node->id();

      // Langue
      $lang = $node->get('field_language_taxo_vern')->entity;
      $lang_label = $lang ? $lang->label() : 'INCONNUE';

      // Taxo
      $taxo = $node->get('field_genus_taxo')->referencedEntities();
      $taxo_labels = array_map(fn($t) => $t->label(), $taxo);

      // Sans taxo
      if (empty($taxo))
        $report['sans_taxo'][] = "NID $nid [$lang_label]: $titre";

      // Sans langue
      if (!$lang)
        $report['sans_langue'][] = "NID $nid: $titre";

      // Index doublons
      if (!isset($by_titre[$titre_lower]))
        $by_titre[$titre_lower] = [];
      $by_titre[$titre_lower][] = [
        'nid' => $nid,
        'langue' => $lang_label,
        'taxo' => $taxo_labels,
      ];

      // Noms génériques
      foreach ($generiques as $g) {
        if ($titre_lower === $g || $titre_lower === $g . 's')
          $report['noms_generiques'][] = "NID $nid [$lang_label]: $titre → " . implode(', ', $taxo_labels);
      }

      // Noms lieux
      foreach ($toponymes as $topo) {
        if (str_contains($titre_lower, $topo))
          $report['noms_lieux'][] = "NID $nid [$lang_label]: $titre";
      }
    }

    // Doublons
    foreach ($by_titre as $titre => $entries) {
      if (count($entries) < 2) continue;
      $langs = array_column($entries, 'langue');
      $unique_langs = array_unique($langs);

      if (count($entries) > count($unique_langs)) {
        // Même nom, même langue → doublon exact
        $report['doublons_exact'][] = "'$titre' : " . count($entries) . "x [" . implode(', ', $langs) . "]";
      } else {
        // Même nom, langues différentes → cross-langue (informatif)
        $taxos = array_unique(array_merge(...array_column($entries, 'taxo')));
        $report['doublons_cross_langue'][] = "'$titre' : [" . implode(', ', $langs) . "] → " . implode(', ', $taxos);
      }
    }

    return $report;
  }

  public function auditPapers() {
    $report = [
      'total' => 0,
      'sans_espece' => [],
      'multi_espece' => [],
      'sans_origine' => [],
      'noms_generiques' => [],
      'doublons' => [],
    ];

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'papers')
      ->accessCheck(FALSE)->execute();
    $report['total'] = count($nids);

    $generiques_papier = ['papier', 'paper', 'washi', '紙', '纸',
                           'hanji', '한지', 'kozo paper', 'bamboo paper'];
    $by_titre = [];

    foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $node) {
      $titre = trim($node->label());
      $titre_lower = mb_strtolower($titre);
      $nid = $node->id();

      $especes = $node->get('field_genus_taxo_pap')->referencedEntities();
      $espece_labels = array_map(fn($e) => $e->label(), $especes);

      $origin = $node->get('field_origin_taxo')->entity;

      // Sans espèce
      if (empty($especes))
        $report['sans_espece'][] = "NID $nid: $titre";

      // Multi-espèce
      if (count($especes) > 1)
        $report['multi_espece'][] = "NID $nid: $titre → " . implode(' + ', $espece_labels);

      // Sans origine
      if (!$origin)
        $report['sans_origine'][] = "NID $nid: $titre";

      // Noms génériques
      foreach ($generiques_papier as $g) {
        if ($titre_lower === $g)
          $report['noms_generiques'][] = "NID $nid: $titre";
      }

      // Index doublons
      if (!isset($by_titre[$titre_lower]))
        $by_titre[$titre_lower] = [];
      $by_titre[$titre_lower][] = $nid;
    }

    foreach ($by_titre as $titre => $nids_list) {
      if (count($nids_list) > 1)
        $report['doublons'][] = "'$titre' : NID " . implode(', ', $nids_list);
    }

    return $report;
  }

  public function printReport($r, $title) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 50) . "\n";
    echo "Total : {$r['total']}\n\n";

    $sections = [
      'sans_taxo'            => 'SANS ESPECE LIEE',
      'sans_espece'          => 'SANS ESPECE LIEE',
      'sans_langue'          => 'SANS LANGUE',
      'sans_origine'         => 'SANS ORIGINE GEO',
      'doublons_exact'       => 'DOUBLONS EXACTS (bruit)',
      'doublons'             => 'DOUBLONS',
      'doublons_cross_langue'=> 'MEME NOM / LANGUES DIFF (informatif)',
      'noms_generiques'      => 'NOMS TROP GENERIQUES (bruit)',
      'noms_lieux'           => 'NOMS DE LIEU (a qualifier)',
      'multi_espece'         => 'MULTI-ESPECES (a documenter)',
    ];

    foreach ($sections as $key => $label) {
      if (!isset($r[$key]) || empty($r[$key])) continue;
      $count = count($r[$key]);
      $pct = round($count / $r['total'] * 100);
      echo "--- $label : $count ($pct%) ---\n";
      foreach (array_slice($r[$key], 0, 15) as $line)
        echo "  $line\n";
      if ($count > 15) echo "  ... et " . ($count - 15) . " autres\n";
      echo "\n";
    }
  }
}

$auditor = new KhartasiaAuditor();

$cn = $auditor->auditCommonNames();
$auditor->printReport($cn, 'COMMON NAMES — ' . $cn['total'] . ' noeuds');

$papers = $auditor->auditPapers();
$auditor->printReport($papers, 'PAPERS — ' . $papers['total'] . ' noeuds');

echo "\n=== SYNTHESE BRUIT INFORMATIQUE ===\n";
$bruit_cn = count($cn['doublons_exact']) + count($cn['noms_generiques']);
$bruit_p = count($papers['doublons']) + count($papers['noms_generiques']);
echo "Common names : $bruit_cn entrées bruitées sur {$cn['total']}\n";
echo "Papers : $bruit_p entrées bruitées sur {$papers['total']}\n";
echo "Sans espece liee : " . count($cn['sans_taxo']) . " common_names, " . count($papers['sans_espece']) . " papers\n";
echo "Multi-especes a documenter : " . count($papers['multi_espece']) . " papers\n";

echo "\nDone\n";
