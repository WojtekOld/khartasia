<?php

namespace Drupal\proc\Service;

/**
 * Interface for the CSS identifier cleaner service.
 */
interface CssIdentifierCleanerInterface {

  /**
   * Cleans a CSS identifier.
   *
   * @param string $identifier
   *   The CSS identifier to clean.
   *
   * @return string
   *   The cleaned CSS identifier.
   */
  public function cleanCssIdentifier(string $identifier): string;

}
