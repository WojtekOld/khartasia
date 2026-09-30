<?php

namespace Drupal\khartasia_pdf_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

/**
 * Sous-classe TCPDF pour personnaliser le pied de page (mention de
 * copyright) — l'API classique de TCPDF exige d'étendre la classe et de
 * surcharger Footer(), pas de passer un callback.
 */
class KhartasiaProtectedPdf extends TCPDF {
  public function Footer() {
    $this->SetY(-15);
    $this->SetFont('wqyzenhei', 'I', 7);
    $this->Cell(0, 10, '© Copyright Khartasia / C. Laroque — ' . date('d/m/Y'), 0, 0, 'C');
  }
}

/**
 * Génère un export PDF protégé (copie/impression désactivées au niveau
 * du fichier lui-même, pas seulement visuellement) pour une fiche Papier.
 */
class PapierPdfController extends ControllerBase {

  public function export(NodeInterface $node) {
    if ($node->bundle() !== 'papers') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $pdf = new KhartasiaProtectedPdf('P', 'mm', 'A4', TRUE, 'UTF-8', FALSE);

    $pdf->SetCreator('Khartasia');
    $pdf->SetAuthor('Khartasia / C. Laroque');
    $pdf->SetTitle($node->label());

    $mot_de_passe_proprietaire = bin2hex(random_bytes(16));
    $pdf->SetProtection(
      ['copy', 'print', 'modify', 'annot-forms', 'extract'],
      '',
      $mot_de_passe_proprietaire,
      0,
      NULL
    );

    $pdf->setPrintHeader(FALSE);
    $pdf->setPrintFooter(TRUE);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->AddPage();

    $pdf->SetFont('wqyzenhei', 'B', 16);
    $pdf->MultiCell(0, 10, $node->label(), 0, 'L');
    $pdf->Ln(4);

    $pdf->SetFont('wqyzenhei', '', 10);
    $corps = $node->hasField('body') ? strip_tags($node->get('body')->value ?? '') : '';
    if ($corps) {
      $pdf->MultiCell(0, 6, $corps, 0, 'J');
      $pdf->Ln(6);
    }

    $att_ids = \Drupal::entityQuery('node')
      ->condition('type', 'attestation')
      ->condition('field_att_papier', $node->id())
      ->accessCheck(FALSE)->execute();

    if ($att_ids) {
      $pdf->SetFont('wqyzenhei', 'B', 12);
      $pdf->MultiCell(0, 8, 'Attestations', 0, 'L');
      $pdf->Ln(2);
      $pdf->SetFont('wqyzenhei', '', 9);

      foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
        $type_label = $att->get('field_att_type')->entity ? $att->get('field_att_type')->entity->label() : '';
        $axe_label = ($att->hasField('field_att_axe') && !$att->get('field_att_axe')->isEmpty())
          ? ' (' . $att->get('field_att_axe')->entity->label() . ')' : '';
        $valeur = strip_tags($att->get('field_att_valeur')->value ?? '');

        $pdf->SetFont('wqyzenhei', 'B', 9);
        $pdf->MultiCell(0, 5, $type_label . $axe_label, 0, 'L');
        $pdf->SetFont('wqyzenhei', '', 9);
        $pdf->MultiCell(0, 5, $valeur, 0, 'J');
        $pdf->Ln(3);
      }
    }

    $contenu_pdf = $pdf->Output($node->label() . '.pdf', 'S');

    $response = new Response($contenu_pdf);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $node->label()) . '.pdf"');
    return $response;
  }

}
