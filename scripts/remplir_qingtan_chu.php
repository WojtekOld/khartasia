<?php
/**
 * remplir_qingtan_chu.php
 *
 * Répartit les données structurées dans les champs de :
 *   - Qingtan (nid=4689) — créé le 28/09
 *   - Chu / Broussonetia papyrifera (nid=22) — enrichissement
 *
 * Champs cibles identifiés :
 *   field_nom_accepte_powo      string(255)       — nom POWO canonique
 *   field_synonymes_bot         text_long         — synonymes botaniques
 *   field_ident_fibre           text_long         — caractéristiques fibres
 *   field_aire_croissance       text_long (∞)     — zones de croissance
 *   field_saison_recolte        string            — saison récolte
 *   field_mode_extraction       list_string       — mode extraction
 *   field_taxon_cultigene       boolean           — sauvage/cultivé
 *   field_paper_name            text_long         — noms papiers issus
 *   field_statut_identification list_string       — statut
 *   body                        text_with_summary — corps (vider après répartition)
 *
 * Usage : ddev drush php:script scripts/remplir_qingtan_chu.php
 * Dry-run par défaut. Ajouter -- --apply pour écrire.
 */

declare(strict_types=1);

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$mode  = $apply ? 'APPLY' : 'DRY-RUN';

echo "\n══════════════════════════════════════════════════════════\n";
echo " Remplissage champs structurés Qingtan + Chu — $mode\n";
echo "══════════════════════════════════════════════════════════\n\n";

$ns = \Drupal::entityTypeManager()->getStorage('node');

// ── Helpers ──────────────────────────────────────────────────────────────

function khartasia_show(string $label, $val): void {
  if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
  echo "  $label : $val\n";
}

function write_field(\Drupal\node\NodeInterface $node, string $field, $value, bool $apply): void {
  if (!$node->hasField($field)) {
    echo "  [!!] Champ $field absent sur ce bundle\n";
    return;
  }
  if ($apply) {
    $node->set($field, $value);
    echo "  [W]  $field ← " . (is_string($value) ? mb_substr($value, 0, 60) : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
  } else {
    $current = $node->get($field)->getString();
    echo "  [?]  $field actuel=«" . mb_substr($current, 0, 50) . "» → sera remplacé\n";
  }
}

// ── Lister valeurs list_string disponibles ───────────────────────────────
function get_allowed(string $field_name): array {
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $field_name);
  if (!$storage) return [];
  return array_keys($storage->getSetting('allowed_values') ?? []);
}

$modes_allowed  = get_allowed('field_mode_extraction');
$statuts_allowed = get_allowed('field_statut_identification');

echo "field_mode_extraction valeurs : " . implode(', ', $modes_allowed) . "\n";
echo "field_statut_identification valeurs : " . implode(', ', $statuts_allowed) . "\n\n";

// ══════════════════════════════════════════════════════════════════════════
// QINGTAN — nid=4689
// ══════════════════════════════════════════════════════════════════════════
echo "── QINGTAN (nid=4689) ──────────────────────────────────────\n";
$q = $ns->load(4689);
if (!$q) { echo "[ERREUR] nid=4689 introuvable\n"; }
else {
  $data_q = [
    'field_nom_accepte_powo'   => 'Pteroceltis tatarinowii Maxim.',
    'field_synonymes_bot'      => implode("\n", [
      'Celtis tatarinowii Maxim. (basionyme)',
      'Pteroceltis delavayi (Franch.) C.K.Schneid.',
      'Pteroceltis tatarinowii var. sinensis (Hemsl.) C.K.Schneid.',
    ]),
    'field_ident_fibre'        =>
      "Fibres d'écorce interne (liber). Longueur : 3–8 mm. Parois fines à moyennes. " .
      "Cellules épidermiques caractéristiques (cellules calciques allongées). " .
      "Blancheur naturelle élevée. Faible teneur en lignine. " .
      "Distinction Chu/Qingtan : les fibres de Qingtan sont plus courtes et à parois plus fines " .
      "que celles de Broussonetia papyrifera (Chu, 4–20 mm). " .
      "Coloration Herzberg : rose-violet (cellulose pure). " .
      "Absence de Qingtan dans les analyses V&A 2019 (inv. E.2851-1913 et al.).",
    'field_aire_croissance'    => [
      ['value' => "Chine : Anhui (Jingxian, Xuancheng, monts Huangshan) — zone de production historique exclusive du papier Xuan. Pousse sur sols calcaires, versants rocheux, altitude 200–800 m."],
      ['value' => "Distribution secondaire : Hebei, Shandong, Henan, Shaanxi, Sichuan, Yunnan — populations relictes ou plantations récentes."],
      ['value' => "Hors Chine : absent à l'état naturel. Culture expérimentale au Japon (Echizen) non documentée à l'échelle industrielle."],
    ],
    'field_saison_recolte'     => 'Printemps (mars–avril) — avant la montée de sève',
    'field_paper_name'         =>
      "Xuan zhi (宣纸) — papier Xuan, Jingxian, Anhui. " .
      "Indication Géographique Protégée (2002). UNESCO PCI (2009). " .
      "Composition standard : 60–80% écorce Qingtan + 20–40% paille de riz du Yangtze. " .
      "NB : les papiers peints cantonais d'exportation (XVIIe–XIXe s.) n'utilisent PAS le Xuan " .
      "(inadapté : prix, absorption, format, analyse fibres V&A 2019).",
    'field_taxon_cultigene'    => 0,
  ];

  // Mode extraction — sélectionner la valeur la plus proche
  if (!empty($modes_allowed)) {
    // Chercher 'ecorce', 'bark', 'liber' ou prendre le premier disponible
    $mode_val = NULL;
    foreach ($modes_allowed as $v) {
      if (preg_match('/ecorce|bark|liber|inner/i', $v)) { $mode_val = $v; break; }
    }
    if (!$mode_val) $mode_val = $modes_allowed[0];
    $data_q['field_mode_extraction'] = $mode_val;
  }

  // Statut identification
  if (!empty($statuts_allowed)) {
    $statut_val = NULL;
    foreach ($statuts_allowed as $v) {
      if (preg_match('/identif|valid|confirm/i', $v)) { $statut_val = $v; break; }
    }
    if (!$statut_val) $statut_val = $statuts_allowed[0];
    $data_q['field_statut_identification'] = $statut_val;
  }

  foreach ($data_q as $field => $value) {
    write_field($q, $field, $value, $apply);
  }

  if ($apply) {
    // Vider le body après répartition (optionnel — commenter pour conserver)
    // $q->set('body', ['value' => '', 'format' => 'full_html', 'summary' => '']);
    $q->save();
    echo "\n  [OK] Qingtan sauvegardé.\n";
  }
}

