<?php
/**
 * create_qingtan_node.php
 * Crée la fiche Qingtan (Pteroceltis tatarinowii) dans Khartasia
 * avec le débat scientifique Chu/Qingtan intégré comme discussion.
 *
 * Usage (depuis racine Drupal) :
 *   ddev drush php:script scripts/create_qingtan_node.php
 *
 * Vérifie d'abord le NID du nœud Chu pour référence croisée.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

echo "\n══════════════════════════════════════════════════════\n";
echo " Création fiche Qingtan (Pteroceltis tatarinowii)\n";
echo "══════════════════════════════════════════════════════\n\n";

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// ── 1. Vérifier que la fiche Chu existe ───────────────────────────────────
$chu_nids = $node_storage->getQuery()
  ->condition('type', 'article')
  ->condition('title', 'Chu', '=')
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();

// Chercher aussi par nom scientifique si titre différent
if (empty($chu_nids)) {
  $chu_nids = $node_storage->getQuery()
    ->condition('type', 'article')
    ->condition('title', 'Broussonetia', 'CONTAINS')
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();
}

$chu_nid = !empty($chu_nids) ? reset($chu_nids) : NULL;
if ($chu_nid) {
  echo "[OK] Fiche Chu trouvée : nid=$chu_nid\n";
} else {
  echo "[!!] Fiche Chu introuvable — la référence croisée sera vide.\n";
}

// ── 2. Vérifier que Qingtan n'existe pas déjà ────────────────────────────
$existing = $node_storage->getQuery()
  ->condition('type', 'article')
  ->condition('title', 'Qingtan')
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();

if (!empty($existing)) {
  echo "[--] Fiche Qingtan déjà présente : nid=" . reset($existing) . " — arrêt.\n";
  return;
}

// ── 3. Inventaire des champs disponibles sur 'article' ───────────────────
$field_defs = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'article');
$available_fields = array_keys($field_defs);

function has_field(array $available, string $field): bool {
  return in_array($field, $available);
}

echo "\nChamps article disponibles (excerpt) :\n";
$show = array_filter($available_fields, fn($f) => !str_starts_with($f, 'revision_') && !in_array($f, ['nid','vid','uuid','langcode','status','created','changed','promote','sticky','default_langcode','revision_translation_affected']));
foreach ($show as $f) {
  echo "  - $f\n";
}

// ── 4. Construire le corps de texte FR ───────────────────────────────────
$body_fr = <<<HTML
<p><em>Pteroceltis tatarinowii</em> Maxim. — le <strong>Qingtan</strong> (青檀) ou « wingceltis » — est l'arbre dont l'écorce entre dans la composition du célèbre papier <strong>Xuan</strong> (宣纸), support canonique de la calligraphie et de la peinture chinoises lettrées depuis la période Tang.</p>

<h3>Taxonomie et morphologie</h3>
<p>Famille des Ulmacées (ormes). Arbre à feuilles caduques, 10–20 m de hauteur, à écorce grise feuilletée. Fibres d'écorce : 3–8 mm (longues, mais inférieures au <em>Broussonetia papyrifera</em>). Le Qingtan pousse exclusivement dans les massifs calcaires d'altitude : monts Huangshan, Jingxian et Xuancheng (Anhui).</p>

<h3>Le papier Xuan</h3>
<p>Le Xuan (宣纸) est fabriqué à Jingxian (泾县), préfecture de Xuancheng (宣城), Anhui. Sa fabrication en 18 étapes prend 2 à 3 ans. Caractéristiques : fin, très absorbant (l'encre diffuse, permettant les effets xieyi 写意), translucide, souple. C'est le support des lettrés pour la calligraphie, la peinture à l'encre et les estampages.</p>
<p>Indication Géographique Protégée (GI) depuis 2002. Inscription UNESCO au patrimoine immatériel en 2009.</p>

<h3>Géographie actuelle</h3>
<p>Culture restreinte aux monts Jingxian/Xuancheng. Interdiction de coupe sauvage depuis 2002. Le Qingtan n'est pas cultivé ailleurs en Chine de manière industrielle.</p>

<h3>Débat scientifique : Qingtan vs Chu dans les papiers peints cantonais</h3>
<p><strong>Question centrale (Note Wallpaper Canton, 28 sept. 2026)</strong> : le Xuan (Qingtan) était-il le support des papiers peints d'exportation cantonais (XVIIe–XIXe s.) ?</p>
<p><strong>Conclusion établie</strong> : très probablement non, pour quatre raisons convergentes :</p>
<ol>
  <li><strong>Coût</strong> : le Xuan, ressource protégée à processus long, était trop onéreux pour des décors muraux produits en série pour l'export.</li>
  <li><strong>Format</strong> : les papiers peints (80 cm × 1,75 m) exigent de grands formats ; le Xuan est standardisé en petits formats.</li>
  <li><strong>Absorption</strong> : le Xuan est très absorbant — inadapté aux pigments lourds (azurite, malachite) qui restent en surface sur le <em>Broussonetia</em> (Chu).</li>
  <li><strong>Preuves analytiques</strong> : les analyses fibres du V&A Conservation Department (2019) sur les inv. E.2851-1913, E.2852-1913, E.2853-1913 montrent des fibres longues (type Chu/bambou), sans aucune fibre de Qingtan.</li>
</ol>
<p><em>Note de méthode</em> : la présence de fibres de Qingtan dans un papier peint cantonais constituerait une anomalie forte (commande impériale exceptionnelle), utilisable comme marqueur N3 négatif dans la méthode Yangban.</p>
<p>Voir aussi : <strong>Chu (楮, <em>Broussonetia papyrifera</em>)</strong> — principal candidat au rôle de support des papiers peints d'exportation des périodes I–III (1720–1795).</p>

<h3>Sources</h3>
<ul>
  <li>Tsien Tsuen-Hsuin, <em>Paper and Printing</em> (Science and Civilisation in China, vol. 5), Cambridge UP, 1985, pp. 55–62.</li>
  <li>Clunas, Craig, <em>Art in China</em>, Oxford UP, 1997, p. 112.</li>
  <li>Administration GI Xuan Zhi, Anhui, 2002.</li>
  <li>V&A Conservation Department, rapport interne, 2019 (analyses fibres E.2851-1913 et al.).</li>
  <li>Note scientifique Wallpaper Canton — Méthode Yangban, 28 sept. 2026.</li>
</ul>
HTML;

$body_en = <<<HTML
<p><em>Pteroceltis tatarinowii</em> Maxim. — <strong>Qingtan</strong> (青檀), also known as wingceltis — is the tree whose bark forms the basis of the famous <strong>Xuan paper</strong> (宣纸), the canonical support for Chinese literati calligraphy and ink painting since the Tang dynasty.</p>

<h3>Taxonomy and morphology</h3>
<p>Family Ulmaceae. Deciduous tree, 10–20 m tall, with flaking grey bark. Bark fibres: 3–8 mm (long, but shorter than <em>Broussonetia papyrifera</em>). Qingtan grows exclusively in limestone uplands: Huangshan, Jingxian and Xuancheng (Anhui).</p>

<h3>Xuan paper</h3>
<p>Xuan (宣纸) is produced in Jingxian (泾县), Xuancheng prefecture, Anhui, through an 18-step process lasting 2–3 years. It is thin, highly absorbent, translucent, and prized for its ink-spreading qualities (xieyi effects). Protected Geographical Indication since 2002; UNESCO Intangible Cultural Heritage 2009.</p>

<h3>Scientific debate: Qingtan vs Chu in Canton export wallpapers</h3>
<p><strong>Key question (Wallpaper Canton scientific note, 28 Sept. 2026)</strong>: Was Xuan (Qingtan) the support of Chinese export wallpapers (17th–19th c.)? <strong>Evidence points to no</strong>: the Xuan was too expensive, too absorbent, and too small in format for large-scale mural wallpapers. V&A fibre analysis (2019) on export wallpapers shows long fibres (Chu/bamboo type) with no Qingtan fibres detected.</p>
<p>Presence of Qingtan fibres in an export wallpaper would constitute a strong N3 anomaly marker in the Yangban dating method (Wallpaper Canton project).</p>

<h3>Sources</h3>
<ul>
  <li>Tsien Tsuen-Hsuin, <em>Paper and Printing</em> (SCC vol. 5), Cambridge UP, 1985.</li>
  <li>Wallpaper Canton, scientific note on paper support as N3 marker, 28 Sept. 2026.</li>
</ul>
HTML;

$body_zh = <<<HTML
<p><strong>青檀</strong>（<em>Pteroceltis tatarinowii</em> Maxim.），榆科，又称翼朴，是制造<strong>宣纸</strong>的主要原料。宣纸产于安徽泾县，为中国传统书画的代表性用纸。</p>

<h3>植物特征</h3>
<p>落叶乔木，高10–20米，树皮灰色片状剥落。韧皮纤维长3–8毫米，较构皮（楮）略短，但品质细腻。青檀主要分布于安徽黄山、泾县、宣城等石灰岩山区，其他省份无工业化种植。</p>

<h3>宣纸</h3>
<p>宣纸制作工序共18道，历时2至3年，成纸薄透、润墨性强，适合泼墨写意。2002年获国家地理标志保护，2009年入选联合国教科文组织非物质文化遗产名录。</p>

<h3>学术争议：广州出口壁纸的纸张来源</h3>
<p>《Wallpaper Canton研究笔记》（2026年9月28日）指出：广州外销壁纸（17–19世纪）的纸张支撑体很可能<strong>并非</strong>宣纸，原因如下：宣纸价格昂贵、规格较小、吸墨性强（不适合重质矿物颜料），且英国V&A博物馆的纤维分析（2019年）在壁纸样品中<strong>未发现青檀纤维</strong>，而是以楮皮（构皮）和竹纤维为主。若在广州出口壁纸中检测到青檀纤维，将构成一个重要的N3异常标志，表明该件作品可能属于帝制特供例外。</p>
HTML;

// ── 5. Créer le nœud ─────────────────────────────────────────────────────
$values = [
  'type'     => 'article',
  'title'    => 'Qingtan',
  'status'   => 1,
  'langcode' => 'fr',
];

// Corps de texte
if (has_field($available_fields, 'body')) {
  $values['body'] = [
    'value'   => $body_fr,
    'format'  => 'full_html',
    'summary' => 'Pteroceltis tatarinowii — arbre source du papier Xuan (宣纸). Distinct du Chu (Broussonetia papyrifera). Absent des papiers peints cantonais d\'exportation selon les analyses fibres V&A 2019.',
  ];
}

// Nom scientifique si champ existant
foreach (['field_nom_scientifique', 'field_scientific_name', 'field_nom_accepte_powo'] as $fn) {
  if (has_field($available_fields, $fn)) {
    $values[$fn] = 'Pteroceltis tatarinowii Maxim.';
    echo "[INFO] Champ $fn renseigné\n";
    break;
  }
}

// Famille botanique
foreach (['field_family', 'field_famille', 'field_famille_botanique'] as $fn) {
  if (has_field($available_fields, $fn)) {
    $values[$fn] = 'Ulmaceae';
    echo "[INFO] Champ $fn renseigné\n";
    break;
  }
}

// Note interne
if (has_field($available_fields, 'field_att_note_interne')) {
  $values['field_att_note_interne'] = 'Créé le ' . date('Y-m-d') . ' suite à la note scientifique Wallpaper Canton du 28/09/2026 sur le support papier des papiers peints cantonais. À compléter : taxonomie POWO, attestations géographiques, lien ct_tradition si disponible.';
}

// Cultigène
if (has_field($available_fields, 'field_taxon_cultigene')) {
  $values['field_taxon_cultigene'] = 0; // espèce sauvage
}

// Référence croisée Chu
if ($chu_nid && has_field($available_fields, 'field_related_species')) {
  $values['field_related_species'] = [['target_id' => $chu_nid]];
  echo "[INFO] Référence croisée Chu (nid=$chu_nid) ajoutée\n";
}

$node = $node_storage->create($values);
$node->save();
$nid = $node->id();
echo "\n[OK] Nœud Qingtan créé : nid=$nid\n";

// ── 6. Ajouter les traductions EN et ZH ──────────────────────────────────
$langs = [
  'en' => ['title' => 'Qingtan', 'body' => $body_en, 'summary' => 'Pteroceltis tatarinowii — source tree of Xuan paper (宣纸). Distinct from Chu (Broussonetia papyrifera). Absent from Canton export wallpapers per V&A fibre analysis 2019.'],
  'zh-hans' => ['title' => '青檀', 'body' => $body_zh, 'summary' => '青檀（Pteroceltis tatarinowii）— 宣纸原料树种，与楮皮纸（构皮）有别。据V&A纤维分析，广州外销壁纸中未发现青檀纤维。'],
];

foreach ($langs as $langcode => $data) {
  if ($node->hasTranslation($langcode)) {
    echo "[--] Traduction $langcode déjà présente — ignorée.\n";
    continue;
  }
  $translation = $node->addTranslation($langcode, [
    'title'  => $data['title'],
    'status' => 1,
  ]);
  if (has_field($available_fields, 'body')) {
    $translation->set('body', [
      'value'   => $data['body'],
      'format'  => 'full_html',
      'summary' => $data['summary'],
    ]);
  }
  $translation->save();
  echo "[OK] Traduction $langcode ajoutée.\n";
}

// ── 7. Noms communs (common_names) ────────────────────────────────────────
$common_names = [
  ['title' => 'Qingtan',              'lang' => 'fr',      'note' => 'Translittération pinyin'],
  ['title' => 'Wingceltis',           'lang' => 'en',      'note' => 'Nom anglais courant'],
  ['title' => 'Chinese wingceltis',   'lang' => 'en',      'note' => 'Nom anglais alternatif'],
  ['title' => '青檀',                  'lang' => 'zh-hans', 'note' => 'Nom chinois simplifié'],
  ['title' => '青檀',                  'lang' => 'zh-hant', 'note' => 'Nom chinois traditionnel'],
  ['title' => 'Qīngtán',              'lang' => 'fr',      'note' => 'Pinyin avec tons'],
];

$cn_storage = \Drupal::entityTypeManager()->getStorage('node');
$lang_terms = $term_storage->loadByProperties(['vid' => 'language_taxonomy']);
$lang_map = [];
foreach ($lang_terms as $t) {
  $lang_map[strtolower($t->label())] = $t->id();
}

$cn_created = 0;
foreach ($common_names as $cn) {
  // Chercher le TID de langue
  $lang_tid = NULL;
  foreach ($lang_map as $label => $tid) {
    if (stripos($label, $cn['lang']) !== FALSE || stripos($cn['lang'], $label) !== FALSE) {
      $lang_tid = $tid;
      break;
    }
  }

  $cn_values = [
    'type'     => 'common_names',
    'title'    => $cn['title'],
    'status'   => 1,
    'langcode' => 'fr',
  ];

  // Lier à la plante
  if (has_field(
    array_keys(\Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'common_names')),
    'field_genus_taxo'
  )) {
    // Chercher le TID taxonomique de Pteroceltis tatarinowii si existant
    $taxo_terms = $term_storage->loadByProperties([
      'name' => 'Pteroceltis tatarinowii',
    ]);
    if (!empty($taxo_terms)) {
      $taxo_tid = reset($taxo_terms)->id();
      $cn_values['field_genus_taxo'] = [['target_id' => $taxo_tid]];
    }
  }

  if ($lang_tid && has_field(
    array_keys(\Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'common_names')),
    'field_language_taxo_vern'
  )) {
    $cn_values['field_language_taxo_vern'] = [['target_id' => $lang_tid]];
  }

  $cn_node = $cn_storage->create($cn_values);
  $cn_node->save();
  $cn_created++;
  echo "  [OK] Nom commun créé : «{$cn['title']}» ({$cn['lang']}) — nid={$cn_node->id()}\n";
}

echo "\n[OK] $cn_created noms communs créés.\n";

// ── 8. Résumé ──────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════════════════════════\n";
echo " RÉSUMÉ\n";
echo "══════════════════════════════════════════════════════\n";
echo " Nœud Qingtan : nid=$nid\n";
echo " URL locale   : /fr/node/$nid\n";
echo " URL prod     : https://khartasia.org/fr/node/$nid\n";
echo " Traductions  : FR (défaut) + EN + ZH-Hans\n";
echo " Noms communs : $cn_created créés\n";
echo " Référence Chu: nid=" . ($chu_nid ?: 'non trouvé') . "\n";
echo "\n À COMPLÉTER MANUELLEMENT :\n";
echo "  1. Taxonomie POWO (field_nom_accepte_powo si disponible)\n";
echo "  2. Attestations géographiques (Anhui/Jingxian)\n";
echo "  3. Lien vers ct_tradition si bundle existant\n";
echo "  4. Images de l'arbre et des fibres\n";
echo "  5. Traductions JA et KO si nécessaires\n";
echo "  6. Vérifier et compléter les noms communs JA/KO\n";
echo "══════════════════════════════════════════════════════\n\n";
