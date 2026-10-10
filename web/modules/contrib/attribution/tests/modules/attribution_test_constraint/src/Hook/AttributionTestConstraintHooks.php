<?php

declare(strict_types=1);

namespace Drupal\attribution_test_constraint\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for attribution_test_constraint.
 */
class AttributionTestConstraintHooks {

  /**
   * Value rejected by the property constraints.
   */
  public const INVALID_VALUE = 'invalid';

  /**
   * Properties covering each container the widgets nest elements in.
   */
  public const CONSTRAINED_PROPERTIES = [
    'source_name',
    'author_name',
    'ai_tool',
    'prompt_editor_name',
  ];

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public static function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() !== 'entity_test' || empty($fields['field_attribution'])) {
      return;
    }
    foreach (self::CONSTRAINED_PROPERTIES as $property) {
      $fields['field_attribution']->addPropertyConstraints($property, [
        'Regex' => [
          'pattern' => '/^' . self::INVALID_VALUE . '$/',
          'match' => FALSE,
          'message' => "Invalid $property.",
        ],
      ]);
    }
  }

}
