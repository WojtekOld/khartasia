<?php

declare(strict_types=1);

namespace Drupal\term_glossary_tippy\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\term_glossary_tippy\TippyLibraries;

/**
 * Requirements hook implementations for term_glossary_tippy.
 */
class TermGlossaryTippyRequirements {

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    return self::checkRequirements();
  }

  /**
   * Builds the runtime requirements for the external libraries.
   *
   * Static so the legacy procedural hook_requirements() implementation
   * used on Drupal cores older than 11.3 can share the same logic.
   *
   * @return array
   *   The runtime requirements.
   */
  public static function checkRequirements(): array {
    $requirements = [];
    // The RequirementSeverity enum only exists on Drupal 11.2+, fall back
    // to the legacy integer constants on older cores.
    $has_severity_enum = class_exists(RequirementSeverity::class);
    $missing_files = TippyLibraries::getMissingFiles();
    if ($missing_files === []) {
      $requirements['term_glossary_tippy_libraries'] = [
        'title' => new TranslatableMarkup('Taxonomy Term Glossary Tippy.js'),
        'value' => new TranslatableMarkup('The required external libraries are correctly installed.'),
        'severity' => $has_severity_enum ? RequirementSeverity::OK : REQUIREMENT_OK,
      ];
    }
    else {
      // Missing libraries only break the site rendering when the Tippy.js
      // handler is the active integration type, report a warning otherwise.
      $integration_type = \Drupal::config('term_glossary.glossaryconfig')
        ->get('integration_type');
      $is_error = $integration_type === 'term_glossary_tippy';
      if ($has_severity_enum) {
        $severity = $is_error ? RequirementSeverity::Error : RequirementSeverity::Warning;
      }
      else {
        $severity = $is_error ? REQUIREMENT_ERROR : REQUIREMENT_WARNING;
      }
      $requirements['term_glossary_tippy_libraries'] = [
        'title' => new TranslatableMarkup('Taxonomy Term Glossary Tippy.js'),
        'value' => new TranslatableMarkup('Required external libraries are missing.'),
        'description' => TippyLibraries::buildWarningMessage($missing_files),
        'severity' => $severity,
      ];
    }
    return $requirements;
  }

}
