<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A block nobody may see. Its build must never reach the output.
 */
#[Block(
  id: 'ui_patterns_test_denied_block',
  admin_label: new TranslatableMarkup('Denied block')
)]
class DeniedTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::forbidden()->addCacheTags(['ui_patterns_test:denied'])->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => 'denied block content',
    ];
  }

}
