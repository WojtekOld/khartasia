<?php

namespace Drupal\bibcite_ris\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for bibcite_ris.
 */
class BibciteRisHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bibcite_ris':
        $module = 'bibcite_ris';
        return \Drupal::service('bibcite.help_service')->getHelpMarkup([], $route_name, $module);
    }
  }

}
