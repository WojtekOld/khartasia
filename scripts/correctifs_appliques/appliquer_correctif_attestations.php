<?php
// appliquer_correctif_attestations.php — à lancer depuis la racine du projet, de préférence via DDEV :
//   ddev exec php appliquer_correctif_attestations.php              (simulation, n'écrit rien)
//   ddev exec php appliquer_correctif_attestations.php --appliquer  (écrit, après sauvegarde)
// Remplace, dans le thème khartasia_ui :
//   1. la fonction khartasia_ui_preprocess_node_papers_attestations() du .theme (et ajoute la fonction pure de regroupement) ;
//   2. le bloc « Attestations » de templates/node/node--papers.html.twig ;
//   3. la comparaison de statut de templates/node/node--common-names.html.twig (même défaut d'affichage).
// Effets : le statut « atteste » n'est plus affiché ; les autres statuts le sont en clair ; le badge « ⚠ signalée » ne
// s'affiche plus que pour une discussion ; les attestations repliées par l'éditeur (champ field_att_replie_dans, qui
// n'existe pas encore) sont regroupées sur une seule ligne. Sans ce champ, rien d'autre ne change.

$appliquer = in_array('--appliquer', $argv ?? [], TRUE);
$theme = 'web/themes/custom/khartasia_ui/khartasia_ui.theme';
$twig = 'web/themes/custom/khartasia_ui/templates/node/node--papers.html.twig';
$noms = 'web/themes/custom/khartasia_ui/templates/node/node--common-names.html.twig';
$dossier_sauvegardes = '_sauvegardes_locales';

$nouveau_theme = <<<'KH_THEME_FIN'
/**
 * Regroupe, pour un même type d'attestation, celles qui énoncent la même
 * affirmation : une attestation dont le champ réservé aux éditeurs
 * « se replie dans » pointe vers une autre attestation du même type est
 * repliée dans celle-ci, dont la ligne affiche alors toutes les sources.
 *
 * Fonction pure (ni base de données ni entités), donc testable seule.
 *
 * @param array $liste
 *   Entrées d'un même type. Chacune porte au moins 'nid', 'replie_dans'
 *   (nid cible ou NULL) et 'groupe_source' (la source de cette attestation).
 *
 * @return array
 *   Les entrées conservées, chacune avec 'sources' : la liste des groupes de
 *   source de l'entrée puis de celles qui s'y sont repliées. Une cible absente
 *   de la liste, ou une boucle, laisse l'attestation affichée normalement.
 */
function khartasia_ui_regrouper_attestations(array $liste) {
  $par_nid = [];
  foreach ($liste as $entree) {
    $par_nid[$entree['nid']] = $entree;
  }

  $cible_de = [];
  foreach ($liste as $entree) {
    $nid = $entree['nid'];
    $vus = [$nid => TRUE];
    $replie = $entree['replie_dans'] ?? NULL;
    $cible = NULL;
    $boucle = FALSE;
    while ($replie !== NULL && isset($par_nid[$replie])) {
      if (isset($vus[$replie])) {
        $boucle = TRUE;
        break;
      }
      $cible = $replie;
      $vus[$replie] = TRUE;
      $replie = $par_nid[$replie]['replie_dans'] ?? NULL;
    }
    if ($cible !== NULL && !$boucle) {
      $cible_de[$nid] = $cible;
    }
  }

  $repliees = [];
  foreach ($liste as $entree) {
    if (isset($cible_de[$entree['nid']])) {
      $repliees[$cible_de[$entree['nid']]][] = $entree['groupe_source'];
    }
  }

  $resultat = [];
  foreach ($liste as $entree) {
    if (isset($cible_de[$entree['nid']])) {
      continue;
    }
    $entree['sources'] = array_merge([$entree['groupe_source']], $repliees[$entree['nid']] ?? []);
    unset($entree['groupe_source'], $entree['replie_dans']);
    $resultat[] = $entree;
  }
  return $resultat;
}

