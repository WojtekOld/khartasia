<?php

declare(strict_types=1);

namespace Drupal\imagemagick\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Url;

/**
 * Install time requirements for the Imagemagick module.
 */
class ImagemagickRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  // @phpstan-ignore missingType.iterableValue
  public static function getRequirements(): array {
    $requirements = [];

    if (stripos((string) ini_get('disable_functions'), 'proc_open') !== FALSE) {
      $reported_info = [];
      // proc_open() is disabled.
      $severity = RequirementSeverity::Error;
      $reported_info[] = t("The <a href=':proc_open_url'>proc_open()</a> PHP function is disabled. It must be enabled for the toolkit to be installed. Edit the <a href=':disable_functions_url'>disable_functions</a> entry in your php.ini file, or consult your hosting provider.", [
        ':proc_open_url' => 'http://php.net/manual/en/function.proc-open.php',
        ':disable_functions_url' => 'http://php.net/manual/en/ini.core.php#ini.disable-functions',
      ]);

      $requirements = [
        'imagemagick' => [
          'title' => t('ImageMagick'),
          'description' => [
            '#markup' => implode('<br />', $reported_info),
          ],
          'severity' => $severity,
        ],
      ];
    }

    if ($checkRotateEffects = self::checkRotateEffects()) {
      $requirements = array_merge($requirements, $checkRotateEffects);
    }

    return $requirements;
  }

  /**
   * Returns a warning requirement if core rotate effects are in use.
   */
  // @phpstan-ignore missingType.iterableValue
  public static function checkRotateEffects(): ?array {
    if (\Drupal::entityTypeManager()->hasDefinition('image_style')) {
      $imageRotateEffectsCount = \Drupal::entityTypeManager()->getStorage('image_style')->getQuery()
        ->condition('effects.*.id', 'image_rotate')
        ->count()
        ->accessCheck(TRUE)
        ->execute();
      if ((int) $imageRotateEffectsCount > 0) {
        return [
          'imagemagick_image_rotate' => [
            'title' => t('ImageMagick rotate'),
            'value' => t("There are <a href=':image_styles'>Image Styles</a> using Drupal standard's Rotate effect.", [
              ':image_styles' => Url::fromRoute('entity.image_style.collection')->toString(),
            ]),
            'description' => t("The ImageMagick module does not support it. Use the Rotate effect provided by the <a href=':image_effects'>Image Effects</a> module instead. Check your <a href=':migration_options'>migration options</a>.", [
              ':image_effects' => 'https://www.drupal.org/project/image_effects',
              ':migration_options' => 'https://www.drupal.org/project/image_effects/releases/8.x-3.2#introduce-rotate',
            ]),
            // @todo Consider turning this into an error once the toolkit op is
            //   removed.
            'severity' => RequirementSeverity::Warning,
          ],
        ];
      }
    }
    return NULL;
  }

}
