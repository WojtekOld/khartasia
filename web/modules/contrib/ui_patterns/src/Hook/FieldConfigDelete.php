<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Hook;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_patterns\DerivableContextPluginManager;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Hook implementations for ui_patterns.
 */
class FieldConfigDelete {

  public function __construct(
    protected SampleEntityGeneratorInterface $sampleEntityGenerator,
    protected SourcePluginManager $sourcePluginManager,
    protected DerivableContextPluginManager $derivableContextPluginManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('field_config_delete')]
  public function fieldConfigDeleteHook(FieldConfigInterface $field_config): void {
    $entity_type = $field_config->getTargetEntityTypeId();
    $bundle = $field_config->getTargetBundle();
    $this->sampleEntityGenerator->delete($entity_type, $bundle);
    // @todo trigger an event and do this logic in UiPatternsEntitySchemaSubscriber
    $this->sourcePluginManager->clearCachedDefinitions();
    $this->derivableContextPluginManager->clearCachedDefinitions();
  }

}
