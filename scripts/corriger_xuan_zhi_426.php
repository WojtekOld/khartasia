<?php
/**
 * corriger_xuan_zhi_426.php
 *
 * Corrige la fiche Xuan zhi (nid=426, bundle=papers) dans Khartasia :
 *   1. Retire Ulmus cavaleriei (nid=473, tid=451) des plantes liées
 *   2. Retire Dalbergia hupeana (nid=2305, tid=566) des plantes liées
 *   3. Ajoute Pteroceltis tatarinowii (nid=4689) si absent
 *   4. Corrige le body FR (santal vert → Qingtan, papier-taxe → papier-tribut)
 *   5. Met à jour les traductions EN et ZH-Hans
 *
 * Usage :
 *   ddev drush php:script scripts/corriger_xuan_zhi_426.php           ← dry-run
 *   ddev drush php:script scripts/corriger_xuan_zhi_426.php -- --apply
 */

declare(strict_types=1);

$apply = in_array('--apply', $_SERVER['argv'] ?? [], TRUE);
$mode  = $apply ? 'APPLY' : 'DRY-RUN';
$ns    = \Drupal::entityTypeManager()->getStorage('node');
$ts    = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

echo "\n══════════════════════════════════════════════════════════\n";
echo " Correction Xuan zhi (nid=426) — $mode\n";
echo "══════════════════════════════════════════════════════════\n\n";

$node = $ns->load(426);
if (!$node) {
  echo "[ERREUR] nid=426 introuvable.\n";
  return;
}

// ── 1. Plantes liées à retirer ───────────────────────────────────────────
$retirer_nids = [473, 2305]; // Ulmus cavaleriei, Dalbergia hupeana
$retirer_tids = [451, 566];  // mêmes en taxo

echo "── field_genus_pap_entity ───────────────────────────────────\n";
$current_refs = [];
foreach ($node->get('field_genus_pap_entity') as $ref) {
  $current_refs[] = (int) $ref->target_id;
}
$new_refs = array_filter($current_refs, fn($nid) => !in_array($nid, $retirer_nids));

// Ajouter Pteroceltis tatarinowii (nid=4689) si absent
$qingtan_nid = 4689;
if (!in_array($qingtan_nid, $new_refs)) {
  $qingtan = $ns->load($qingtan_nid);
  if ($qingtan) {
    $new_refs[] = $qingtan_nid;
    echo "  [+] Ajout Pteroceltis tatarinowii (nid=$qingtan_nid)\n";
  } else {
    echo "  [!!] nid=$qingtan_nid introuvable — créer d'abord via create_qingtan_node.php\n";
  }
}

foreach ($current_refs as $nid) {
  $label = $ns->load($nid)?->label() ?? '?';
  $action = in_array($nid, $retirer_nids) ? '[-] RETIRE' : '[=] GARDE';
  echo "  $action nid=$nid : $label\n";
}
echo "  [+] Ajout si absent : nid=$qingtan_nid (Pteroceltis tatarinowii)\n";

// ── 2. Taxons à retirer ──────────────────────────────────────────────────
echo "\n── field_genus_taxo_pap ─────────────────────────────────────\n";
$current_tids = [];
foreach ($node->get('field_genus_taxo_pap') as $ref) {
  $current_tids[] = (int) $ref->target_id;
}
$new_tids = array_filter($current_tids, fn($tid) => !in_array($tid, $retirer_tids));

// Chercher TID de Pteroceltis tatarinowii
$pt_terms = $ts->loadByProperties(['name' => 'Pteroceltis tatarinowii']);
$pt_tid   = !empty($pt_terms) ? (int) reset($pt_terms)->id() : NULL;
if ($pt_tid && !in_array($pt_tid, $new_tids)) {
  $new_tids[] = $pt_tid;
  echo "  [+] Ajout TID=$pt_tid (Pteroceltis tatarinowii)\n";
} elseif (!$pt_tid) {
  echo "  [!!] Terme taxonomique Pteroceltis tatarinowii introuvable\n";
}

foreach ($current_tids as $tid) {
  $label = $ts->load($tid)?->label() ?? '?';
  $action = in_array($tid, $retirer_tids) ? '[-] RETIRE' : '[=] GARDE';
  echo "  $action tid=$tid : $label\n";
}

