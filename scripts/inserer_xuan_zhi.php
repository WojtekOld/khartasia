<?php
/**
 * inserer_xuan_zhi.php
 *
 * Trouve le nœud "Xuan" existant dans Khartasia et remplace son contenu
 * par la fiche complète et corrigée.
 * Si absent : crée le nœud.
 *
 * Usage :
 *   ddev drush php:script scripts/inserer_xuan_zhi.php           ← dry-run
 *   ddev drush php:script scripts/inserer_xuan_zhi.php -- --apply
 */

declare(strict_types=1);

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$mode  = $apply ? 'APPLY' : 'DRY-RUN';
$ns    = \Drupal::entityTypeManager()->getStorage('node');
$ts    = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

echo "\n══════════════════════════════════════════════════════════\n";
echo " Insertion/mise à jour fiche Xuan Zhi — $mode\n";
echo "══════════════════════════════════════════════════════════\n\n";

// ── 1. Inventaire champs article ─────────────────────────────────────────
$defs   = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'article');
$fields = array_keys($defs);
$hf     = fn(string $f): bool => in_array($f, $fields);

echo "Champs disponibles sur 'article' :\n";
$show = array_filter($fields, fn($f) => str_starts_with($f, 'field_'));
foreach ($show as $f) echo "  - $f\n";
echo "\n";

// ── 2. Chercher le nœud Xuan existant ───────────────────────────────────
$candidates = [];
foreach (['Xuan', 'Xuan zhi', 'Xuan Zhi', '宣纸', 'Xuan paper'] as $q) {
  $nids = $ns->getQuery()
    ->condition('type', 'article')
    ->condition('title', $q, 'CONTAINS')
    ->accessCheck(FALSE)
    ->execute();
  foreach ($nids as $nid) {
    $candidates[$nid] = $ns->load($nid)->label();
  }
}

echo "── Nœuds article trouvés contenant 'Xuan' ──────────────────\n";
if (empty($candidates)) {
  echo "  Aucun nœud existant → sera créé.\n";
  $target_nid = NULL;
} else {
  foreach ($candidates as $nid => $title) {
    echo "  nid=$nid : «$title»\n";
  }
  $target_nid = array_key_first($candidates);
  echo "  → Cible retenue : nid=$target_nid\n";
}

// ── 3. Données à écrire ──────────────────────────────────────────────────

$body_fr = <<<HTML
<p>Littéralement « papier de Xuan[zhou] ». Abréviation de <em>Xuanzhou Gongzhi</em>
(宣州贡纸, « papier-tribut du Xuanzhou »). Le caractère 贡 (<em>gòng</em>) désigne
un tribut ou une offrande à la cour impériale — et non une taxe au sens fiscal.</p>

<h3>Production</h3>
<p>Comté de Jing (泾县, Jīngxiàn), préfecture de Xuancheng (宣城), province de l'Anhui.
Production concentrée dans un périmètre restreint où coexistent le Qingtan sauvage,
la paille de riz du bassin du Yangtze et l'eau de source calcaire nécessaire au rouissage.</p>

<h3>Composition et grades</h3>
<ul>
  <li><strong>棉料</strong> (<em>mián liào</em>) : 60 % Qingtan + 40 % paille — grade courant</li>
  <li><strong>净皮</strong> (<em>jìng pí</em>, écorce pure) : 80 % Qingtan + 20 % paille — qualité standard</li>
  <li><strong>特净</strong> (<em>tè jìng</em>, extra-pur) : &gt;80 % Qingtan — qualité supérieure</li>
</ul>
<p>C'est la combinaison des fibres longues du Qingtan et des fibres courtes de la paille qui
confère au papier ses propriétés d'absorption contrôlée et l'effet incisif du pinceau.</p>
<p><strong>Note</strong> : <em>Oryza sativa</em> dans cette composition désigne la <strong>paille</strong>
(tiges après récolte, 稻草 <em>dàocǎo</em>), non le grain alimentaire.</p>

<h3>Processus de fabrication</h3>
<p>18 étapes sur 2 à 3 ans : rouissage de l'écorce (4–6 mois), blanchiment naturel au soleil
(12–18 mois), battage, mise en pâte, formation à la cuve, séchage sur mur chauffé. La durée
du processus explique le coût élevé du Xuan.</p>