// ══════════════════════════════════════════════════════════════════════════
// CHU — nid=22
// ══════════════════════════════════════════════════════════════════════════
echo "\n── CHU / Broussonetia papyrifera (nid=22) ──────────────────\n";
$chu = $ns->load(22);
if (!$chu) { echo "[ERREUR] nid=22 introuvable\n"; }
else {
  // Inspecter les valeurs actuelles
  echo "  Titre actuel : " . $chu->label() . "\n";
  echo "  field_nom_accepte_powo actuel : " . $chu->get('field_nom_accepte_powo')->getString() . "\n";
  echo "  field_ident_fibre actuel : " . mb_substr($chu->get('field_ident_fibre')->getString(), 0, 80) . "...\n";
  echo "  field_paper_name actuel : " . mb_substr($chu->get('field_paper_name')->getString(), 0, 80) . "...\n";

  $data_chu = [];

  // N'écraser que si vide
  if (empty($chu->get('field_nom_accepte_powo')->getString())) {
    $data_chu['field_nom_accepte_powo'] = 'Broussonetia papyrifera (L.) L\'Hér. ex Vent.';
  }

  if (empty($chu->get('field_synonymes_bot')->getString())) {
    $data_chu['field_synonymes_bot'] = implode("\n", [
      'Morus papyrifera L. (basionyme)',
      'Broussonetia kaempferi Siebold',
      'Broussonetia papyrifera var. australis Endl.',
      'Papyrius japonica Lam.',
      'Broussonetia papyrifera var. leucocarpa Aiton',
    ]);
  }

  if (empty($chu->get('field_ident_fibre')->getString())) {
    $data_chu['field_ident_fibre'] =
      "Fibres d'écorce interne (liber). Longueur : 4–20 mm (très longues). " .
      "Parois épaisses, robustes. Surface légèrement denticulée visible au MEB. " .
      "Coloration Herzberg : rose-violet (cellulose). " .
      "Candidat principal au rôle de support des papiers peints cantonais d'exportation " .
      "périodes I–III (1720–1795), selon Note Yangban 28/09/2026. " .
      "Fibres trouvées dans analyses V&A Conservation Dept. 2019 (E.2851-1913 et al.). " .
      "Distinction avec Qingtan (nid=4689) : longueur supérieure, parois plus épaisses.";
  }

  if (empty($chu->get('field_aire_croissance')->getString())) {
    $data_chu['field_aire_croissance'] = [
      ['value' => "Chine : Fujian (Liancheng, Yanshan — production historique Liansi/Lianshi), Zhejiang, Jiangxi, Guangdong (province de Canton)."],
      ['value' => "Japon : Tosa (Kochi), Echizen (Fukui), Mino (Gifu) — Kozo japonais, base des papiers washi."],
      ['value' => "Corée : Hanji — papier coréen traditionnel issu exclusivement de Broussonetia."],
      ['value' => "Distribution naturelle : Asie du Sud-Est, Inde, Polynésie (tissu d'écorce tapa). Espèce très adaptable, cultivée dans les zones tropicales et subtropicales."],
    ];
  }

  if (empty($chu->get('field_paper_name')->getString())) {
    $data_chu['field_paper_name'] =
      "Liansi zhi (连史纸 / 廉史纸) — Liancheng, Fujian. " .
      "Washi japonais (和紙) : Kozo — Tosa, Echizen, Mino. " .
      "Hanji coréen (한지). " .
      "Hypothèse Wallpaper Canton : support probable des papiers peints d'exportation " .
      "périodes I–III (1720–1795). À confirmer par banque de référence fibres Fujian.";
  }

  if (!$apply) {
    echo "\n  Données à écrire (dry-run) :\n";
    foreach ($data_chu as $field => $value) {
      $preview = is_array($value) ? count($value) . ' items' : mb_substr((string)$value, 0, 60);
      echo "    $field → $preview\n";
    }
  } else {
    foreach ($data_chu as $field => $value) {
      write_field($chu, $field, $value, TRUE);
    }
    $chu->save();
    echo "\n  [OK] Chu sauvegardé.\n";
  }
}

// ── Résumé ────────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════════════════════════════\n";
if (!$apply) {
  echo " DRY-RUN terminé. Relancer avec -- --apply pour écrire.\n";
  echo " Commande : ddev drush php:script scripts/remplir_qingtan_chu.php -- --apply\n";
} else {
  echo " APPLY terminé.\n";
  echo " Vérifier :\n";
  echo "   /fr/node/4689 (Qingtan)\n";
  echo "   /fr/node/22   (Chu)\n";
}
echo "══════════════════════════════════════════════════════════\n\n";
