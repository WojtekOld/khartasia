<?php

declare(strict_types=1);

namespace Drupal\bibcite\Install\Requirements;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

class BibciteInstallRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (!class_exists('\Seboettg\CiteProc\CiteProc') || !class_exists('\ADCI\FullNameParser\Parser')) {
      $requirements['bibcite_dependencies'] = [
        'title' => t('Bibliography &amp; Citation dependencies'),
        'description' => t("Bibliography &amp; Citation requires the seboettg/citeproc-php and adci/full-name-parser libraries. See the module's README.md file for more information."),
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
