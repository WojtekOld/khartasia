<?php

namespace Drupal\external_entities\DataProcessor;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use Psr\Log\LoggerInterface;

/**
 * Plugin type manager for data processors.
 *
 * @see \Drupal\external_entities\DataProcessor\DataProcessorInterface
 */
class DataProcessorManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

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
   * Constructs a DataProcessorManager object.
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
      'Plugin/ExternalEntities/DataProcessor',
      $namespaces,
      $module_handler,
      '\Drupal\external_entities\DataProcessor\DataProcessorInterface',
      'Drupal\external_entities\Attribute\DataProcessor',
      'Drupal\external_entities\Annotation\DataProcessor'
    );

    $this->alterInfo('external_entities_data_processor_info');
    $this->setCacheBackend(
      $cache_backend,
      'external_entities_data_processor',
      ['external_entities_data_processor']
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
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) :string {
    $this->messenger->addWarning(
      $this->t(
        "WARNING: Failed to load data processor plugin '@plugin'. Does the plugin exists and is enabled? Is the plugin cache up-to-date? It has been replaced by '@replacement'.",
        [
          '@plugin' => $plugin_id,
          '@replacement' => PropertyMapperBase::DEFAULT_DATA_PROCESSOR,
        ]
      )
    );
    $this->logger->warning("Failed to load data processor plugin '$plugin_id'.");
    return PropertyMapperBase::DEFAULT_DATA_PROCESSOR;
  }

}
