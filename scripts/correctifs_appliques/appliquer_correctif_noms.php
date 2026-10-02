<?php
/**
 * appliquer_correctif_noms.php — correctif de l'affichage des fiches « Noms vernaculaires ».
 *
 * Usage, depuis la racine du projet :
 *   ddev exec php appliquer_correctif_noms.php              (simulation : n'écrit rien)
 *   ddev exec php appliquer_correctif_noms.php --appliquer  (écrit, après sauvegarde)
 *
 * Ce qu'il fait :
 *   1. remplace, dans le thème khartasia_ui, la fonction khartasia_ui_preprocess_node_common_names()
 *      (avec son commentaire) et ajoute la fonction de classement des attestations ;
 *   2. remplace le gabarit templates/node/node--common-names.html.twig.
 * Effets : un nom peut viser plusieurs plantes ou aucune ; seules les attestations PUBLIÉES
 * sont affichées (avant, une attestation non publiée aurait été visible) ; les attestations sont
 * rangées par type ; la page de la source (locateur) est affichée si le champ existe ; des
 * étiquettes de cache évitent qu'une attestation publiée apparaisse tardivement.
 *
 * DÉFINITIONS
 * simulation : exécution à blanc. Le script lit les fichiers, vérifie qu'il sait où agir et que le
 *   résultat est valide, affiche ce qu'il ferait, et n'écrit rien.
 * garde : test préalable qui fait REFUSER l'écriture quand un fichier n'a pas la forme attendue
 *   (fonction absente ou en double, gabarit inconnu). Un refus ne modifie aucun fichier.
 * idempotent : relancer le script une seconde fois ne change rien (il constate que c'est déjà fait).
 * analyseur PHP (tokenizer) : l'outil de PHP qui découpe le code en éléments. Il permet de
 *   trouver la fin d'une fonction sans se tromper sur les accolades situées dans des chaînes.
 * BOM : marque d'octets placée au tout début de certains fichiers UTF-8 ; elle est conservée telle quelle.
 * fin de ligne (EOL) : LF (\n) ou CRLF (\r\n) ; celle du fichier d'origine est conservée.
 * étiquette de cache : mot-clé qui dit à Drupal « cette page dépend de tel contenu » ; quand ce
 *   contenu change, la page est recalculée.
 */

$appliquer = in_array('--appliquer', $argv ?? [], TRUE);
$theme = 'web/themes/custom/khartasia_ui/khartasia_ui.theme';
$gabarit = 'web/themes/custom/khartasia_ui/templates/node/node--common-names.html.twig';
$dossier_sauvegardes = '_sauvegardes_locales';
$nom_fonction = 'khartasia_ui_preprocess_node_common_names';

$nouvelle_fonction = <<<'KH_FN_FIN'
/**
 * Préprocess des fiches de noms vernaculaires : quelle plante, quelle famille,
 * quels papiers, et ce que disent les sources.
 *
 * DÉFINITIONS
 * plante liée : fiche Plante (type « article ») vers laquelle pointe le nom. Un nom en
 *   vise 0 à n (champ illimité) : la polysémie, un nom pour deux plantes, en a deux.
 * portée : à quoi se rapporte un nom qui n'a PAS de plante liée (champ field_nom_portee,
 *   s'il existe) : taxon supérieur, fonction, ou sans référent papetier (faux nom).
 * attestation publiée : seule une attestation publiée est affichée. Une attestation en
 *   cours de relecture ne doit jamais apparaître sur une page publique.
 * désignation : attestation de type « Désignation » : une source affirme qu'un nom
 *   désigne tel référent (plante ou papier), à tel endroit, à telle page (locateur).
 * écriture : attestation de type « Écriture vernaculaire » : la forme graphique d'un nom.
 * locateur : localisation précise dans la source (page, planche, entrée), si le champ existe.
 */
