<?php

namespace Drupal\term_glossary_per_node;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Various utilities methods for term_glossary_per_node.
 */
class TermGlossaryUtils {

  /**
   * Get the glossary display state of a node.
   *
   * @phpstan-ignore-next-line See: https://github.com/mglaman/phpstan-drupal/issues/927
   */
  public static function isNodeGlossaryActive(NodeInterface $node): bool {
    // If no value has been set on the node, use the value of the field.
    if ($node->hasField('term_glossary_active')) {
      return (bool) $node->get('term_glossary_active')->value;
    }
    return FALSE;
  }

  /**
   * Check if user is authorized to update glossary state.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The user to check permissions for.
   *
   * @return bool
   *   TRUE if the user can change glossary display, FALSE otherwise.
   */
  public static function hasEditGlossaryPermission(AccountProxyInterface $currentUser): bool {
    return $currentUser->hasPermission('edit term glossary node display') ||
      $currentUser->hasPermission('administer nodes');
  }

}
