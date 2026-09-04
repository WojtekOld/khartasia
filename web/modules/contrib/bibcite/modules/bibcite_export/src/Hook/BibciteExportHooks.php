<?php

namespace Drupal\bibcite_export\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for bibcite_export.
 */
class BibciteExportHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bibcite_export':
        $module = 'bibcite_export';
        return \Drupal::service('bibcite.help_service')->getHelpMarkup([], $route_name, $module);
    }
  }

}