// ── 3. Nouveau body FR ───────────────────────────────────────────────────
$new_body_fr = <<<HTML
<p>Littéralement « papier de Xuan[zhou] ». Abréviation de <em>Xuanzhou Gongzhi</em>
(宣州贡纸, « papier-tribut du Xuanzhou »). Le caractère 贡 (<em>gòng</em>) désigne
un tribut ou une offrande à la cour impériale — et non une taxe au sens fiscal.</p>

<p>Produit dans le comté de Jing (泾县), préfecture de Xuancheng, province de l'Anhui.
Fabriqué à partir de fibres d'écorce de <strong>qingtan</strong> (青檀,
<em>Pteroceltis tatarinowii</em> Maxim., Ulmaceae — wingceltis de Chine, voir fiche)
et de paille de riz (<em>Oryza sativa</em> L., 稻草 <em>dàocǎo</em> — les tiges
après récolte, non le grain). C'est le mélange de ces deux fibres qui confère au
papier ses qualités d'absorption contrôlée et l'effet incisif des traits de pinceau.</p>

<p><strong>Grades</strong> : 棉料 (<em>mián liào</em>) 60 % qingtan / 40 % paille —
grade courant ; 净皮 (<em>jìng pí</em>) 80 % qingtan — qualité standard calligraphie ;
特净 (<em>tè jìng</em>) &gt;80 % qingtan — qualité supérieure.</p>

<p><strong>Formats</strong> : Standard 1,0 × 0,5 m à 1,8 × 0,95 m ; formats
exceptionnels jusqu'à 3,6 × 2,4 m pour peintures monumentales. Simple épaisseur
(单宣 <em>dān xuān</em>) ou collé à deux/trois épaisseurs (夹宣 <em>jiā xuān</em>).
Processus de fabrication : 18 étapes sur 2 à 3 ans.</p>

<p><strong>Statut</strong> : Indication Géographique Protégée (GI) depuis 2002.
Inscription UNESCO au Patrimoine Culturel Immatériel en 2009.</p>

<p><strong>Usage</strong> : Support canonique de la calligraphie (书法) et de la
peinture lettrée (文人画) depuis la période Tang (VIIIe s.).
<strong>Non utilisé pour les papiers peints d'exportation cantonais</strong>
(XVIIe–XIXe s.), réalisés sur papier d'écorce de mûrier (<em>Broussonetia
papyrifera</em>, Chu 楮) ou de bambou — plus épais et économiques.</p>

<p><strong>Note botanique</strong> : Le qingtan (青檀) n'est pas un santal
(Santalaceae) ; c'est un orme (Ulmaceae), genre <em>Pteroceltis</em>.
<em>Dalbergia hupeana</em> Hance (黄檀) et <em>Ulmus cavaleriei</em> H.Lév.
sont parfois cités comme composants du Xuan par confusion nomenclaturale
(suffixe 檀 commun) — <strong>non documentés dans les sources primaires</strong>
sur la fabrication du Xuan (Tsien Tsuen-Hsuin, 1985 ; Li Shaoyan, 2005).</p>
HTML;

echo "\n── Body FR — aperçu du nouveau contenu ─────────────────────\n";
echo "  " . mb_substr(strip_tags($new_body_fr), 0, 120) . "...\n";

// ── 4. Traductions ───────────────────────────────────────────────────────
$new_body_en = <<<HTML
<p>Literally "Xuan[zhou] paper". Abbreviation of <em>Xuanzhou Gongzhi</em>
(宣州贡纸, "tribute paper of Xuanzhou"). The character 贡 (<em>gòng</em>)
means imperial tribute or offering — not a fiscal tax.</p>

<p>Produced in Jing County (泾县), Xuancheng Prefecture, Anhui Province.
Made from bark fibres of <strong>qingtan</strong> (青檀,
<em>Pteroceltis tatarinowii</em> Maxim., Ulmaceae — wingceltis) and rice straw
(<em>Oryza sativa</em> L. — stalks after harvest, not the grain).</p>

<p><strong>Grades</strong>: 棉料 (<em>mián liào</em>) 60% qingtan / 40% straw —
common; 净皮 (<em>jìng pí</em>) 80% qingtan — standard; 特净 (<em>tè jìng</em>)
&gt;80% qingtan — premium. 18-step process over 2–3 years.</p>

<p><strong>Heritage</strong>: Protected GI since 2002; UNESCO ICH since 2009.</p>

<p><strong>Note</strong>: <strong>Not used for Chinese export wallpapers</strong>
(17th–19th c.) — too fine, absorbent and costly for large mural decorations.
<em>Dalbergia hupeana</em> and <em>Ulmus cavaleriei</em> listed in some sources
as Xuan components are <strong>undocumented in primary sources</strong> —
nomenclatural confusion via the shared suffix 檀.</p>
HTML;

