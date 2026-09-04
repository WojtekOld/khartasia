<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\SourceTree;

use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Processor to apply translations to source nodes.
 *
 * @internal May be challenged.
 */
final class TranslationApplier implements ProcessorInterface {

  public function __construct(
    private array $translations,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function process(TypedDataInterface $element, array $parents, mixed &$tree_item, array $context): void {
    $key = $context['key'];
    if (isset($this->translations[$key])) {
      $tree_item = $this->translations[$key];
    }
  }

}
