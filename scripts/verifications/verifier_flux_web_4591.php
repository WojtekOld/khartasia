<?php
/**
 * verifier_flux_web_4591.php — Diagnostic flux web vs CLI pour node/4591.
 *
 * NE NÉCESSITE PAS DRUPAL (pur PHP, lecture de fichiers + requête HTTP).
 * La requête est faite DEPUIS le conteneur web : elle emprunte exactement
 * le même chemin nginx → php-fpm que le navigateur, ce qui permet de
 * comparer des octets servis dans les mêmes conditions.
 *
 * Usage : copier à la racine du projet, puis
 *   ddev exec php verifier_flux_web_4591.php
 *
 * Ce que le script établit :
 *   A. Taille du journal de debug AVANT la requête (référence).
 *   B. Requête HTTP /fr/node/4591 depuis le conteneur → /tmp/web.html.
 *   C. Lignes de journal AJOUTÉES par cette requête : la fonction
 *      preprocess atteint-elle « FIN fonction » en contexte web ?
 *   D. web.html est-il complet au niveau octet (7 × kh-dispute, </html>
 *      final, Content-Length cohérent) — ou tronqué ?
 *   E. Si complet : extrait autour du premier lien de dispute, pour
 *      inspection du markup produit par les lignes Twig ajoutées.
 *
 * NB : la requête est ANONYME. Si le navigateur est en session
 * authentifiée et que les résultats divergent, refaire avec le cookie
 * de session (curl -b).
 */

$URL_PATH = '/fr/node/4591';
$LOG = '/tmp/khartasia_debug.log';
$WEB = '/tmp/web.html';
$CLI = '/tmp/test_render.html';
$HDR = '/tmp/web_headers.txt';

function titre($t) { echo "\n=== $t ===\n"; }
function compte($fichier, $motif, $ci = FALSE) {
  if (!file_exists($fichier)) return -1;
  $c = file_get_contents($fichier);
  return $ci ? substr_count(mb_strtolower($c), mb_strtolower($motif))
             : substr_count($c, $motif);
}

// ------------------------------------------------------------- A
titre('A. Journal de debug avant requête');
$pos = file_exists($LOG) ? filesize($LOG) : 0;
echo '  ' . $LOG . ' : ' . ($pos ? "$pos octets" : 'ABSENT ou vide — l\'instrumentation est-elle bien déployée ?') . "\n";

// ------------------------------------------------------------- B
titre('B. Requête HTTP depuis le conteneur');
@unlink($WEB); @unlink($HDR);
$essais = [
  ['https://localhost' . $URL_PATH, ['-H', 'Host: khartasia.ddev.site']],
  ['https://khartasia.ddev.site' . $URL_PATH, []],
  ['http://localhost' . $URL_PATH, ['-H', 'Host: khartasia.ddev.site']],
];
$http_code = '0';
foreach ($essais as [$url, $extra]) {
  $parts = ['curl', '-sk', '-D', $HDR, '-o', $WEB, '-w', '%{http_code}'];
  foreach ($extra as $e) $parts[] = $e;
  $parts[] = $url;
  $cmd = implode(' ', array_map('escapeshellarg', $parts));
  $http_code = trim((string) shell_exec($cmd . ' 2>/dev/null'));
  if (file_exists($WEB) && filesize($WEB) > 0) { echo "  via $url → HTTP $http_code\n"; break; }
}
if (!file_exists($WEB) || filesize($WEB) === 0) {
  echo "  ÉCHEC : aucune réponse exploitable. Vérifier que ddev tourne (ddev describe).\n";
  exit(1);
}
$taille_web = filesize($WEB);
echo "  corps : $taille_web octets → $WEB\n";

// En-têtes : Content-Length déclaré vs réel, Transfer-Encoding.
$hdrs = file_exists($HDR) ? (string) file_get_contents($HDR) : '';
if (preg_match('/^Content-Length:\s*(\d+)/mi', $hdrs, $m)) {
  $declare = (int) $m[1];
  echo '  Content-Length déclaré : ' . $declare . ($declare === $taille_web
    ? " — COHÉRENT\n"
    : " — INCOHÉRENT avec le corps reçu ($taille_web) : réponse tronquée côté serveur\n");
}
elseif (preg_match('/^Transfer-Encoding:\s*chunked/mi', $hdrs)) {
  echo "  Transfer-Encoding: chunked — la complétude se juge au marqueur de fin (voir D)\n";
}

