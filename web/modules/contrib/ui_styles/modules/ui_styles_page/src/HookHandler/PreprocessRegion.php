<?php

declare(strict_types=1);

namespace Drupal\ui_styles_page\HookHandler;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Template\AttributeHelper;
use Drupal\ui_styles_page\UiStylesPageInterface;

/**
 * Add classes to region.
 */
class PreprocessRegion {

  /**
   * Inject classes.
   */
  public function preprocess(array &$variables): void {
    $settings = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', static fn () => \Drupal::service(ThemeSettingsProvider::class)->getSetting(UiStylesPageInterface::REGION_STYLES_KEY_THEME_SETTINGS), static fn () => \theme_get_setting(UiStylesPageInterface::REGION_STYLES_KEY_THEME_SETTINGS));
    if (!\is_array($settings)) {
      return;
    }

    /** @var string $region */
    $region = $variables['region'];
    /** @var array $selected */
    $selected = $settings[$region]['selected'] ?? [];
    /** @var string $extra */
    $extra = $settings[$region]['extra'] ?? '';

    $extra = \explode(' ', $extra);
    $classes = \array_merge($selected, $extra);
    $classes = \array_unique(\array_filter($classes));

    if (empty($classes)) {
      return;
    }

    $variables['attributes'] = $variables['attributes'] ?? [];
    $variables['attributes'] = AttributeHelper::mergeCollections(
      // @phpstan-ignore-next-line
      $variables['attributes'],
      [
        'class' => $classes,
      ]
    );
  }

}
