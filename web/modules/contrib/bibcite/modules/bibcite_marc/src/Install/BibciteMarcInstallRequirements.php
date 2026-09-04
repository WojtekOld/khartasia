<?php

declare(strict_types=1);

namespace Drupal\bibcite_marc\Install\Requirements;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

class BibciteMarcInstallRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (!class_exists('\PhpMarc\Record')) {
      $requirements['bibcite_marc_dependencies'] = [
        'title' => t('MARC dependencies'),
        'description' => t("Bibliography &amp; Citation - Marc requires the caseyamcl/php-marc21 library. See the module's README.md file for more information."),
        'severity' => DeprecationHelper::backwardsCompatibleCall(
          currentVersion: \Drupal::VERSION,
          deprecatedVersion: '11.2',
          currentCallable: fn() => RequirementSeverity::Error,
          deprecatedCallable: fn() => REQUIREMENT_ERROR,
        ),
      ];
    }
    return $requirements;
  }
}