// ------------------------------------------------------------- C
titre('C. Journal de debug — lignes ajoutées par CETTE requête web');
$nouvelles = '';
if ($pos && file_exists($LOG)) {
  $fp = fopen($LOG, 'r');
  fseek($fp, $pos);
  $nouvelles = (string) stream_get_contents($fp);
  fclose($fp);
}
if ($nouvelles === '') {
  echo "  AUCUNE ligne ajoutée — la requête n'a pas passé par le preprocess instrumenté ?!\n";
  echo "  (Page servie depuis le cache de rendu ? Vider les caches : ddev drush cr, puis relancer.)\n";
}
else {
  echo $nouvelles;
  if (strpos($nouvelles, 'FIN fonction') !== FALSE) {
    echo "  >>> « FIN fonction » PRÉSENT : le preprocess aboutit aussi en contexte web.\n";
    echo "      Le preprocess est exonéré — la mort est en aval (template ou transport).\n";
  }
  else {
    echo "  >>> « FIN fonction » ABSENT : le preprocess s'interrompt en contexte web.\n";
    echo "      La dernière ligne ci-dessus désigne l'attestation et l'étape fautives.\n";
  }
}

// ------------------------------------------------------------- D
titre('D. Complétude des octets servis');
$n_dispute_web = compte($WEB, 'kh-dispute');
$n_dispute_cli = compte($CLI, 'kh-dispute');
echo "  kh-dispute : web = $n_dispute_web | cli = " . ($n_dispute_cli < 0 ? '(fichier absent)' : $n_dispute_cli) . "\n";
echo '  « attestations » (casse ignorée) : web = ' . compte($WEB, 'attestations', TRUE)
   . ' | cli = ' . ($n_dispute_cli < 0 ? '(absent)' : compte($CLI, 'attestations', TRUE)) . "\n";
$corps = (string) file_get_contents($WEB);
$finit_par_html = (bool) preg_match('/<\/html>\s*$/', $corps);
echo '  se termine par </html> : ' . ($finit_par_html ? 'OUI — réponse COMPLÈte' : 'NON — réponse TRONQUÉE côté serveur') . "\n";
echo "  250 derniers octets :\n  …" . trim(mb_substr($corps, -250)) . "\n";
if ($n_dispute_cli > 0 && file_exists($CLI)) {
  echo '  tailles : web = ' . $taille_web . ' | cli = ' . filesize($CLI)
     . " (écarts attendus : jetons de formulaire, dates — seuls comptent les marqueurs ci-dessus)\n";
}

// ------------------------------------------------------------- E
titre('E. Inspection du markup (seulement si réponse complète)');
if ($finit_par_html && $n_dispute_web > 0) {
  $i = strpos($corps, 'kh-dispute');
  echo "  contexte autour du premier lien de dispute :\n\n  …"
     . str_replace(["\r\n", "\n", "\r"], ' ', mb_substr($corps, max(0, $i - 400), 800))
     . " …\n";
  // Signal grossier : équilibre des div/section dans tout le document.
  foreach (['div', 'section', 'span', 'a'] as $balise) {
    $ouv = preg_match_all('/<' . $balise . '(\s|>)/', $corps);
    $fer = preg_match_all('/<\/' . $balise . '>/', $corps);
    if ($ouv !== $fer) echo "  DÉSÉQUILIBRE <$balise> : $ouv ouvrantes vs $fer fermantes\n";
  }
  echo "\n  >>> Réponse complète + navigateur tronqué = le navigateur avale la suite\n";
  echo "      (markup invalide). Examiner l'extrait ci-dessus et les deux lignes\n";
  echo "      Twig ajoutées dans node--papers.html.twig.\n";
}
elseif (!$finit_par_html) {
  echo "  >>> Réponse tronquée côté serveur alors que le CLI aboutit :\n";
  echo "      comparer les SAPI — ddev exec php -i | grep -E 'memory_limit|zend.assertions'\n";
  echo "      contre  /etc/php/*/fpm/php.ini (memory_limit, zend.assertions,\n";
  echo "      max_execution_time, output_buffering).\n";
}

echo "\n=== Fin ===\n";
