<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\external_entities\Entity\ExternalEntityType;
use Psr\Log\LoggerInterface;

/**
 * Plugin type manager for field mappers.
 *
 * @see \Drupal\external_entities\FieldMapper\FieldMapperInterface
 */
class FieldMapperManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a FieldMapperManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    MessengerInterface $messenger,
    LoggerInterface $logger,
  ) {
    parent::__construct(
      'Plugin/ExternalEntities/FieldMapper',
      $namespaces,
      $module_handler,
      '\Drupal\external_entities\FieldMapper\FieldMapperInterface',
      'Drupal\external_entities\Attribute\FieldMapper',
      'Drupal\external_entities\Annotation\FieldMapper'
    );

    $this->alterInfo('external_entities_field_mapper_info');
    $this->setCacheBackend(
      $cache_backend,
      'external_entities_field_mapper',
      ['external_entities_field_mapper']
    );
    $this->messenger = $messenger;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container, array $configuration = [], $plugin_id = '', $plugin_definition = []) {
    return new static(
      $container->get('container.namespaces'),
      $container->get('cache.discovery'),
      $container->get('module_handler'),
      $container->get('messenger'),
      $container->get('logger.channel.external_entities')
    );
  }

  /**
   * Returns an array of field mappers compatible with the given field type.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return \Drupal\external_entities\FieldMapper\FieldMapperInterface[]
   *   An array of compatible field mapper objects.
   */
  public function getCompatibleFieldMappers(string $field_type) :array {
    $field_mappers = [];
    $all_field_mappers = $this->getDefinitions();
    foreach ($all_field_mappers as $field_mapper_id => $definition) {
      if (!$definition['field_types']
        || in_array($field_type, $definition['field_types'])
        || in_array('*', $definition['field_types'])) {
        $field_mappers[$field_mapper_id] = $definition;
      }
    }
    // @todo Allow extensions to alter the list and add compatible instances.
    // Use case: a new derived field type that should be supported like its
    // ancestor (ie. a new "text_new" field type derived from the "text" field
    // type should be able to say that it is compatible with field mappers
    // supporting the "text" field type).
    // See also FieldMapperBase::getFieldTypes().
    return $field_mappers;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) :string {
    $this->messenger->addWarning(
      $this->t(
        "WARNING: Failed to load field mapper plugin '@plugin'. Does the plugin exists and is enabled? Is the plugin cache up-to-date? It has been replaced by '@replacement'.",
        [
          '@plugin' => $plugin_id,
          '@replacement' => ExternalEntityType::DEFAULT_FIELD_MAPPER,
        ]
      )
    );
    $this->logger->warning("Failed to load field mapper plugin '$plugin_id'.");
    return ExternalEntityType::DEFAULT_FIELD_MAPPER;
  }

}
