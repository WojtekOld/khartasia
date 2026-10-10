<?php

namespace Drupal\attribution\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an attribution block.
 */
#[Block(
  id: 'attribution',
  admin_label: new TranslatableMarkup('Attribution'),
  category: new TranslatableMarkup('Legal'),
)]
final class AttributionBlock extends AttributionBaseBlock {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'license' => 'gpl_2_0_or_later',
      'disclaimer' => 'Except where otherwise noted, content on this site is licensed under a <a href="@link">@name</a> license.',
    ];
  }

}