function khartasia_ui_preprocess_node_papers_attestations(&$variables, $node) {
  $db = \Drupal::database();

  $att_ids = $db->query("
    SELECT DISTINCT f.entity_id
    FROM node__field_att_papier f
    INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1
    WHERE f.field_att_papier_target_id = :nid
    AND f.bundle = 'attestation'
    AND f.deleted = 0
  ", [':nid' => $node->id()])->fetchCol();

  $attestations_par_type = [];
  $cache_tags = [];

  foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
    $cache_tags = array_merge($cache_tags, $att->getCacheTags());

    $type_term = $att->hasField('field_att_type') && !$att->get('field_att_type')->isEmpty()
      ? $att->get('field_att_type')->entity : NULL;
    $type_label = $type_term ? $type_term->label() : 'Non typé';

    $axe_term = $att->hasField('field_att_axe') && !$att->get('field_att_axe')->isEmpty()
      ? $att->get('field_att_axe')->entity : NULL;

    $periodes = [];
    if ($att->hasField('field_att_periode') && !$att->get('field_att_periode')->isEmpty()) {
      foreach ($att->get('field_att_periode') as $item) {
        if ($item->entity) $periodes[] = $item->entity->label();
      }
    }

    $lieu = $att->hasField('field_att_lieu') && !$att->get('field_att_lieu')->isEmpty()
      ? $att->get('field_att_lieu')->entity->label() : NULL;

    $source_label = NULL;
    if ($att->hasField('field_att_source') && !$att->get('field_att_source')->isEmpty()) {
      $source_entite = $att->get('field_att_source')->entity;
      $source_label = $source_entite ? $source_entite->label() : NULL;
    }

    $motifs = ($att->hasField('field_att_revue_motifs') && !$att->get('field_att_revue_motifs')->isEmpty())
      ? array_column($att->get('field_att_revue_motifs')->getValue(), 'value') : [];
    $a_revoir = $att->hasField('field_att_a_revoir') ? (bool) $att->get('field_att_a_revoir')->value : FALSE;

    $url_dispute = NULL;
    if (!in_array('dispute_signalee', $motifs, TRUE)) {
      try {
        $generated = \Drupal\Core\Url::fromRoute('khartasia_core.signaler_dispute', ['attestation' => $att->id()])
          ->toString(TRUE);
        $url_dispute = $generated->getGeneratedUrl();
        $cache_tags = array_merge($cache_tags, $generated->getCacheTags());
      }
      catch (\Exception $e) {
        $url_dispute = NULL;
      }
    }

    $statut = $att->get('field_att_statut')->value ?? '';
    $replie_dans = ($att->hasField('field_att_replie_dans') && !$att->get('field_att_replie_dans')->isEmpty())
      ? (int) $att->get('field_att_replie_dans')->target_id : NULL;

    $attestations_par_type[$type_label][] = [
      'nid' => $att->id(),
      'axe' => $axe_term ? $axe_term->label() : NULL,
      'valeur' => $att->get('field_att_valeur')->value ?? '',
      'statut' => $statut,
      'periodes' => $periodes,
      'lieu' => $lieu,
      'source' => $source_label,
      'a_revoir' => $a_revoir,
      'motifs' => $motifs,
      'url_dispute' => $url_dispute,
      'replie_dans' => $replie_dans,
      // Une « source » = une attestation : son libellé, son statut, et si une
      // discussion est en cours (seul motif rendu public ; les autres motifs
      // de revue, comme une source absente, restent réservés aux éditeurs).
      'groupe_source' => [
        'nid' => $att->id(),
        'label' => $source_label,
        'statut' => $statut,
        'dispute' => in_array('dispute_signalee', $motifs, TRUE),
        'url_dispute' => $url_dispute,
      ],
    ];
  }

  foreach ($attestations_par_type as $type_label => $liste) {
    $attestations_par_type[$type_label] = khartasia_ui_regrouper_attestations($liste);
  }
  ksort($attestations_par_type);

  $variables['attestations_par_type'] = $attestations_par_type;
  $variables['#cache']['tags'] = array_merge($variables['#cache']['tags'] ?? [], array_unique($cache_tags));
}
KH_THEME_FIN;

