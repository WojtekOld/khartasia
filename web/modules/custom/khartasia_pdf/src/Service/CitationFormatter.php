<?php

namespace Drupal\khartasia_pdf\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Met en forme une notice bibliographique (bibcite_reference) pour le PDF.
 *
 * PROVISOIRE : mise en forme « maison » à partir des champs de la notice, en attendant de brancher le formateur de citations de
 * bibcite (bibcite.citation_styler). Isolée dans cette classe pour pouvoir la remplacer sans toucher au reste.
 *
 * DÉFINITIONS
 * notice : une référence bibliographique ; contributeur : un auteur, entité distincte liée à la notice.
 * forme courte : « Nom (année) » ; forme complète : la référence en entier, pour la section Références.
 */
class CitationFormatter {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Forme courte, en texte brut : « Nom (année) », « A & B (année) » ou « A et al. (année) ».
   */
  public function court($ref): string {
    $auteurs = $this->auteurs($ref);
    $annee = $this->champ($ref, 'bibcite_year');
    if (!$auteurs) {
      $t = trim((string) $ref->label());
      return $annee !== '' ? $t . ' (' . $annee . ')' : $t;
    }
    $noms = array_column($auteurs, 'nom');
    $txt = count($noms) === 1 ? $noms[0] : (count($noms) === 2 ? $noms[0] . ' & ' . $noms[1] : $noms[0] . ' et al.');
    return $annee !== '' ? $txt . ' (' . $annee . ')' : $txt;
  }

  /**
   * Forme complète, en HTML sûr (chaque valeur est échappée ; seules les balises <i> sont ajoutées).
   */
  public function complete($ref): string {
    $h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $parts = [];
    foreach ($this->auteurs($ref) as $a) {
      $parts[] = $a['nom'] . ($a['initiales'] !== '' ? ', ' . $a['initiales'] : '');
    }
    $auteurs = !$parts ? '' : (count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' & ' . end($parts) : $parts[0]);
    $annee = $this->champ($ref, 'bibcite_year');
    $titre = trim((string) $ref->label());
    $contenant = $this->champ($ref, 'bibcite_secondary_title');
    $editeur = $this->champ($ref, 'bibcite_publisher');
    $pages = $this->pages($ref);

    $s = '';
    if ($auteurs !== '') {
      $s .= $h($auteurs) . ($annee !== '' ? ' (' . $h($annee) . ')' : '') . '. ';
    }
    elseif ($annee !== '') {
      $s .= '(' . $h($annee) . '). ';
    }
    $s .= $contenant !== ''
      ? '“' . $h($this->guillemets($titre)) . '”, <i>' . $h($contenant) . '</i>'
      : '<i>' . $h($titre) . '</i>';
    if ($editeur !== '') {
      $s .= ', ' . $h($editeur);
    }
    if ($pages !== '') {
      $s .= ', ' . $h($pages);
    }
    return rtrim($s, ' .') . '.';
  }

  /**
   * @return array<int, array{nom: string, initiales: string}>
   */
  protected function auteurs($ref): array {
    $out = [];
    if (!$ref->hasField('author')) {
      return $out;
    }
    foreach ($ref->get('author') as $item) {
      $c = $this->contributeur($item);
      if (!$c) {
        continue;
      }
      $nom = $this->champ($c, 'last_name');
      $prenom = $this->champ($c, 'first_name');
      if ($nom === '') {
        $mots = preg_split('/\s+/u', trim((string) $c->label()), -1, PREG_SPLIT_NO_EMPTY);
        $nom = (string) array_pop($mots);
        $prenom = $prenom !== '' ? $prenom : implode(' ', $mots);
      }
      $out[] = ['nom' => $nom, 'initiales' => $this->initiales($prenom)];
    }
    return $out;
  }

  protected function contributeur($item) {
    try {
      $c = $item->entity;
    }
    catch (\Throwable $e) {
      $c = NULL;
    }
    if (!$c && !empty($item->target_id)) {
      $c = $this->entityTypeManager->getStorage('bibcite_contributor')->load($item->target_id);
    }
    return $c;
  }

  /** « Agnieszka » => « A. » ; « Jean-Paul » => « J.-P. » ; « Anne Marie » => « A. M. ». */
  protected function initiales(string $prenom): string {
    $prenom = trim($prenom);
    if ($prenom === '') {
      return '';
    }
    $out = '';
    foreach (preg_split('/([\s-]+)/u', $prenom, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $m) {
      if (preg_match('/^\s+$/u', $m)) {
        $out .= ' ';
      }
      elseif (strpos($m, '-') !== FALSE) {
        $out .= '-';
      }
      else {
        $out .= mb_strtoupper(mb_substr($m, 0, 1)) . '.';
      }
    }
    return trim($out);
  }

  /** Les pages se lisent dans « bibcite_pages » ou, à défaut, dans « bibcite_section » (où elles sont parfois saisies). */
  protected function pages($ref): string {
    foreach (['bibcite_pages', 'bibcite_section'] as $nom) {
      $v = $this->champ($ref, $nom);
      if ($v !== '') {
        $v = preg_replace('/(\d)\s*-\s*(\d)/u', '$1–$2', $v);
        if (preg_match('/^\d+(–\d+)?$/u', $v)) {
          return (strpos($v, '–') !== FALSE ? 'pp. ' : 'p. ') . $v;
        }
        return $v;
      }
    }
    return '';
  }

  /** Les guillemets droits internes d'un titre deviennent des guillemets simples typographiques. */
  protected function guillemets(string $titre): string {
    return preg_replace('/"([^"]*)"/u', '‘$1’', $titre);
  }

  protected function champ($e, string $nom): string {
    return $e->hasField($nom) && !$e->get($nom)->isEmpty() ? trim((string) $e->get($nom)->value) : '';
  }

}
