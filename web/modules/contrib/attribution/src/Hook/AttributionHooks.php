<?php

declare(strict_types=1);

namespace Drupal\attribution\Hook;

use Drupal\Core\Field\FieldTypeCategoryManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for attribution.
 */
class AttributionHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help(string $route_name): string {
    switch ($route_name) {
      case 'help.page.attribution':
        $output = '';
        $output .= '<h3>' . t('About') . '</h3>';
        $output .= '<p>' . t('Attach an attribution and licensing information to your site and any fieldable entity.') . '</p>';
        return $output;
    }
    return '';
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme(): array {
    return [
      'attribution_plain' => [
        'variables' => [
          'source' => NULL,
          'author' => NULL,
          'license' => NULL,
          'creation' => NULL,
          'attributes' => NULL,
        ],
      ],
      'attribution_plain_oneline' => [
        'variables' => [
          'source' => NULL,
          'author' => NULL,
          'license' => NULL,
          'creation' => NULL,
        ],
      ],
      'attribution_html' => [
        'variables' => [
          'source' => NULL,
          'author' => NULL,
          'license' => NULL,
          'creation' => NULL,
          'attributes' => NULL,
        ],
      ],
      'attribution_creative_commons' => [
        'variables' => [
          'source' => NULL,
          'author' => NULL,
          'license' => NULL,
          'creation' => NULL,
          'attributes' => NULL,
        ],
      ],
      'attribution_creative_commons_icons' => [
        'variables' => [
          'source' => NULL,
          'author' => NULL,
          'license' => NULL,
          'creation' => NULL,
          'attributes' => NULL,
        ],
      ],
      'attribution_creative_commons_refined' => [
        'variables' => [
          'source' => NULL,
          'author' => NULL,
          'license' => NULL,
          'creation' => NULL,
          'attributes' => NULL,
        ],
      ],
      'attribution_default' => [
        'variables' => [
          'lead' => NULL,
          'parts' => [],
          'creation' => NULL,
          'divider' => FALSE,
          'attributes' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_field_type_category_info_alter().
   */
  #[Hook('field_type_category_info_alter')]
  public static function fieldTypeCategoryInfoAlter(array &$definitions): void {
    $definitions[FieldTypeCategoryManagerInterface::FALLBACK_CATEGORY]['libraries'][] = 'attribution/drupal.attribution-icon';
  }

}
