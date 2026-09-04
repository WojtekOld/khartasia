<?php

declare(strict_types=1);

namespace Drupal\bibcite_bibtex\Install\Requirements;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

class BibciteBibtexInstallRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (!class_exists('\RenanBr\BibTexParser\Parser')) {
      $requirements['bibcite_bibtex_dependencies'] = [
        'title' => t('BibTeX dependencies'),
        'description' => t("Bibliography &amp; Citation - BibTeX requires the renanbr/bibtex-parser library. See the module's README.md file for more information."),
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
