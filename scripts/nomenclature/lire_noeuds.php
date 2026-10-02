<?php
/**
 * lire_noeuds.php — LECTURE SEULE : affiche tous les champs remplis de nœuds, puis les valeurs permises des listes rencontrées.
 * Usage (sur le serveur) : NIDS=1861,4686,4687,4688 vendor/bin/drush php-script lire_noeuds.php
 * Ne modifie rien ; pas de marqueur.
 *
 * DÉFINITIONS
 * champ de liste : champ qui n'accepte qu'un nombre fixe de valeurs (clé interne => libellé), par exemple un statut.
 * valeurs permises : ces clés et libellés, tels que la configuration les définit ; ce sont celles qu'un script doit utiliser.
 * champ de référence : champ qui pointe vers un autre contenu ou un terme ; on affiche alors le numéro et le libellé de la cible,
 *   et le type de cible attendu (bundles ou vocabulaires).
 */
$liste = getenv('NIDS') ?: '';
$ids = array_values(array_filter(array_map('intval', explode(',', $liste))));
if (!$ids || count($ids) > 40) { echo "NIDS invalide : indiquer de 1 à 40 numéros séparés par des virgules (ex. NIDS=1861,4686).\n"; return; }
$etm = \Drupal::entityTypeManager();
$noeuds = $etm->getStorage('node')->loadMultiple($ids);
$court = function ($t, $n = 220) { return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $t))), 0, $n); };
$ignores = ['nid', 'uuid', 'vid', 'langcode', 'type', 'revision_timestamp', 'revision_uid', 'revision_log', 'revision_default', 'revision_translation_affected',
  'status', 'uid', 'title', 'created', 'changed', 'promote', 'sticky', 'default_langcode', 'path', 'menu_link', 'metatag', 'content_translation_source', 'content_translation_outdated'];
echo "=== Lecture de " . count($ids) . " nœud(s) — lecture seule ===\n";
$listes_vues = []; $refs_vues = [];
foreach ($ids as $id) {
  if (!isset($noeuds[$id])) { echo "\n=== nœud $id : introuvable ===\n"; continue; }
  $n = $noeuds[$id];
  echo "\n=== nœud $id | type : " . $n->bundle() . " | publié : " . ($n->isPublished() ? 'oui' : 'non') . " | langues : " . implode(',', array_keys($n->getTranslationLanguages())) . " ===\n";
  echo "  titre : " . $court($n->label(), 120) . "\n";
  foreach ($n->getFields() as $nom => $champ) {
    if (in_array($nom, $ignores, TRUE) || $champ->isEmpty()) { continue; }
    $def = $champ->getFieldDefinition();
    $type = $def->getType();
    try {
      if (method_exists($champ, 'referencedEntities')) {
        $c = []; foreach ($champ->referencedEntities() as $e) { $c[] = $e->id() . ' ' . $court($e->label(), 60); }
        $v = implode(' ; ', $c); $refs_vues[$nom] = $def;
      }
      else { $v = $court($champ->getString()); }
    } catch (\Throwable $e) { $v = '(illisible)'; }
    if ($type === 'list_string' || $type === 'list_integer') { $listes_vues[$nom] = $def; }
    echo "  " . str_pad($nom, 28) . "[" . $type . "] " . $v . "\n";
  }
}
if ($listes_vues) {
  echo "\n--- valeurs permises des champs de liste rencontrés ---\n";
  foreach ($listes_vues as $nom => $def) {
    $vals = $def->getFieldStorageDefinition()->getSetting('allowed_values');
    $p = []; foreach ($vals as $k => $l) { $p[] = "$k => " . (is_array($l) ? ($l['label'] ?? '') : $l); }
    echo "  " . $nom . " : " . implode(' | ', $p) . "\n";
  }
}
if ($refs_vues) {
  echo "\n--- cibles attendues des champs de référence rencontrés ---\n";
  foreach ($refs_vues as $nom => $def) {
    $cible = $def->getFieldStorageDefinition()->getSetting('target_type');
    $reglages = $def->getSetting('handler_settings');
    $b = isset($reglages['target_bundles']) ? implode(', ', array_keys($reglages['target_bundles'])) : '(tous)';
    echo "  " . str_pad($nom, 28) . $cible . " : " . $b . ($def->isRequired() ? "  [OBLIGATOIRE]" : '') . "\n";
  }
}
