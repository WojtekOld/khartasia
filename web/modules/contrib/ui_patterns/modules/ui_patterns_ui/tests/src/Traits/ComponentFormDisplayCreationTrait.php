<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Traits;

use Drupal\ui_patterns_ui\Entity\ComponentFormDisplay;

/**
 * Creates component form displays for tests.
 */
trait ComponentFormDisplayCreationTrait {

  /**
   * Saves a display.
   *
   * @param string $component_id
   *   The component ID.
   * @param string $form_mode
   *   The form mode name, also the label.
   * @param array $content
   *   The rows, keyed by prop or slot ID. See ::row().
   * @param array $hidden
   *   The hidden prop or slot IDs, as keys.
   * @param bool $status
   *   Whether the display is enabled.
   */
  protected function createDisplay(string $component_id, string $form_mode, array $content, array $hidden = [], bool $status = TRUE): ComponentFormDisplay {
    $display = ComponentFormDisplay::create([
      'label' => \ucfirst($form_mode),
      'component_id' => $component_id,
      'form_mode_name' => $form_mode,
      'status' => $status,
      'content' => $content,
      'hidden' => $hidden,
    ]);
    $display->save();
    return $display;
  }

  /**
   * A display row, shaped like the admin form saves it.
   *
   * @param string $region
   *   One of content, configure, hidden.
   * @param string|null $source_id
   *   The source plugin ID, '' for the source selector, NULL for none.
   * @param array $source
   *   The source settings, left out when empty like the admin form does.
   * @param array $widget_settings
   *   The widget settings.
   * @param int $weight
   *   The weight, stored as a string like the form does.
   */
  protected function row(string $region, ?string $source_id = NULL, array $source = [], array $widget_settings = [], int $weight = 0): array {
    $row = [
      'region' => $region,
      'weight' => (string) $weight,
      'parent' => '',
      'widget_settings' => $widget_settings,
      'third_party_settings' => [],
    ];
    if ($source_id !== NULL) {
      $row['source_id'] = $source_id;
    }
    if ($source !== []) {
      $row['source'] = $source;
    }
    return $row;
  }

}
