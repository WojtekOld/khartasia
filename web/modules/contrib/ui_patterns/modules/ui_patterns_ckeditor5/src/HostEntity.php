<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ui_patterns\Entity\SampleEntityGenerator;

/**
 * The entity whose text holds the embedded components.
 *
 * Gives the components the "entity" and "bundle" contexts their sources
 * need, as the block and field formatter plugin types do. In the editor,
 * the entity comes from the request the JavaScript plugin sends; on the
 * page, from the entity being rendered, pushed around the filtering of each
 * processed_text element by hook_entity_display_build_alter().
 */
final class HostEntity implements TrustedCallbackInterface {

  /**
   * Render array key carrying the entity of a processed_text element.
   */
  public const ELEMENT_KEY = '#ui_patterns_ckeditor5_host_entity';

  /**
   * Entities being rendered, innermost last: components nest text formats.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  private array $stack = [];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private SampleEntityGenerator $sampleEntityGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderPush', 'preRenderPop'];
  }

  /**
   * Pre-render callback: makes the element's entity the current host.
   */
  public static function preRenderPush(array $element): array {
    $entity = $element[self::ELEMENT_KEY] ?? NULL;
    \Drupal::service('ui_patterns_ckeditor5.host_entity')->push($entity instanceof EntityInterface ? $entity : NULL);
    return $element;
  }

  /**
   * Pre-render callback: releases the host pushed by preRenderPush().
   */
  public static function preRenderPop(array $element): array {
    \Drupal::service('ui_patterns_ckeditor5.host_entity')->pop();
    return $element;
  }

  /**
   * Makes an entity the current host; NULL when the text has no entity.
   */
  public function push(?EntityInterface $entity): void {
    $this->stack[] = $entity;
  }

  /**
   * Releases the current host.
   */
  public function pop(): void {
    \array_pop($this->stack);
  }

  /**
   * The contexts of the current host.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The contexts, or nothing without a host.
   */
  public function getContexts(): array {
    $entity = \end($this->stack);
    return $entity instanceof EntityInterface ? self::contextsOf($entity) : [];
  }

  /**
   * The contexts of the host described by request values.
   *
   * The values are entity_type, entity_id and entity_bundle as posted by the
   * JavaScript plugin. Without an id the entity is not saved yet: a sample
   * entity of the bundle stands for it, so that the sources depending on
   * field values have some.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The contexts, or nothing when the values do not describe an accessible
   *   entity.
   */
  public function getContextsFromValues(array $values): array {
    $entity_type_id = $values['entity_type'] ?? NULL;
    if (!\is_string($entity_type_id) || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return [];
    }
    $entity_id = $values['entity_id'] ?? NULL;
    $entity = (\is_int($entity_id) || (\is_string($entity_id) && $entity_id !== ''))
      ? $this->loadEntity($entity_type_id, $entity_id)
      : $this->sampleEntity($entity_type_id, $values['entity_bundle'] ?? NULL);
    return $entity ? self::contextsOf($entity) : [];
  }

  /**
   * The contexts the sources expect for an entity.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The entity context and its bundle, keyed by "entity" and "bundle".
   */
  private static function contextsOf(EntityInterface $entity): array {
    return [
      'entity' => EntityContext::fromEntity($entity),
      'bundle' => new Context(ContextDefinition::create('string'), $entity->bundle()),
    ];
  }

  /**
   * Loads a saved host, when the current user may view it.
   */
  private function loadEntity(string $entity_type_id, int|string $entity_id): ?EntityInterface {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    return $entity && $entity->access('view') ? $entity : NULL;
  }

  /**
   * A sample of the unsaved host, when the current user may create it.
   */
  private function sampleEntity(string $entity_type_id, mixed $bundle): ?EntityInterface {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if ($entity_type->getKey('bundle')) {
      if (!\is_string($bundle) || $bundle === '') {
        return NULL;
      }
    }
    else {
      $bundle = $entity_type_id;
    }
    $allowed = $this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle);
    return $allowed ? $this->sampleEntityGenerator->get($entity_type_id, $bundle) : NULL;
  }

}