function khartasia_ui_classer_attestations_nom(array $entrees) {
  $classees = ['ecritures' => [], 'designations' => [], 'autres' => []];
  foreach ($entrees as $e) {
    $type = $e['type_attestation'] ?? NULL;
    if ($type === 'Désignation') {
      $classees['designations'][] = $e;
    }
    elseif ($type === NULL || $type === '' || $type === 'Écriture vernaculaire') {
      // Sans type : conservé avec les écritures, comme avant ce correctif.
      $classees['ecritures'][] = $e;
    }
    else {
      $classees['autres'][] = $e;
    }
  }
  return $classees;
}

function khartasia_ui_preprocess_node_common_names(&$variables, $node) {
  $db = \Drupal::database();
  $cache_tags = ['node_list:attestation'];

  $langue_terme = $node->hasField('field_language_taxo_vern') && !$node->get('field_language_taxo_vern')->isEmpty()
    ? $node->get('field_language_taxo_vern')->entity : NULL;
  $variables['langue_nom'] = $langue_terme ? $langue_terme->label() : NULL;

  // === Plantes liées (0 à n, publiées seulement) ===
  $plantes = [];
  if ($node->hasField('field_genus_com_entity') && !$node->get('field_genus_com_entity')->isEmpty()) {
    foreach ($node->get('field_genus_com_entity')->referencedEntities() as $plante) {
      if (!$plante->isPublished()) {
        continue;
      }
      $cache_tags = array_merge($cache_tags, $plante->getCacheTags());
      $plantes[] = [
        'nid' => $plante->id(),
        'titre' => $plante->label(),
        'url' => $plante->toUrl()->toString(),
        'famille' => $plante->hasField('field_tax_family') && !$plante->get('field_tax_family')->isEmpty()
          ? $plante->get('field_tax_family')->entity->label() : NULL,
        'ordre' => $plante->hasField('field_tax_order') && !$plante->get('field_tax_order')->isEmpty()
          ? $plante->get('field_tax_order')->entity->label() : NULL,
      ];
    }
  }
  $variables['plantes_liees'] = $plantes;
  $variables['plante_liee'] = $plantes[0] ?? NULL;

  // === Portée du nom, s'il n'a pas de plante liée ===
  $variables['portee'] = NULL;
  if (!$plantes && $node->hasField('field_nom_portee') && !$node->get('field_nom_portee')->isEmpty()) {
    $definitions = [
      'taxon_superieur' => ['libelle' => 'Taxon supérieur', 'definition' => 'désigne un genre ou une famille de plantes, sans fiche Plante correspondante.'],
      'fonction' => ['libelle' => 'Appellatif générique', 'definition' => 'est un appellatif générique : il désigne une fonction ou une catégorie (par exemple « plante à papier »), pas un taxon précis.'],
      'sans_referent_papetier' => ['libelle' => 'Sans référent papetier', 'definition' => 'est rapproché de plantes à papier par certaines sources, mais aucune preuve d\'usage papetier n\'est établie.'],
    ];
    $cle = (string) $node->get('field_nom_portee')->value;
    $variables['portee'] = $definitions[$cle] ?? NULL;
  }

  // === Papiers fabriqués depuis ces plantes (publiés seulement) ===
  $tous_papiers = [];
  if ($plantes) {
    $paper_nids = $db->query("
      SELECT DISTINCT f.entity_id
      FROM node__field_genus_pap_entity f
      INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1
      WHERE f.field_genus_pap_entity_target_id IN (:nids[])
      AND f.bundle = 'papers'
      AND f.deleted = 0
    ", [':nids[]' => array_column($plantes, 'nid')])->fetchCol();
    foreach (\Drupal\node\Entity\Node::loadMultiple($paper_nids) as $p) {
      $tous_papiers[] = ['titre' => $p->label(), 'url' => $p->toUrl()->toString()];
    }
  }

  // === PAGINATION PAPIERS ===
  $papiers_per_page = 10;
  $total_papiers_meme_plante = count($tous_papiers);

  $pager_cn = \Drupal::service('pager.manager')->createPager($total_papiers_meme_plante, $papiers_per_page, 0);
  $current_page_cn = $pager_cn->getCurrentPage();
  $offset_cn = $current_page_cn * $papiers_per_page;

  $variables['papiers_meme_plante'] = array_slice($tous_papiers, $offset_cn, $papiers_per_page);
  $variables['papiers_meme_plante_total'] = $total_papiers_meme_plante;
  $variables['papiers_meme_plante_pager'] = [
    '#type' => 'pager',
    '#element' => 0,
    '#quantity' => 5,
    '#tags' => ['« Premier', '‹ Précédent', 'Suivant ›', 'Dernier »'],
  ];

  // === Attestations de ce nom (publiées seulement) ===
  $att_ids = $db->query("
    SELECT DISTINCT f.entity_id
    FROM node__field_att_nom_vernaculaire f
    INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1
    WHERE f.field_att_nom_vernaculaire_target_id = :nid
    AND f.bundle = 'attestation'
    AND f.deleted = 0
    ORDER BY f.entity_id
  ", [':nid' => $node->id()])->fetchCol();

  $entrees = [];
  foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
    $cache_tags = array_merge($cache_tags, $att->getCacheTags());

    $type_terme = $att->hasField('field_att_type') && !$att->get('field_att_type')->isEmpty()
      ? $att->get('field_att_type')->entity : NULL;
    $type_ecriture_terme = $att->hasField('field_att_type_ecriture') && !$att->get('field_att_type_ecriture')->isEmpty()
      ? $att->get('field_att_type_ecriture')->entity : NULL;
    $source = $att->hasField('field_att_source') && !$att->get('field_att_source')->isEmpty()
      ? $att->get('field_att_source')->entity : NULL;
    $lieu = $att->hasField('field_att_lieu') && !$att->get('field_att_lieu')->isEmpty()
      ? $att->get('field_att_lieu')->entity : NULL;
    $locateur = $att->hasField('field_att_locateur') && !$att->get('field_att_locateur')->isEmpty()
      ? (string) $att->get('field_att_locateur')->value : NULL;

    // Référents de l'attestation : plantes et papiers publiés.
    $referents = [];
    foreach (['field_att_plante', 'field_att_papier'] as $champ_referent) {
      if ($att->hasField($champ_referent) && !$att->get($champ_referent)->isEmpty()) {
        foreach ($att->get($champ_referent)->referencedEntities() as $r) {
          if ($r->isPublished()) {
            $referents[] = ['titre' => $r->label(), 'url' => $r->toUrl()->toString()];
          }
        }
      }
    }

    $entrees[] = [
      'nid' => $att->id(),
      'type_attestation' => $type_terme ? $type_terme->label() : NULL,
      'type' => $type_ecriture_terme ? $type_ecriture_terme->label() : ($type_terme ? $type_terme->label() : NULL),
      'valeur' => $att->get('field_att_valeur')->value ?? '',
      'statut' => $att->get('field_att_statut')->value ?? '',
      'source' => $source ? $source->label() : NULL,
      'locateur' => $locateur,
      'lieu' => $lieu ? $lieu->label() : NULL,
      'referents' => $referents,
    ];
  }

  $classees = khartasia_ui_classer_attestations_nom($entrees);
  $variables['ecritures_vernaculaires'] = $classees['ecritures'];
  $variables['designations'] = $classees['designations'];
  $variables['autres_attestations'] = $classees['autres'];

  $variables['#cache']['tags'] = array_merge($variables['#cache']['tags'] ?? [], array_unique($cache_tags));
}
KH_FN_FIN;

$nouveau_gabarit = <<<'KH_TWIG_FIN'
{#
/**
 * @file
 * Template pour les fiches Noms vernaculaires.
 * Répond à la question réelle d'un utilisateur qui tape "kozo" :
 * quelle plante, quelle famille botanique, quels papiers l'utilisent,
 * et ce que disent les sources — pas une liste de champs bruts sans contexte.
 *
 * Un nom peut viser plusieurs plantes (polysémie) ou aucune : dans ce cas sa
 * « portée » dit à quoi il se rapporte (fonction, taxon supérieur, sans
 * référent papetier). Seules les attestations publiées sont affichées.
 */
#}

{{ attach_library('khartasia_ui/kh-plante') }}

{% set kh_statuts = {'probable': 'probable', 'hypothetique': 'hypothétique', 'refute': 'réfuté', 'inconnu': 'à vérifier'} %}

<article class="kh-plante">

  <header class="kh-plante__header">
    <h1 class="kh-plante__title">{{ node.label }}</h1>
    {% if node.field_ver_local_scripture.value %}
      <div class="kh-plante__synonymes">{{ node.field_ver_local_scripture.value }}</div>
    {% endif %}
  </header>

  <div class="kh-plante__body">

    <div class="kh-plante__col-left">

      {% if plantes_liees %}
        <section class="kh-plante__section">
          <h2 class="kh-plante__section-title">Qu'est-ce que c'est ?</h2>
          <div class="kh-prose">
            <p>
              <strong>{{ node.label }}</strong>
              {% if langue_nom %}
                est le nom employé en {{ langue_nom }}
              {% else %}
                est un nom vernaculaire
              {% endif %}
              pour désigner
              {% for p in plantes_liees %}
                <a href="{{ p.url }}"><em>{{ p.titre }}</em></a>{% if p.famille %}, de la famille des <strong>{{ p.famille }}</strong>{% if p.ordre %} (ordre des {{ p.ordre }}){% endif %}{% endif %}{% if not loop.last %} ;{% endif %}
              {%- endfor %}.
            </p>
            {% if node.field_vern_syn.value %}
              <p>Autres formes attestées : {{ node.field_vern_syn.value|raw }}</p>
            {% endif %}
          </div>
        </section>

        {% if papiers_meme_plante_total %}
          <section class="kh-plante__section">
            <h2 class="kh-plante__section-title">Papiers fabriqués à partir de {% if plantes_liees|length > 1 %}ces plantes{% else %}cette plante{% endif %} ({{ papiers_meme_plante_total }})</h2>
            <ul class="kh-papers-group__list">
              {% for p in papiers_meme_plante %}
                <li class="kh-papers-group__item">
                  <a href="{{ p.url }}">{{ p.titre }}</a>
                </li>
              {% endfor %}
            </ul>
            {{ papiers_meme_plante_pager }}
          </section>
        {% endif %}
      {% else %}
        <section class="kh-plante__section">
          <h2 class="kh-plante__section-title">Qu'est-ce que c'est ?</h2>
          <div class="kh-prose">
            {% if portee %}
              <p>
                <strong>{{ node.label }}</strong>
                {% if langue_nom %}(nom employé en {{ langue_nom }}){% endif %}
                {{ portee.definition }}
              </p>
            {% else %}
              <p>Aucune plante associée à ce nom pour l'instant.</p>
            {% endif %}
            {% if node.field_vern_syn.value %}
              <p>Autres formes attestées : {{ node.field_vern_syn.value|raw }}</p>
            {% endif %}
          </div>
        </section>
      {% endif %}

      {% if designations %}
        <section class="kh-plante__section">
          <h2 class="kh-plante__section-title">Ce que disent les sources</h2>
          <ul class="kh-papers-group__list">
            {% for d in designations %}
              <li class="kh-papers-group__item">
                {{ d.valeur }}
                {% if d.referents %}<span class="kh-nom-scripture">→ {% for r in d.referents %}<a href="{{ r.url }}"><em>{{ r.titre }}</em></a>{% if not loop.last %}, {% endif %}{% endfor %}</span>{% endif %}
                {% if d.lieu %}<span class="kh-nom-scripture">— {{ d.lieu }}</span>{% endif %}
                {% if d.source %}<span class="kh-fibre__notes">[source : {{ d.source }}{% if d.locateur %}, {{ d.locateur }}{% endif %}]</span>{% endif %}
                {% if d.statut and d.statut != 'atteste' %}<em class="kh-fibre__notes">[{{ kh_statuts[d.statut]|default(d.statut) }}]</em>{% endif %}
              </li>
            {% endfor %}
          </ul>
        </section>
      {% endif %}

      {% if ecritures_vernaculaires %}
        <section class="kh-plante__section">
          <h2 class="kh-plante__section-title">Écriture</h2>
          <ul class="kh-papers-group__list">
            {% for e in ecritures_vernaculaires %}
              <li class="kh-papers-group__item">
                {% if e.type %}<strong>{{ e.type }}</strong> — {% endif %}
                {{ e.valeur }}
                {% if e.source %}<span class="kh-fibre__notes">[source : {{ e.source }}{% if e.locateur %}, {{ e.locateur }}{% endif %}]</span>{% endif %}
                {% if e.statut and e.statut != 'atteste' %}<em class="kh-fibre__notes">[{{ kh_statuts[e.statut]|default(e.statut) }}]</em>{% endif %}
              </li>
            {% endfor %}
          </ul>
        </section>
      {% endif %}

      {% if autres_attestations %}
        <section class="kh-plante__section">
          <h2 class="kh-plante__section-title">Autres attestations</h2>
          <ul class="kh-papers-group__list">
            {% for a in autres_attestations %}
              <li class="kh-papers-group__item">
                {% if a.type %}<strong>{{ a.type }}</strong> — {% endif %}
                {{ a.valeur }}
                {% if a.source %}<span class="kh-fibre__notes">[source : {{ a.source }}{% if a.locateur %}, {{ a.locateur }}{% endif %}]</span>{% endif %}
                {% if a.statut and a.statut != 'atteste' %}<em class="kh-fibre__notes">[{{ kh_statuts[a.statut]|default(a.statut) }}]</em>{% endif %}
              </li>
            {% endfor %}
          </ul>
        </section>
      {% endif %}

    </div>

    <div class="kh-plante__col-right">
      {% for p in plantes_liees %}
        <div class="kh-meta-card">
          <h3 class="kh-meta-card__title">Fiche complète</h3>
          <p class="kh-prose"><a href="{{ p.url }}">Voir {{ p.titre }} →</a></p>
        </div>
      {% endfor %}
    </div>

  </div>
</article>
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

/**
 * Cherche la fonction $nom dans $code avec l'analyseur PHP.
 * Retourne [début, fin) en octets : du commentaire qui la précède (s'il la touche) à son accolade fermante.
 * Retourne NULL si absente ; plusieurs exemplaires : le nombre est dans le 3e élément.
 */
function trouver_fonction($code, $nom) {
  $tokens = token_get_all($code);
  $offsets = []; $pos = 0;
  foreach ($tokens as $i => $t) { $offsets[$i] = $pos; $pos += strlen(is_array($t) ? $t[1] : $t); }
  $trouvees = [];
  $n = count($tokens);
  for ($i = 0; $i < $n; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
    if ($j < $n && $tokens[$j] === '&') { $j++; while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; } }
    if (!($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === $nom)) { continue; }
    // début : le commentaire de documentation qui touche la fonction
    $debut_idx = $i; $k = $i - 1;
    while ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) { $k--; }
    if ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_DOC_COMMENT) { $debut_idx = $k; }
    // fin : l'accolade qui ferme le corps
    $profondeur = 0; $corps = FALSE; $fin = NULL;
    for ($m = $j; $m < $n; $m++) {
      $t = $tokens[$m];
      $ouvre = $t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE));
      if ($ouvre) { $profondeur++; $corps = TRUE; }
      elseif ($t === '}') { $profondeur--; if ($corps && $profondeur === 0) { $fin = $offsets[$m] + 1; break; } }
    }
    if ($fin === NULL) { return [NULL, NULL, 0]; }
    $trouvees[] = [$offsets[$debut_idx], $fin];
  }
  if (count($trouvees) === 0) { return NULL; }
  return [$trouvees[0][0], $trouvees[0][1], count($trouvees)];
}

