<?php

declare(strict_types=1);

namespace Drupal\ui_icons_font\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Requirements for the ui_icons_font module.
 */
class UiIconsFontRequirements {

  use StringTranslationTrait;

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $library_exists = class_exists('\FontLib\Font');

    return [
      'ui_icons_font' => [
        'title' => $this->t('UI Icons Font'),
        'value' => $library_exists ? $this->t('Font library detected') : $this->t('Missing Font library!'),
        'description' => $library_exists ? '' : $this->t('Install with: composer require dompdf/php-font-lib'),
        'severity' => $library_exists ? RequirementSeverity::OK : RequirementSeverity::Warning,
      ],
    ];
  }

}
