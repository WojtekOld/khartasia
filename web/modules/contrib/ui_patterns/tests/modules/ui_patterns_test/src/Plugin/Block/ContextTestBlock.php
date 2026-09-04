<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A block needing a node context, to test sources built without one.
 */
#[Block(
  id: 'ui_patterns_test_context_block',
  admin_label: new TranslatableMarkup('Needs a node'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
  ]
)]
class ContextTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->getContextValue('node')->label(),
    ];
  }

}
