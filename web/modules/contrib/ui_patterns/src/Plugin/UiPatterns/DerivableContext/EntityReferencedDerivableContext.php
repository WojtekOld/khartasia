<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\DerivableContext;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\ui_patterns\Attribute\DerivableContext;
use Drupal\ui_patterns\DerivableContextPluginBase;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\Plugin\Derivative\EntityReferencedDerivableContextDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivable context plugins for entity Reference fields.
 */
#[DerivableContext(
  id: 'entity_reference',
  label: new TranslatableMarkup('Entity Referenced from fields'),
  description: new TranslatableMarkup('Derived contexts for Entity Reference Fields.'),
  deriver: EntityReferencedDerivableContextDeriver::class
)]
class EntityReferencedDerivableContext extends DerivableContextPluginBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity repository.
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ContextRepositoryInterface::class),
    );
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->entityFieldManager = $container->get(EntityFieldManagerInterface::class);
    $instance->entityRepository = $container->get(EntityRepositoryInterface::class);
    $instance->sampleEntityGenerator = $container->get(SampleEntityGeneratorInterface::class);
    $instance->logger = $container->get('logger.channel.ui_patterns');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedContexts(): array {
    $referenced_entities = $this->getEntities();
    if (empty($referenced_entities)) {
      return [];
    }
    $removed_context_keys = ['entity', 'ui_patterns:field:', 'bundle'];
    $base_context = \array_filter($this->context, static function ($one_context, $one_context_id) use (&$removed_context_keys) {
      if (\in_array($one_context_id, $removed_context_keys, TRUE)) {
        return FALSE;
      }
      foreach ($removed_context_keys as $removed_context_key) {
        if (\str_starts_with($one_context_id, $removed_context_key)) {
          return FALSE;
        }
      }
      return TRUE;
    }, \ARRAY_FILTER_USE_BOTH);
    $base_context = RequirementsContext::removeFromContext(['field_granularity:item'], $base_context);
    $metadata = $this->parsePluginId();
    $entity_type_id = $metadata['entity_type_id'];
    $bundle = $metadata['bundle'];
    // Bundle context definition.
    $bundle_context_definition = new ContextDefinition('string', 'Bundle');
    $base_context['bundle'] = new Context($bundle_context_definition, $bundle);
    // Entity context definition.
    $entity_context_definition = new EntityContextDefinition($entity_type_id);
    if (!empty($bundle)) {
      $entity_context_definition->addConstraint('Bundle', [$bundle]);
    }

    // Generate the contexts.
    $returned_contexts = [];
    foreach ($referenced_entities as $referenced_entity) {
      $returned_contexts[] = \array_merge($base_context, [
        'entity' => new Context($entity_context_definition, $referenced_entity),
      ]);
    }
    return $returned_contexts;
  }

  /**
   * Decode entity type, bundle and field name from the plugin ID.
   *
   * @return array
   *   The entity_type_id, bundle, field_name and parent_entity_type_id.
   */
  protected function parsePluginId(): array {
    // Base, entity_type, bundle, field name, target_entity_type, target_bundle.
    $split_plugin_id = \explode(PluginBase::DERIVATIVE_SEPARATOR, $this->getPluginId());
    [$bundle, $entity_type_id, $ref_field_name] = \array_slice(\array_reverse($split_plugin_id), 0, 3);
    return [
      'entity_type_id' => $entity_type_id,
      'bundle' => $bundle,
      'field_name' => $ref_field_name,
      'parent_entity_type_id' => $split_plugin_id[1],
    ];
  }

  /**
   * Retrieve the entity from the context.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity.
   */
  protected function getEntityFromContext(): ?EntityInterface {
    if (!isset($this->context['entity'])) {
      // This case is not supposed to happen,
      // unless context guessing is not working,
      // like when, for example, using Display Suite without
      // the correct module installed to guess the entity...etc.
      $this->logger->error('Missing entity from context for @entity_type_id', [
        '@entity_type_id' => $this->parsePluginId()['parent_entity_type_id'],
      ]);
      return NULL;
    }
    try {
      return $this->context['entity']->getContextValue();
    }
    catch (\Exception $e) {
      $this->logger->error('Missing entity from context for @entity_type_id: @error', [
        '@entity_type_id' => $this->parsePluginId()['parent_entity_type_id'],
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get entities for this derivable context.
   *
   * @return array
   *   The references entities.
   */
  protected function getEntities(): array {
    $entity = $this->getEntityFromContext();
    if (!($entity instanceof EntityInterface)) {
      return [];
    }
    $metadata = $this->parsePluginId();
    $entity_type_id = $metadata['entity_type_id'];
    $bundle = $metadata['bundle'];
    // Get referenced entities.
    $referenced_entities = $this->getReferencedEntities($entity, $metadata['field_name'], $bundle);
    if ((\count($referenced_entities) === 0) && !$entity->id()) {
      // Case when the entity is a sample (we are probably in a form)
      // we generate a sample referenced entity.
      $referenced_entities[] = $this->sampleEntityGenerator->get($entity_type_id, empty($bundle) ? $this->findEntityBundleWithField($entity_type_id, NULL) : $bundle);
    }
    elseif (isset($this->context['ui_patterns:field:index'])) {
      // The index is a field delta. A delta with no viewable entity yields
      // nothing, never the entities of the other deltas.
      $field_index = $this->context['ui_patterns:field:index']->getContextValue();
      $referenced_entities = isset($referenced_entities[$field_index]) ? [$referenced_entities[$field_index]] : [];
    }
    return $referenced_entities;
  }

  /**
   * Get the referenced entities the current user can view.
   *
   * Same rules as core entity reference formatters: the entity is translated
   * for the display language first, so access is checked on the translation
   * that will be shown. The access cacheability is collected for every
   * referenced entity, denied ones included, so the render cache varies and
   * gets invalidated the same way core does.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity.
   * @param string $ref_field_name
   *   The field name of the reference field.
   * @param string $bundle
   *   Optional bundle of the referenced entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The viewable referenced entities, keyed by field delta.
   */
  protected function getReferencedEntities(?EntityInterface $entity, string $ref_field_name, string $bundle = ''): array {
    if (!($entity instanceof ContentEntityInterface) || !$entity->hasField($ref_field_name)) {
      return [];
    }
    $langcode = $this->getDisplayLangcode($entity);
    $referenced_entities = [];
    $field_reference = $entity->get($ref_field_name);
    for ($delta = 0; $delta < $field_reference->count(); ++$delta) {
      $referenced_entity = $this->getReferencedEntityAtDelta($field_reference, $delta, $bundle);
      if (!$referenced_entity) {
        continue;
      }
      if ($referenced_entity instanceof TranslatableInterface) {
        $referenced_entity = $this->entityRepository->getTranslationFromContext($referenced_entity, $langcode);
      }
      $access = $referenced_entity->access('view', NULL, TRUE);
      $this->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $this->addCacheableDependency($referenced_entity);
        $referenced_entities[$delta] = $referenced_entity;
      }
    }
    return $referenced_entities;
  }

  /**
   * The entity referenced at a field delta, if it matches the bundle.
   */
  protected function getReferencedEntityAtDelta(FieldItemListInterface $field_reference, int $delta, string $bundle): ?EntityInterface {
    $typed_data_item = $field_reference->get($delta)?->get('entity');
    $referenced_entity = ($typed_data_item instanceof EntityReference) ? $typed_data_item->getValue() : NULL;
    if (!($referenced_entity instanceof EntityInterface)) {
      return NULL;
    }
    return (empty($bundle) || $referenced_entity->bundle() === $bundle) ? $referenced_entity : NULL;
  }

  /**
   * The language the referenced entities are displayed in.
   *
   * Set by the field formatters as ui_patterns:lang_code. Without it, the
   * entity in context is already the translation being rendered.
   */
  protected function getDisplayLangcode(EntityInterface $entity): string {
    if (isset($this->context['ui_patterns:lang_code'])) {
      $langcode = $this->context['ui_patterns:lang_code']->getContextValue();
      if (\is_string($langcode) && $langcode !== '') {
        return $langcode;
      }
    }
    return $entity->language()->getId();
  }

  /**
   * Find an entity bundle which eventually has a field.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $field_name
   *   The field name to be found in searched bundle.
   *
   * @return string
   *   The bundle.
   */
  protected function findEntityBundleWithField(string $entity_type_id, ?string $field_name = NULL): string {
    // @todo better implementation with service 'entity_type.bundle.info'
    $bundle = $entity_type_id;
    $bundle_entity_type = $this->entityTypeManager->getDefinition($entity_type_id)->getBundleEntityType();
    if ($bundle_entity_type !== NULL) {
      $bundle_list = $this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple();
      if (\count($bundle_list) > 0) {
        foreach ($bundle_list as $bundle_entity) {
          $bundle_to_test = (string) $bundle_entity->id();
          if ($field_name === NULL) {
            $bundle = $bundle_to_test;
            break;
          }
          $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_to_test);
          if (\array_key_exists($field_name, $definitions)) {
            $bundle = $bundle_to_test;
            break;
          }
        }
      }
    }
    return $bundle;
  }

}
