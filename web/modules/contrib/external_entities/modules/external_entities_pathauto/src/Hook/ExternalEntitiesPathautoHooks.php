<?php

namespace Drupal\external_entities_pathauto\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Form\ExternalEntityTypeForm;
use Drupal\external_entities_pathauto\Plugin\pathauto\AliasType\ExternalEntityAliasTypeBase;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for external_entities_pathauto.
 */
class ExternalEntitiesPathautoHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_pathauto_alias_types_alter().
   */
  #[Hook('pathauto_alias_types_alter')]
  public static function pathautoAliasTypesAlter(&$types) {
    foreach (array_keys($types) as $plugin_id) {
      if (!empty($types[$plugin_id]['provider']) && $types[$plugin_id]['provider'] === 'external_entities') {
        $types[$plugin_id]['class'] = ExternalEntityAliasTypeBase::class;
      }
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public static function configSchemaInfoAlter(&$definitions) {
    // Adds 'generate_aliases' property to external entity type config schema.
    $definitions['external_entities.external_entity_type.*']['mapping']['generate_aliases'] ??= [
      'type' => 'boolean',
      'label' => 'Automatically generate aliases',
          // Let it be nullable for config saved before enabling the module.
      'nullable' => TRUE,
    ];
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    // Add config checkbox for 'generate_aliases' setting.
    // Only work on external entity type forms.
    if (('external_entity_type_edit_form' == $form_id || 'external_entity_type_add_form' == $form_id) && $form_state->getFormObject() instanceof ExternalEntityTypeForm) {
      $external_entity_type = $form_state->getFormObject()->getEntity();
      // Add alias generation checkbox.
      $form['generate_aliases'] = [
        '#title' => $this->t('Automatically generate aliases'),
        '#type' => 'checkbox',
        '#default_value' => $external_entity_type->getThirdPartySetting('external_entities_pathauto', 'generate_aliases'),
        '#description' => $this->t('Whether or not to automatically generate aliases for this external entity type.'),
      ];
      $form['#entity_builders'][] = 'external_entities_pathauto_xntt_form_builder';
    }
  }

  /**
   * Implements hook_entity_storage_load().
   */
  #[Hook('entity_storage_load')]
  public static function entityStorageLoad(array $entities, $entity_type) {
    /** @var \Drupal\external_entities\Entity\ExternalEntity $external_entity_type */
    $external_entity_type = \Drupal::entityTypeManager()->getStorage('external_entity_type')->load($entity_type);
    if (!$external_entity_type) {
      return;
    }
    $generate_aliases = $external_entity_type->getThirdPartySetting('external_entities_pathauto', 'generate_aliases');
    if (!$generate_aliases) {
      return;
    }
    /** @var \Drupal\pathauto\PathautoGeneratorInterface $pathauto_generator */
    $pathauto_generator = \Drupal::service('pathauto.generator');
    /** @var \Drupal\path_alias\AliasManagerInterface $path_alias_manager */
    $path_alias_manager = \Drupal::service('path_alias.manager');
    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $entity */
    foreach ($entities as $entity) {
      $path = '/' . $entity->toUrl()->getInternalPath();
      $alias = $path_alias_manager->getAliasByPath($path, $entity->language()->getId());
      if ($alias !== $path) {
        continue;
      }
      // Generate an alias.
      $entity->path = $pathauto_generator->updateEntityAlias($entity, 'insert');
    }
  }

}
