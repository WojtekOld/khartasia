<?php

declare(strict_types=1);

namespace Drupal\bibcite_ris\Install\Requirements;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

class BibciteRisInstallRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (!class_exists('\LibRIS\RISReader')) {
      $requirements['bibcite_ris_dependencies'] = [
        'title' => t('RIS dependencies'),
        'description' => t("Bibliography &amp; Citation - RIS requires the researchgate/libris library. See the module's README.md file for more information."),
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
