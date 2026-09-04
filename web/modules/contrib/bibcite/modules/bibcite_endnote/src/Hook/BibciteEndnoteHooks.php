<?php

namespace Drupal\bibcite_endnote\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for bibcite_endnote.
 */
class BibciteEndnoteHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bibcite_endnote':
        $module = 'bibcite_endnote';
        return \Drupal::service('bibcite.help_service')->getHelpMarkup([], $route_name, $module);
    }
  }

}