echo "=== Correctif des fiches de noms vernaculaires (thème) — " . ($appliquer ? 'APPLICATION' : 'SIMULATION') . " ===\n";

// --- .theme ---
list($c_theme, $bom_theme, $eol_theme) = lire($theme);
$deja_theme = strpos($c_theme, 'khartasia_ui_classer_attestations_nom') !== FALSE;
if (!$deja_theme) {
  $trouve = trouver_fonction($c_theme, $nom_fonction);
  if ($trouve === NULL) { arreter("fonction $nom_fonction introuvable dans $theme."); }
  list($debut, $fin, $nombre) = $trouve;
  if ($nombre !== 1 || $debut === NULL) { arreter("la fonction $nom_fonction apparaît $nombre fois (une seule attendue) dans $theme."); }
  $ancienne = substr($c_theme, $debut, $fin - $debut);
  if (strpos($ancienne, 'field_genus_com_entity') === FALSE || strpos($ancienne, 'node__field_att_nom_vernaculaire') === FALSE) { arreter("la fonction trouvée ne ressemble pas à celle attendue (elle devrait lire field_genus_com_entity et field_att_nom_vernaculaire)."); }
  $nouveau_c_theme = substr($c_theme, 0, $debut) . vers_eol($nouvelle_fonction, $eol_theme) . substr($c_theme, $fin);
  echo "\n.theme : fonction à remplacer trouvée (" . (substr_count($ancienne, "\n") + 1) . " lignes) -> " . (substr_count($nouvelle_fonction, "\n") + 1) . " lignes (dont la fonction de classement).\n";
  $tmp = tempnam(sys_get_temp_dir(), 'kh_theme_') . '.php';
  file_put_contents($tmp, $nouveau_c_theme);
  exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
  @unlink($tmp);
  if ($code !== 0) { arreter("le .theme obtenu n'a pas une syntaxe PHP valide : " . implode(' ', $sortie)); }
  echo "  syntaxe PHP du résultat : OK\n";
} else { echo "\n.theme : déjà corrigé (la fonction de classement existe) ; rien à faire.\n"; }

