<?php

declare(strict_types=1);

namespace Drupal\bibcite\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a BibciteProcessor attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class BibCiteProcessor extends Plugin {

  /**
   * Constructs the BibciteProcessor attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin title.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {
  }

}
