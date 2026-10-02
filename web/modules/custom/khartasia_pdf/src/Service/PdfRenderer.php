<?php

namespace Drupal\khartasia_pdf\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Produit le PDF d'un dossier : trois gabarits Twig (corps, en-tête, pied de page) mis en page par mPDF.
 *
 * Contraintes de mise en page imposées par mPDF : pas de grille CSS ni de flexbox ; tables et blocs simples seulement.
 *
 * DÉFINITIONS
 * mPDF : bibliothèque PHP qui convertit du HTML/CSS en PDF.
 * substitution de polices : quand un caractère manque à la police courante (chinois, tibétain…), mPDF en prend une autre,
 *   parmi celles qu'il embarque.
 */
class PdfRenderer {

  public function __construct(
    protected RendererInterface $renderer,
    protected FileSystemInterface $fileSystem,
  ) {}

  /** Rend un gabarit Twig déclaré par hook_theme() et renvoie le HTML. */
  protected function twig(string $theme, array $variables): string {
    $build = ['#theme' => $theme];
    foreach ($variables as $nom => $valeur) {
      $build['#' . $nom] = $valeur;
    }
    return (string) $this->renderer->renderInIsolation($build);
  }

  public function pdf(array $d, array $L, bool $relecture, string $url, string $date): string {
    $v = ['d' => $d, 'L' => $L, 'relecture' => $relecture, 'url' => $url, 'date' => $date];
    $corps = $this->twig('khartasia_pdf_dossier', $v);
    $entete = $this->twig('khartasia_pdf_header', $v);
    $pied = $this->twig('khartasia_pdf_footer', $v);

    $temp = rtrim($this->fileSystem->getTempDirectory(), '/') . '/khartasia_pdf_mpdf';
    if (!is_dir($temp)) {
      @mkdir($temp, 0775, TRUE);
    }

    $mpdf = new \Mpdf\Mpdf([
      'tempDir' => $temp, 'mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'dejavusans',
      'margin_left' => 20, 'margin_right' => 20, 'margin_top' => 27, 'margin_bottom' => 24, 'margin_header' => 8, 'margin_footer' => 8,
      'autoScriptToLang' => TRUE, 'autoLangToFont' => TRUE,
    ]);
    $mpdf->useSubstitutions = TRUE;
    $mpdf->SetTitle(($relecture ? $L['doc_relecture'] : $L['doc_public']) . ' — ' . $d['plante']['titre']);
    $mpdf->SetAuthor('Khartasia');
    $mpdf->SetCreator('Khartasia');
    if ($relecture) {
      $mpdf->SetWatermarkText($L['wm'], 0.04);
      $mpdf->showWatermarkText = TRUE;
    }
    $mpdf->SetHTMLHeader($entete);
    $mpdf->SetHTMLFooter($pied);
    $mpdf->WriteHTML($corps);
    return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
  }

}