<h3>Formats</h3>
<p>Standard 1,0 × 0,5 m à 1,8 × 0,95 m. Formats exceptionnels jusqu'à 3,6 × 2,4 m.
Simple épaisseur (单宣 <em>dān xuān</em>) ou collé (夹宣 <em>jiā xuān</em>).</p>

<h3>Statut patrimonial</h3>
<ul>
  <li>Indication Géographique Protégée (GI) depuis 2002</li>
  <li>Inscription UNESCO au Patrimoine Culturel Immatériel en 2009</li>
  <li>Interdiction de coupe sauvage du Qingtan depuis 2002</li>
</ul>

<h3>Usage historique et lien Wallpaper Canton</h3>
<p>Support canonique de la calligraphie (书法) et de la peinture lettrée (文人画) depuis
la période Tang (VIIIe s.). <strong>Non utilisé pour les papiers peints d'exportation cantonais</strong>,
qui étaient réalisés sur des papiers d'écorce de mûrier (<em>Broussonetia papyrifera</em>, Chu 楮)
ou de bambou — plus épais, opaques et économiques. Voir → <strong>Chu (楮)</strong>.</p>

<h3>Note botanique — erreurs fréquentes</h3>
<ul>
  <li>Le Qingtan (青檀) n'est <strong>pas</strong> un santal (Santalaceae) : c'est un orme
  (Ulmaceae), genre <em>Pteroceltis</em>. La confusion vient d'une traduction littérale de
  青 (vert) + 檀 (bois aromatique).</li>
  <li><em>Dalbergia hupeana</em> Hance (黄檀) et <em>Ulmus cavaleriei</em> H.Lév. :
  cités dans certaines sources comme composants du Xuan — <strong>non documentés dans les
  sources primaires</strong>. Confusion de nomenclature (suffixe 檀 commun).</li>
</ul>

<h3>Sources</h3>
<ul>
  <li>Tsien Tsuen-Hsuin, <em>Paper and Printing</em> (SCC vol. 5), Cambridge UP, 1985, pp. 55–62.</li>
  <li>Li Shaoyan, <em>Xuan Paper Research</em> (宣纸研究), Anhui Fine Arts Press, 2005.</li>
  <li>Cao Ying, entretien terrain, Jingxian, 2019 — <em>Heritage Science</em>, 2021, vol. 9, art. 45.</li>
  <li>Note Wallpaper Canton — support papier comme marqueur N3, 28/09/2026.</li>
</ul>
HTML;

$body_en = <<<HTML
<p>Literally "Xuan[zhou] paper". Abbreviation of <em>Xuanzhou Gongzhi</em>
(宣州贡纸, "tribute paper of Xuanzhou"). The character 贡 (<em>gòng</em>)
means imperial tribute or offering — not a tax in the fiscal sense.</p>

<h3>Production</h3>
<p>Jing County (泾县), Xuancheng Prefecture, Anhui Province. Production is
geographically restricted to the area where wild Qingtan trees, Yangtze
basin rice straw, and calcareous spring water coexist.</p>

<h3>Composition and grades</h3>
<ul>
  <li><strong>棉料</strong> (<em>mián liào</em>): 60% Qingtan + 40% straw — common grade</li>
  <li><strong>净皮</strong> (<em>jìng pí</em>, "pure bark"): 80% Qingtan + 20% straw — standard</li>
  <li><strong>特净</strong> (<em>tè jìng</em>, "extra pure"): &gt;80% Qingtan — premium</li>
</ul>
<p><em>Oryza sativa</em> in this context refers to rice <strong>straw</strong>
(stalks after harvest, 稻草), not the grain.</p>

<h3>Manufacturing process</h3>
<p>18 steps over 2–3 years: bark retting (4–6 months), natural sun bleaching
(12–18 months), beating, pulping, sheet formation, wall drying.</p>

<h3>Heritage status</h3>
<ul>
  <li>Protected Geographical Indication (GI) since 2002</li>
  <li>UNESCO Intangible Cultural Heritage since 2009</li>
</ul>

<h3>Wallpaper Canton note</h3>
<p><strong>Not used for Chinese export wallpapers</strong> (17th–19th c.),
which were made on <em>Broussonetia papyrifera</em> (Chu) or bamboo paper —
thicker, opaque, and economical. See → <strong>Chu (楮)</strong>.</p>

