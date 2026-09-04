<?php

namespace Drupal\proc\Service;

use Drupal\Component\Utility\Html;

/**
 * Service for cleaning CSS identifiers.
 */
class HtmlCssIdentifierCleaner implements CssIdentifierCleanerInterface {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function cleanCssIdentifier(string $identifier): string {
    return Html::cleanCssIdentifier($identifier);
  }

}
