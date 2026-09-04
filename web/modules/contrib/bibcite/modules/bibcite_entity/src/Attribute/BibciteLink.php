<?php

declare(strict_types=1);

namespace Drupal\bibcite_entity\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Link item attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class BibciteLink extends Plugin {

  /**
   * Constructs the BibciteLink attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin title.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