<h3>Botanical note</h3>
<p>Qingtan (青檀) is <strong>not</strong> a sandalwood (Santalaceae): it is
an elm-family tree (Ulmaceae), genus <em>Pteroceltis</em>.
<em>Dalbergia hupeana</em> and <em>Ulmus cavaleriei</em> listed in some
sources as Xuan components are <strong>undocumented in primary sources</strong>
— nomenclatural confusion via the shared suffix 檀.</p>
HTML;

$body_zh = <<<HTML
<p><strong>宣纸</strong>，全称「宣州贡纸」，产于安徽省泾县（宣城市辖），为中国传统书画用纸的代表。</p>

<h3>产地与原料</h3>
<p>产地限于泾县周边山区，因该地同时具备野生青檀、长江流域稻草和石灰岩山泉水三大条件。</p>
<p>原料组成：</p>
<ul>
  <li><strong>棉料</strong>：青檀皮60% + 稻草40%</li>
  <li><strong>净皮</strong>：青檀皮80% + 稻草20%</li>
  <li><strong>特净</strong>：青檀皮80%以上</li>
</ul>
<p>注：配方中的<em>Oryza sativa</em>指<strong>稻草</strong>（收割后的茎秆），非稻米。</p>

<h3>制作工艺</h3>
<p>共18道工序，历时2至3年，包括：树皮沤制（4–6个月）、日光漂白（12–18个月）、捣料、抄纸、晒纸等。</p>

<h3>保护状态</h3>
<ul>
  <li>2002年获国家地理标志保护</li>
  <li>2009年入选联合国教科文组织非物质文化遗产名录</li>
</ul>

<h3>与广州外销壁纸的关系</h3>
<p>宣纸<strong>并非</strong>广州外销壁纸的支撑体。外销壁纸使用构皮纸（楮皮，<em>Broussonetia papyrifera</em>）
或竹纸，更厚实、不透明且成本低廉。详见→<strong>楮 (Chu)</strong>。</p>

<h3>植物学说明</h3>
<p>青檀（<em>Pteroceltis tatarinowii</em>）属榆科，<strong>非</strong>檀香科（Santalaceae）。
黄檀（<em>Dalbergia hupeana</em>）与<em>Ulmus cavaleriei</em>被部分资料误列为宣纸原料，
<strong>缺乏原始文献依据</strong>，系命名混淆所致。</p>
HTML;

// ── 4. Préparer les valeurs ──────────────────────────────────────────────
$values = [
  'type'     => 'article',
  'title'    => 'Xuan Zhi (宣纸)',
  'status'   => 1,
  'langcode' => 'fr',
  'body'     => [
    'value'   => $body_fr,
    'format'  => 'full_html',
    'summary' => 'Papier Xuan — support canonique de la calligraphie et peinture lettrée chinoises. '
               . 'Comté de Jing, Anhui. Qingtan + paille de riz. Non utilisé pour les papiers '
               . 'peints cantonais d\'exportation.',
  ],
];

if ($hf('field_nom_accepte_powo')) {
  $values['field_nom_accepte_powo'] = 'Pteroceltis tatarinowii Maxim.';
}
if ($hf('field_taxon_cultigene')) {
  $values['field_taxon_cultigene'] = 0;
}
if ($hf('field_paper_name')) {
  $values['field_paper_name'] =
    "Xuan zhi (宣纸) — papier-tribut de Xuanzhou, Jingxian, Anhui. "
    . "Grades : 棉料 (60/40), 净皮 (80/20), 特净 (>80% Qingtan). "
    . "GI 2002, UNESCO 2009. Non utilisé pour les papiers peints d'exportation cantonais.";
}
if ($hf('field_synonymes_bot')) {
  $values['field_synonymes_bot'] = implode("\n", [
    'Celtis tatarinowii Maxim. [basionyme]',
    'Pteroceltis delavayi (Franch.) C.K.Schneid. [synonyme hétérotypique — statut débattu]',
    'Pteroceltis tatarinowii var. sinensis (Hemsl.) C.K.Schneid. [variété synonyme]',
  ]);
}
if ($hf('field_ident_fibre')) {
  $values['field_ident_fibre'] =
    "Fibres d'écorce interne (liber) du Qingtan : 3–8 mm, parois fines à moyennes. "
    . "Fibres de paille de riz : 0,5–2 mm (courtes). Coloration Herzberg : rose-violet "
    . "(cellulose). Absence de Qingtan confirmée dans les analyses fibres V&A 2019 "
    . "(inv. E.2851-1913) sur des papiers peints cantonais — marqueur N3 négatif "
    . "(méthode Yangban, Wallpaper Canton).";
}
if ($hf('field_mode_extraction')) {
  // Utiliser 'decoction' ou première valeur disponible
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_mode_extraction');
  $allowed = array_keys($storage ? $storage->getSetting('allowed_values') : []);
  // Chercher 'trempage' ou 'rouissage'
  $val = 'trempage_froid';
  foreach ($allowed as $v) {
    if (preg_match('/trempage|rouissage|macerat/i', $v)) { $val = $v; break; }
  }
  if (in_array($val, $allowed)) {
    $values['field_mode_extraction'] = $val;
  }
}
if ($hf('field_saison_recolte')) {
  $values['field_saison_recolte'] = 'Printemps (mars–avril) — avant la montée de sève';
}
if ($hf('field_statut_identification')) {
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_statut_identification');
  $allowed = array_keys($storage ? $storage->getSetting('allowed_values') : []);
  $val = 'identifie_powo';
  if (in_array($val, $allowed)) {
    $values['field_statut_identification'] = $val;
  }
}