// --- gabarit ---
list($c_gabarit, $bom_gabarit, $eol_gabarit) = lire($gabarit);
$deja_gabarit = strpos($c_gabarit, 'plantes_liees') !== FALSE;
if (!$deja_gabarit) {
  if (strpos($c_gabarit, 'Template pour les fiches Noms vernaculaires') === FALSE || strpos($c_gabarit, '{% if plante_liee %}') === FALSE) { arreter("le gabarit trouvé ne ressemble pas à celui attendu."); }
  $nouveau_c_gabarit = vers_eol($nouveau_gabarit, $eol_gabarit) . $eol_gabarit;
  echo "\ngabarit : remplacement complet (" . (substr_count($c_gabarit, "\n") + 1) . " lignes -> " . (substr_count($nouveau_gabarit, "\n") + 1) . " lignes).\n";
} else { echo "\ngabarit : déjà corrigé ; rien à faire.\n"; }

if ($deja_theme && $deja_gabarit) { echo "\nLes deux fichiers sont déjà corrigés.\n"; exit(0); }
if (!$appliquer) { echo "\nSimulation : rien n'a été écrit. Relancez avec --appliquer pour appliquer.\n"; exit(0); }

// --- écriture, après sauvegarde ---
if (!is_dir($dossier_sauvegardes)) { mkdir($dossier_sauvegardes, 0775, TRUE); }
$horodatage = date('Ymd_His');
foreach ([[$theme, $deja_theme], [$gabarit, $deja_gabarit]] as list($chemin, $deja)) {
  if ($deja) { continue; }
  $copie = $dossier_sauvegardes . '/' . basename($chemin) . '.avant_correctif_noms_' . $horodatage;
  if (!copy($chemin, $copie)) { arreter("sauvegarde impossible : $copie"); }
  echo "sauvegarde : $copie\n";
}
if (!$deja_theme) { file_put_contents($theme, ($bom_theme ? "\xEF\xBB\xBF" : '') . $nouveau_c_theme); echo "écrit : $theme\n"; }
if (!$deja_gabarit) { file_put_contents($gabarit, ($bom_gabarit ? "\xEF\xBB\xBF" : '') . $nouveau_c_gabarit); echo "écrit : $gabarit\n"; }
echo "\nFait. Vider les caches (ddev drush cr) puis regarder une fiche de nom vernaculaire.\n";