$new_body_zh = <<<HTML
<p><strong>宣纸</strong>，全称「宣州贡纸」，「贡」指进贡朝廷之意，非税收之意。
产于安徽省泾县（宣城市辖）。</p>

<p>原料：<strong>青檀</strong>皮（<em>Pteroceltis tatarinowii</em> Maxim.，榆科）
与稻草（<em>Oryza sativa</em> L. 茎秆，非稻米）。</p>

<p><strong>品种</strong>：棉料（青檀60% + 稻草40%）；净皮（青檀80%）；
特净（青檀80%以上）。共18道工序，历时2至3年。</p>

<p><strong>保护</strong>：2002年获国家地理标志保护；2009年入选联合国教科文组织
非物质文化遗产名录。</p>

<p><strong>说明</strong>：宣纸<strong>非</strong>广州外销壁纸的支撑体。
黄檀（<em>Dalbergia hupeana</em>）与<em>Ulmus cavaleriei</em>被部分资料
误列为宣纸原料，<strong>缺乏原始文献依据</strong>，系命名混淆所致。</p>
HTML;

// ── 5. Dry-run ou Apply ──────────────────────────────────────────────────
if (!$apply) {
  echo "\n══════════════════════════════════════════════════════════\n";
  echo " DRY-RUN terminé — aucune écriture.\n";
  echo " Corrections prévues :\n";
  echo "   - Retire nid=473 (Ulmus cavaleriei) de field_genus_pap_entity\n";
  echo "   - Retire nid=2305 (Dalbergia hupeana) de field_genus_pap_entity\n";
  echo "   - Retire tid=451, tid=566 de field_genus_taxo_pap\n";
  echo "   - Ajoute nid=4689 (Pteroceltis tatarinowii) si existant\n";
  echo "   - Corrige body FR (santal vert→Qingtan, papier-taxe→papier-tribut)\n";
  echo "   - Ajoute/met à jour traductions EN et ZH-Hans\n";
  echo "\n Commande apply :\n";
  echo " ddev drush php:script scripts/corriger_xuan_zhi_426.php -- --apply\n";
  echo "══════════════════════════════════════════════════════════\n\n";
  return;
}

// ── APPLY ────────────────────────────────────────────────────────────────

// Plantes liées
$node->set('field_genus_pap_entity',
  array_map(fn($nid) => ['target_id' => $nid], array_values($new_refs)));
echo "[W] field_genus_pap_entity → " . implode(', ', array_values($new_refs)) . "\n";

// Taxons
$node->set('field_genus_taxo_pap',
  array_map(fn($tid) => ['target_id' => $tid], array_values($new_tids)));
echo "[W] field_genus_taxo_pap → " . implode(', ', array_values($new_tids)) . "\n";

// Body FR
$node->set('body', [
  'value'   => $new_body_fr,
  'format'  => 'full_html',
  'summary' => 'Papier Xuan — support canonique de la calligraphie chinoise. '
             . 'Qingtan (Pteroceltis tatarinowii) + paille de riz. '
             . 'Non utilisé pour les papiers peints cantonais d\'exportation.',
]);
echo "[W] body (FR) mis à jour\n";
$node->save();
echo "[OK] nid=426 sauvegardé\n";

// Traductions
$node = $ns->load(426); // recharger
foreach (['en' => $new_body_en, 'zh-hans' => $new_body_zh] as $lang => $body) {
  $titles = ['en' => 'Xuan Zhi (宣纸)', 'zh-hans' => '宣纸 (Xuan Zhi)'];
  if ($node->hasTranslation($lang)) {
    $tr = $node->getTranslation($lang);
  } else {
    $tr = $node->addTranslation($lang, ['title' => $titles[$lang], 'status' => 1]);
  }
  $tr->set('title', $titles[$lang]);
  $tr->set('body', ['value' => $body, 'format' => 'full_html']);
  $tr->save();
  echo "[OK] Traduction $lang sauvegardée\n";
}

echo "\n══════════════════════════════════════════════════════════\n";
echo " APPLY terminé.\n";
echo " Vérifier : /fr/node/426 (local) ou khartasia.org/fr/node/426\n";
echo " Shengxuan (nid=483) et Shuxuan (nid=484) → à lier manuellement\n";
echo " comme variantes de traitement du Xuan zhi (nid=426).\n";
echo "══════════════════════════════════════════════════════════\n\n";