// ── 5. Dry-run : afficher le plan ────────────────────────────────────────
if (!$apply) {
  echo "\n── Plan d'écriture (dry-run) ────────────────────────────────\n";
  $action = $target_nid ? "Mise à jour nid=$target_nid" : "Création nouveau nœud";
  echo "  Action : $action\n";
  foreach ($values as $k => $v) {
    $preview = is_array($v)
      ? mb_substr($v['value'] ?? json_encode($v), 0, 60)
      : mb_substr((string)$v, 0, 60);
    echo "  $k → «$preview»\n";
  }
  echo "\n── Traductions prévues ──────────────────────────────────────\n";
  echo "  EN : body + title 'Xuan Zhi (宣纸)'\n";
  echo "  ZH-Hans : body + title '宣纸 (Xuan Zhi)'\n";
  echo "\n── Noms communs à créer ─────────────────────────────────────\n";
  $noms = ['Xuan', 'Xuan Zhi', '宣纸', 'Papier Xuan', 'Xuan paper',
           'Xuanzhou Gongzhi', '宣州贡纸', 'Rice paper (impropre)'];
  foreach ($noms as $n) echo "  - «$n»\n";
  echo "\n══════════════════════════════════════════════════════════\n";
  echo " DRY-RUN terminé.\n";
  echo " Commande apply :\n";
  echo " ddev drush php:script scripts/inserer_xuan_zhi.php -- --apply\n";
  echo "══════════════════════════════════════════════════════════\n\n";
  return;
}

// ── 6. Apply ─────────────────────────────────────────────────────────────
if ($target_nid) {
  $node = $ns->load($target_nid);
  foreach ($values as $k => $v) {
    if ($k === 'type' || $k === 'langcode') continue;
    if ($node->hasField($k)) {
      $node->set($k, $v);
      echo "  [W] $k\n";
    }
  }
  $node->save();
  $nid = $target_nid;
  echo "\n[OK] Nœud mis à jour : nid=$nid\n";
} else {
  $node = $ns->create($values);
  $node->save();
  $nid = $node->id();
  echo "\n[OK] Nœud créé : nid=$nid\n";
}

// ── 7. Traductions ───────────────────────────────────────────────────────
$node = $ns->load($nid);
$translations = [
  'en' => ['title' => 'Xuan Zhi (宣纸)', 'body' => $body_en,
           'summary' => 'Xuan paper — canonical support for Chinese literati calligraphy and painting. '
                      . 'Jing County, Anhui. Qingtan bark + rice straw. Not used for Canton export wallpapers.'],
  'zh-hans' => ['title' => '宣纸 (Xuan Zhi)', 'body' => $body_zh,
                'summary' => '宣纸，产于安徽泾县，原料为青檀皮与稻草。传统书画用纸，非外销壁纸支撑体。'],
];

