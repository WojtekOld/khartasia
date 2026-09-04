<?php

declare(strict_types=1);

namespace Drupal\sophron\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for Sophron.
 */
class SophronHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'sophron.settings':
        $output = '';
        $output .= '<p>' . $this->t('<strong>Sophron</strong> provides an extensive MIME types management API, enhancing Drupal core capabilities. It integrates with the <a href=":mimemap_url">FileEye/MimeMap</a> PHP library.', [':mimemap_url' => 'https://github.com/FileEye/MimeMap/']) . '</p>';
        return $output;

    }
    return NULL;
  }

}
