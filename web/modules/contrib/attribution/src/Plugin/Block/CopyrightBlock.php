<?php

namespace Drupal\attribution\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a copyright block.
 */
#[Block(
  id: 'attribution_copyright',
  admin_label: new TranslatableMarkup('Copyright'),
  category: new TranslatableMarkup('Legal'),
)]
final class CopyrightBlock extends AttributionBaseBlock {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'license' => 'all_rights_reserved',
      'disclaimer' => 'Copyright © [current-date:html_year] [site:name]. @name.',
    ];
  }

}
