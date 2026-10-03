<?php

namespace Drupal\external_entities\PropertyMapper;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\external_entities\FieldMapper\FieldMapperBase;
use Psr\Log\LoggerInterface;

/**
 * Plugin type manager for property mappers.
 *
 * @see \Drupal\external_entities\PropertyMapper\PropertyMapperInterface
 */
class PropertyMapperManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

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
   * Constructs a PropertyMapperManager object.
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
      'Plugin/ExternalEntities/PropertyMapper',
      $namespaces,
      $module_handler,
      '\Drupal\external_entities\PropertyMapper\PropertyMapperInterface',
      'Drupal\external_entities\Attribute\PropertyMapper',
      'Drupal\external_entities\Annotation\PropertyMapper'
    );

    $this->alterInfo('external_entities_property_mapper_info');
    $this->setCacheBackend(
      $cache_backend,
      'external_entities_property_mapper',
      ['external_entities_property_mapper']
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
   * @param string $property_name
   *   The name of the field property to map.
   *
   * @return \Drupal\external_entities\PropertyMapper\PropertyMapperInterface[]
   *   An array of compatible field mapper objects.
   */
  public function getCompatiblePropertyMappers(
    string $field_type,
    string $property_name,
  ) :array {
    $property_mappers = [];
    $all_property_mappers = $this->getDefinitions();
    foreach ($all_property_mappers as $property_mapper_id => $definition) {
      if (!empty($definition['field_properties'])) {
        foreach ($definition['field_properties'] as $field_property) {
          [$def_field_type, $def_property_name] = explode(
            ':',
            $field_property
          );
          // Check field type first.
          if (!$def_field_type
            || (in_array($def_field_type, [$field_type, '*']))
          ) {
            // Compatible with field type, now check property.
            if (!$def_property_name
              || (in_array($def_property_name, [$property_name, '*']))
            ) {
              // Compatible, no need to check further.
              $property_mappers[$property_mapper_id] = $definition;
              break 1;
            }
          }
        }
      }
      else {
        // No restrictions, add.
        $property_mappers[$property_mapper_id] = $definition;
      }
    }
    // @todo Allow extensions to alter the list and add compatible instances.
    return $property_mappers;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) :string {
    $this->messenger->addWarning(
      $this->t(
        "WARNING: Failed to load property mapper plugin '@plugin'. Does the plugin exists and is enabled? Is the plugin cache up-to-date? It has been replaced by '@replacement'.",
        [
          '@plugin' => $plugin_id,
          '@replacement' => FieldMapperBase::DEFAULT_PROPERTY_MAPPER,
        ]
      )
    );
    $this->logger->warning("Failed to load property mapper plugin '$plugin_id'.");
    return FieldMapperBase::DEFAULT_PROPERTY_MAPPER;
  }

}