$nouveau_twig = <<<'KH_TWIG_FIN'
      {# Attestations — datation, géographie, composition, agent de traitement... #}
      {% if attestations_par_type %}
        {% set kh_statuts = {'probable': 'probable', 'hypothetique': 'hypothétique', 'refute': 'réfuté', 'inconnu': 'à vérifier'} %}
        <section class="kh-plante__section">
          <h2 class="kh-plante__section-title">Attestations</h2>
          {% for type_label, liste in attestations_par_type %}
            <div class="kh-noms-group">
              <h4 class="kh-noms-group__title">{{ type_label }}</h4>
              <ul class="kh-papers-group__list">
                {% for att in liste %}
                  <li class="kh-papers-group__item">
                    {% if att.axe %}<strong>{{ att.axe }}</strong> — {% endif %}
                    {{ att.valeur }}
                    {% if att.periodes %}<span class="kh-nom-scripture">({{ att.periodes|join(', ') }})</span>{% endif %}
                    {% if att.lieu %}<span class="kh-nom-scripture">— {{ att.lieu }}</span>{% endif %}
                    {% for s in att.sources %}
                      {% if s.label %}<span class="kh-fibre__notes">[source : {{ s.label }}]</span>{% endif %}
                      {% if s.dispute %}<span class="kh-dispute-badge" title="Une discussion est en cours">⚠ signalée</span>{% endif %}
                      {% if s.url_dispute %}<a href="{{ s.url_dispute }}" class="kh-dispute-lien">Signaler une discussion</a>{% endif %}
                      {% if s.statut and s.statut != 'atteste' %}<em class="kh-fibre__notes">[{{ kh_statuts[s.statut]|default(s.statut) }}]</em>{% endif %}
                    {% endfor %}
                  </li>
                {% endfor %}
              </ul>
            </div>
          {% endfor %}
        </section>
      {% endif %}
KH_TWIG_FIN;

function lire($chemin) {
  if (!is_file($chemin)) { echo "ERREUR : fichier introuvable : $chemin (lancez ce script depuis la racine du projet)\n"; exit(1); }
  $c = file_get_contents($chemin);
  $bom = substr($c, 0, 3) === "\xEF\xBB\xBF";
  if ($bom) { $c = substr($c, 3); }
  return [$c, $bom, strpos($c, "\r\n") !== FALSE ? "\r\n" : "\n"];
}
function vers_eol($texte, $eol) { return str_replace("\n", $eol, str_replace("\r\n", "\n", $texte)); }
function arreter($msg) { echo "ERREUR : $msg\nAucun fichier n'a été modifié.\n"; exit(1); }

echo "=== Correctif des attestations (thème) — " . ($appliquer ? 'APPLICATION' : 'SIMULATION') . " ===\n";

// --- .theme ---
list($c_theme, $bom_theme, $eol_theme) = lire($theme);
$deja_theme = strpos($c_theme, 'khartasia_ui_regrouper_attestations') !== FALSE;
if (!$deja_theme) {
  if (!preg_match('/^function khartasia_ui_preprocess_node_papers_attestations\(/m', $c_theme, $m1, PREG_OFFSET_CAPTURE)) { arreter("fonction khartasia_ui_preprocess_node_papers_attestations introuvable dans $theme."); }
  $debut = $m1[0][1];
  if (!preg_match('/^function khartasia_ui_preprocess_html\(/m', $c_theme, $m2, PREG_OFFSET_CAPTURE, $debut)) { arreter("borne de fin (khartasia_ui_preprocess_html) introuvable après la fonction à remplacer."); }
  $fin = $m2[0][1];
  $ancienne = substr($c_theme, $debut, $fin - $debut);
  if (strpos($ancienne, 'attestations_par_type') === FALSE || strpos($ancienne, 'khartasia_core.signaler_dispute') === FALSE) { arreter("la fonction trouvée ne ressemble pas à celle attendue (elle devrait construire attestations_par_type et le lien de dispute)."); }
  if (substr_count($c_theme, 'function khartasia_ui_preprocess_node_papers_attestations(') !== 1) { arreter("la fonction apparaît plusieurs fois dans $theme."); }
  $nouveau_c_theme = substr($c_theme, 0, $debut) . vers_eol($nouveau_theme, $eol_theme) . $eol_theme . $eol_theme . substr($c_theme, $fin);
  echo "\n.theme : fonction à remplacer trouvée (" . substr_count($ancienne, "\n") . " lignes) -> " . (substr_count($nouveau_theme, "\n") + 1) . " lignes (dont la fonction pure de regroupement).\n";
  $tmp = tempnam(sys_get_temp_dir(), 'kh_theme_') . '.php';
  file_put_contents($tmp, $nouveau_c_theme);
  exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
  @unlink($tmp);
  if ($code !== 0) { arreter("le .theme obtenu n'a pas une syntaxe PHP valide : " . implode(' ', $sortie)); }
  echo "  syntaxe PHP du résultat : OK\n";
} else { echo "\n.theme : déjà corrigé (la fonction de regroupement existe) ; rien à faire.\n"; }

// --- gabarit Twig ---
list($c_twig, $bom_twig, $eol_twig) = lire($twig);
$deja_twig = strpos($c_twig, 'att.sources') !== FALSE;
if (!$deja_twig) {
  if (!preg_match('/^[ \t]*\{# Attestations[^\n]*#\}[ \t]*\r?\n/m', $c_twig, $m3, PREG_OFFSET_CAPTURE)) { arreter("repère « {# Attestations … #} » introuvable dans $twig."); }
  $debut_t = $m3[0][1];
  if (!preg_match('/^[ \t]*<\/div>\{# fin col-left #\}/m', $c_twig, $m4, PREG_OFFSET_CAPTURE, $debut_t)) { arreter("repère « </div>{# fin col-left #} » introuvable après le bloc à remplacer."); }
  $fin_t = $m4[0][1];
  $ancien_bloc = substr($c_twig, $debut_t, $fin_t - $debut_t);
  if (strpos($ancien_bloc, 'attestations_par_type') === FALSE || strpos($ancien_bloc, 'Signaler une discussion') === FALSE) { arreter("le bloc trouvé ne ressemble pas à celui attendu."); }
  if (substr_count($c_twig, '{# Attestations') !== 1) { arreter("le repère « {# Attestations » apparaît plusieurs fois dans $twig."); }
  $nouveau_c_twig = substr($c_twig, 0, $debut_t) . vers_eol($nouveau_twig, $eol_twig) . $eol_twig . $eol_twig . substr($c_twig, $fin_t);
  echo "\ngabarit : bloc à remplacer trouvé (" . substr_count($ancien_bloc, "\n") . " lignes) -> " . (substr_count($nouveau_twig, "\n") + 1) . " lignes.\n";
} else { echo "\ngabarit : déjà corrigé ; rien à faire.\n"; }

// --- gabarit des noms vernaculaires : même comparaison de statut obsolète ---
list($c_noms, $bom_noms, $eol_noms) = lire($noms);
$ancien_noms = "{% if e.statut and e.statut != 'attesté' %}<em class=\"kh-fibre__notes\">[{{ e.statut }}]</em>{% endif %}";
$nouveau_noms = "{% if e.statut and e.statut != 'atteste' %}<em class=\"kh-fibre__notes\">[{{ {'probable': 'probable', 'hypothetique': 'hypothétique', 'refute': 'réfuté', 'inconnu': 'à vérifier'}[e.statut]|default(e.statut) }}]</em>{% endif %}";
$deja_noms = strpos($c_noms, "e.statut != 'atteste'") !== FALSE;
if (!$deja_noms) {
  $n_occ = substr_count($c_noms, $ancien_noms);
  if ($n_occ !== 1) { arreter("la comparaison de statut attendue apparaît $n_occ fois dans $noms (une seule fois attendue)."); }
  $nouveau_c_noms = str_replace($ancien_noms, $nouveau_noms, $c_noms);
  echo "\nnoms vernaculaires : comparaison de statut obsolète trouvée (1 occurrence) -> à remplacer.\n";
} else { echo "\nnoms vernaculaires : déjà corrigé ; rien à faire.\n"; }

if ($deja_theme && $deja_twig && $deja_noms) { echo "\nLes trois fichiers sont déjà corrigés.\n"; exit(0); }
if (!$appliquer) { echo "\nSimulation : rien n'a été écrit. Relancez avec --appliquer pour appliquer.\n"; exit(0); }

// --- écriture, après sauvegarde ---
if (!is_dir($dossier_sauvegardes)) { mkdir($dossier_sauvegardes, 0775, TRUE); }
$horodatage = date('Ymd_His');
foreach ([[$theme, $deja_theme], [$twig, $deja_twig], [$noms, $deja_noms]] as list($chemin, $deja)) {
  if ($deja) { continue; }
  $copie = $dossier_sauvegardes . '/' . basename($chemin) . '.avant_correctif_attestations_' . $horodatage;
  if (!copy($chemin, $copie)) { arreter("sauvegarde impossible : $copie"); }
  echo "sauvegarde : $copie\n";
}
if (!$deja_theme) { file_put_contents($theme, ($bom_theme ? "\xEF\xBB\xBF" : '') . $nouveau_c_theme); echo "écrit : $theme\n"; }
if (!$deja_twig) { file_put_contents($twig, ($bom_twig ? "\xEF\xBB\xBF" : '') . $nouveau_c_twig); echo "écrit : $twig\n"; }
if (!$deja_noms) { file_put_contents($noms, ($bom_noms ? "\xEF\xBB\xBF" : '') . $nouveau_c_noms); echo "écrit : $noms\n"; }
echo "\nFait. Vider les caches (ddev drush cr) puis regarder une fiche Papier.\n";