foreach ($translations as $lang => $data) {
  if ($node->hasTranslation($lang)) {
    $tr = $node->getTranslation($lang);
    $tr->set('title', $data['title']);
    if ($node->hasField('body')) {
      $tr->set('body', ['value' => $data['body'], 'format' => 'full_html',
                        'summary' => $data['summary']]);
    }
    $tr->save();
    echo "[OK] Traduction $lang mise à jour.\n";
  } else {
    $tr = $node->addTranslation($lang, ['title' => $data['title'], 'status' => 1]);
    if ($node->hasField('body')) {
      $tr->set('body', ['value' => $data['body'], 'format' => 'full_html',
                        'summary' => $data['summary']]);
    }
    $tr->save();
    echo "[OK] Traduction $lang créée.\n";
  }
}

// ── 8. Noms communs ──────────────────────────────────────────────────────
$noms_communs = [
  ['title' => 'Xuan',              'lang' => 'fr',      'note' => 'Abréviation courante'],
  ['title' => 'Xuan Zhi',          'lang' => 'fr',      'note' => 'Translittération pinyin'],
  ['title' => '宣纸',               'lang' => 'zh-hans', 'note' => 'Nom officiel simplifié'],
  ['title' => '宣紙',               'lang' => 'zh-hant', 'note' => 'Nom officiel traditionnel'],
  ['title' => 'Papier Xuan',       'lang' => 'fr',      'note' => 'Usage courant français'],
  ['title' => 'Xuan paper',        'lang' => 'en',      'note' => 'Common English name'],
  ['title' => 'Xuanzhou Gongzhi',  'lang' => 'fr',      'note' => 'Nom complet historique (translittération)'],
  ['title' => '宣州贡纸',            'lang' => 'zh-hans', 'note' => 'Nom complet historique'],
  ['title' => 'Rice paper (impropre)', 'lang' => 'en',  'note' => 'Terme erroné — à ne pas utiliser. '
    . 'Le « rice paper » désigne parfois le Xuan par erreur. '
    . 'Le vrai « rice paper » est le papier de pith (Tetrapanax papyrifer).'],
];

$cn_fields = array_keys(
  \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'common_names')
);

// TID taxonomique Pteroceltis tatarinowii
$taxo_terms = $ts->loadByProperties(['name' => 'Pteroceltis tatarinowii']);
$taxo_tid   = !empty($taxo_terms) ? reset($taxo_terms)->id() : NULL;

$cn_created = 0;
foreach ($noms_communs as $cn) {
  // Vérifier si déjà existant
  $existing = $ns->getQuery()
    ->condition('type', 'common_names')
    ->condition('title', $cn['title'])
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();
  if (!empty($existing)) {
    echo "  [--] Nom commun déjà existant : «{$cn['title']}»\n";
    continue;
  }

  $cn_vals = ['type' => 'common_names', 'title' => $cn['title'],
              'status' => 1, 'langcode' => 'fr'];

  if ($taxo_tid && in_array('field_genus_taxo', $cn_fields)) {
    $cn_vals['field_genus_taxo'] = [['target_id' => $taxo_tid]];
  }
  if (in_array('body', $cn_fields) && !empty($cn['note'])) {
    $cn_vals['body'] = ['value' => '<p>' . htmlspecialchars($cn['note']) . '</p>',
                        'format' => 'full_html'];
  }

  $cn_node = $ns->create($cn_vals);
  $cn_node->save();
  $cn_created++;
  echo "  [OK] Nom commun : «{$cn['title']}» — nid={$cn_node->id()}\n";
}

// ── 9. Résumé ─────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════════════════════════════\n";
echo " RÉSUMÉ\n";
echo "══════════════════════════════════════════════════════════\n";
echo " Nœud Xuan Zhi   : nid=$nid\n";
echo " URL locale      : /fr/node/$nid\n";
echo " URL prod        : https://khartasia.org/fr/node/$nid\n";
echo " Traductions     : FR + EN + ZH-Hans\n";
echo " Noms communs    : $cn_created créés\n";
echo "\n À COMPLÉTER MANUELLEMENT :\n";
echo "  1. field_tax_family → TID 'Ulmaceae'\n";
echo "  2. field_tax_genus  → TID 'Pteroceltis'\n";
echo "  3. field_zones_geo  → Anhui/Jingxian (TID à sélectionner)\n";
echo "  4. field_image      → photo de l'arbre Qingtan ou du papier\n";
echo "  5. Référence croisée → nid=22 (Chu) si field_related_species existe\n";
echo "══════════════════════════════════════════════════════════\n\n";
