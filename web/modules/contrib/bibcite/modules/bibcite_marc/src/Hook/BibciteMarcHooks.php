<?php

namespace Drupal\bibcite_marc\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for bibcite_marc.
 */
class BibciteMarcHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bibcite_marc':
        $module = 'bibcite_marc';
        return \Drupal::service('bibcite.help_service')->getHelpMarkup([], $route_name, $module);
    }
  }

}
