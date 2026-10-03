<?php

namespace Drupal\external_entities\DataAggregator;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Form\XnttSubformState;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\FileClientInterface;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\QueryLanguageClientInterface;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClientInterface;
use Drupal\external_entities\Plugin\PluginDebugTrait;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Drupal\external_entities\StorageClient\StorageClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for external entity data aggregators.
 */
abstract class DataAggregatorBase extends PluginBase implements DataAggregatorInterface, PluginFormInterface {

  use PluginDependencyTrait;
  use PluginFormTrait;
  use PluginDebugTrait;

  /**
   * Default storage client plugin id.
   */
  const DEFAULT_STORAGE_CLIENT = 'rest';

  /**
   * The external entity type this storage client is configured for.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The external storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * The storage client plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Grouped list of available storage clients.
   *
   * @var array
   *
   * @see self::getAvailableStorageClients() for the structure.
   */
  protected $availableStorageClients;

  /**
   * Array of storage client plugin instances.
   *
   * @var \Drupal\external_entities\StorageClient\StorageClientInterface[]
   */
  protected $storageClientPlugins = [];

  /**
   * Recursively merge 2 arrays.
   *
   * If $deep_merge is FALSE, only the first-level keys are taken into account.
   * In that case, sub-arrays of the first array may be overriden by values of
   * the second array (which may result in a non-array value).
   * Otherwise, when $deep_merge is TRUE, if the values of a given key is an
   * array either in the first or the second array, the resulting value will be
   * an array that merge the values from both sides, with overrides if needed.
   *
   * @param array $a
   *   First array that may be overriden.
   * @param array $b
   *   Second array that overrides existing values.
   * @param bool $deep_merge
   *   If TRUE, merge sub-arrays together.
   * @param bool $override_empty
   *   If TRUE, only first array empty values (WARNING: are considered empty
   *   NULL, '' and [] which differs from PHP empty() function) can be overriden
   *   by a second array non-empty value.
   * @param bool $preserve_integer_keys
   *   If TRUE, integer keys are preserved and values with the same index may be
   *   overriden according to the above rules.
   * @param bool $no_new_keys
   *   Only existing keys can be overriden. No new keys are added. If TRUE,
   *   parameter $preserve_integer_keys will be forced to TRUE.
   *
   * @return array
   *   The merged array.
   */
  public static function mergeArrays(
    array $a,
    array $b,
    bool $deep_merge = FALSE,
    bool $override_empty = FALSE,
    bool $preserve_integer_keys = FALSE,
    bool $no_new_keys = FALSE,
  ) :array {
    // Force $preserve_integer_keys if $no_new_keys is set.
    if ($no_new_keys) {
      $preserve_integer_keys = TRUE;
    }

    // Prepare result array.
    if ($preserve_integer_keys) {
      $result = $a;
    }
    else {
      $result = [];
      foreach ($a as $key => $value) {
        if (is_int($key)) {
          $result[] = $value;
        }
        else {
          $result[$key] = $value;
        }
      }
    }

    // Merge array b on result.
    if ($deep_merge) {
      // Merge sub-arrays.
      if ($override_empty) {
        foreach ($b as $key => $value) {
          // Check for new keys allowed.
          if ($no_new_keys && !array_key_exists($key, $result)) {
            continue;
          }

          if (is_int($key) && !$preserve_integer_keys) {
            // If we don't preserve int keys, we add the value.
            $result[] = $value;
          }
          elseif (((!isset($result[$key])) || ('' === $result[$key]) || ([] === $result[$key]))
            && ((NULL !== $value) && ('' !== $value) && ([] !== $value))
          ) {
            // Either the key is not int or it is but we preserve in keys, only
            // override empty values with non-empty ones.
            $result[$key] = $value;
          }
          elseif (((isset($result[$key])) && ('' !== $result[$key]) && ([] !== $result[$key]))
            && ((NULL !== $value) && ('' !== $value) && ([] !== $value))
          ) {
            if (is_array($result[$key])) {
              if (is_array($value)) {
                $result[$key] = static::mergeArrays(
                  $result[$key],
                  $value,
                  $deep_merge,
                  $override_empty,
                  $preserve_integer_keys,
                  $no_new_keys
                );
              }
              else {
                $result[$key] = static::mergeArrays(
                  $result[$key],
                  [$value],
                  $deep_merge,
                  $override_empty,
                  $preserve_integer_keys,
                  $no_new_keys
                );
              }
            }
            elseif (is_array($value)) {
              $result[$key] = static::mergeArrays(
                [$result[$key]],
                $value,
                $deep_merge,
                $override_empty,
                $preserve_integer_keys,
                $no_new_keys
              );
            }
          }
        }
      }
      else {
        // Full override.
        foreach ($b as $key => $value) {
          // Check for new keys allowed.
          if ($no_new_keys && !array_key_exists($key, $result)) {
            continue;
          }

          if (is_int($key) && !$preserve_integer_keys) {
            $result[] = $value;
          }
          elseif (isset($result[$key]) && is_array($result[$key])) {
            if (isset($value) && is_array($value)) {
              $result[$key] = static::mergeArrays(
                $result[$key],
                $value,
                $deep_merge,
                $override_empty,
                $preserve_integer_keys,
                $no_new_keys
              );
            }
            else {
              $result[$key] = static::mergeArrays(
                $result[$key],
                [$value],
                $deep_merge,
                $override_empty,
                $preserve_integer_keys,
                $no_new_keys
              );
            }
          }
          elseif (isset($result[$key]) && isset($value) && is_array($value)) {
            $result[$key] = static::mergeArrays(
              [$result[$key]],
              $value,
              $deep_merge,
              $override_empty,
              $preserve_integer_keys,
              $no_new_keys
            );
          }
          else {
            $result[$key] = $value;
          }
        }
      }
    }
    else {
      // Not merging sub-arrays.
      if ($override_empty) {
        foreach ($b as $key => $value) {
          // Check for new keys allowed.
          if ($no_new_keys && !array_key_exists($key, $result)) {
            continue;
          }

          if (is_int($key) && !$preserve_integer_keys) {
            // If we don't preserve int keys, we add the value.
            $result[] = $value;
          }
          elseif (((!isset($result[$key])) || ('' === $result[$key]) || ([] === $result[$key]))
            && ((NULL !== $value) && ('' !== $value) && ([] !== $value))
          ) {
            // Either the key is not int or it is but we preserve in keys, only
            // override empty values with non-empty ones.
            $result[$key] = $value;
          }
        }
      }
      else {
        // Full override.
        foreach ($b as $key => $value) {
          // Check for new keys allowed.
          if ($no_new_keys && !array_key_exists($key, $result)) {
            continue;
          }

          if (is_int($key) && !$preserve_integer_keys) {
            $result[] = $value;
          }
          else {
            $result[$key] = $value;
          }
        }
      }
    }
    return $result;
  }

  /**
   * Constructs a DataAggregatorBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    PluginManagerInterface $storage_client_manager,
  ) {
    $this->debugLevel = $configuration['debug_level'] ?? NULL;
    $this->setConfiguration($configuration);
    $configuration = $this->getConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setStringTranslation($string_translation);
    $this->logger = $logger;
    $this->storageClientManager = $storage_client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory')->get('xntt_data_aggregator_' . $plugin_id),
      $container->get('plugin.manager.external_entities.storage_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() :string {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() :string {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() :array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
    if (!empty($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP])
        && $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP] instanceof ExternalEntityTypeInterface
    ) {
      $this->externalEntityType = $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP];
    }
    unset($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP]);
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'storage_clients' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientDefaultConfiguration() :array {
    return [
      // Allow the aggregator to call back into the entity type.
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this->externalEntityType,
      'debug_level' => $this->getDebugLevel(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() :array {
    $dependencies = [];
    foreach ($this->getStorageClients() as $storage_client) {
      $dependencies = NestedArray::mergeDeep(
        $dependencies,
        $this->getPluginDependencies($storage_client)
      );
    }
    return $dependencies;
  }

  /**
   * Returns an array of storage clients grouped by categories.
   *
   * @return array
   *   Storage client instances grouped by categories (key). Categories are:
   *   'rest', 'files', 'ql', and 'others'. A special key '#group' offers the
   *   storage client plugin id to category name association.
   */
  public function getAvailableStorageClients() :array {
    if (empty($this->availableStorageClients)) {
      $storage_client_groups = [];
      $storage_clients = $this->storageClientManager->getDefinitions();
      foreach ($storage_clients as $storage_client_id => $definition) {
        $config = $this->getStorageClientDefaultConfiguration();
        $storage_client = $this
          ->storageClientManager
          ->createInstance($storage_client_id, $config);
        if ($storage_client instanceof RestClientInterface) {
          $storage_client_groups['rest'][$storage_client_id] = $storage_client;
          $storage_client_groups['#group'][$storage_client_id] = 'rest';
        }
        elseif ($storage_client instanceof FileClientInterface) {
          $storage_client_groups['files'][$storage_client_id] = $storage_client;
          $storage_client_groups['#group'][$storage_client_id] = 'files';
        }
        elseif ($storage_client instanceof QueryLanguageClientInterface) {
          $storage_client_groups['ql'][$storage_client_id] = $storage_client;
          $storage_client_groups['#group'][$storage_client_id] = 'ql';
        }
        else {
          $storage_client_groups['others'][$storage_client_id] = $storage_client;
          $storage_client_groups['#group'][$storage_client_id] = 'others';
        }
      }
      $this->availableStorageClients = $storage_client_groups;
    }
    return $this->availableStorageClients;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientId(int|string $client_key) :string {
    if (!empty($this->storageClientPlugins[$client_key])) {
      $this->configuration['storage_clients'][$client_key]['id'] =
        $this->storageClientPlugins[$client_key]->getPluginId();
    }
    return $this->configuration['storage_clients'][$client_key]['id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function clearStorageClient(
    int|string $client_key,
  ) :self {
    unset($this->configuration['storage_clients'][$client_key]);
    unset($this->storageClientPlugins[$client_key]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStorageClientId(
    string $storage_client_id,
    int|string $client_key,
  ) :self {
    if (empty($storage_client_id)) {
      $this->clearStorageClient($client_key);
    }
    else {
      if ($storage_client_id != ($this->configuration['storage_clients'][$client_key]['id'] ?? '')) {
        $this->configuration['storage_clients'][$client_key] = [
          'id' => $storage_client_id,
          'config' => [],
        ];
        $this->storageClientPlugins[$client_key] = NULL;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClient(int|string $client_key)
  :StorageClientInterface {
    if (empty($this->configuration['storage_clients'][$client_key]['id'])) {
      throw new PluginException(
        sprintf("Invalid storage client plugin key '%d'.", $client_key)
      );
    }
    if (empty($this->storageClientPlugins[$client_key])) {
      $config = NestedArray::mergeDeep(
        $this->getStorageClientDefaultConfiguration(),
        $this->getStorageClientConfig($client_key)
      );
      $this->storageClientPlugins[$client_key] =
        $this->storageClientManager->createInstance(
          $this->getStorageClientId($client_key),
          $config
        );
    }
    return $this->storageClientPlugins[$client_key];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClients() :array {
    foreach ($this->configuration['storage_clients'] as $sckey => $storage_client_setting) {
      if (!empty($storage_client_setting['id'])) {
        // This will fill storageClientPlugins member.
        $this->getStorageClient($sckey);
      }
    }
    // Remove unused keys.
    $this->storageClientPlugins = array_intersect_key(
      $this->storageClientPlugins,
      $this->configuration['storage_clients']
    );
    return $this->storageClientPlugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientConfig(int|string $client_key) :array {
    if (!empty($this->storageClientPlugins[$client_key])) {
      $this->configuration['storage_clients'][$client_key]['config'] =
        $this->storageClientPlugins[$client_key]->getConfiguration();
    }
    return $this->configuration['storage_clients'][$client_key]['config'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setStorageClientConfig(
    array $storage_client_config,
    int|string $client_key,
  ) :self {
    if (!empty($this->configuration['storage_clients'][$client_key]['id'])) {
      if (!empty($this->storageClientPlugins[$client_key])) {
        // Update plugin and local config.
        $this
          ->storageClientPlugins[$client_key]
          ->setConfiguration(
            NestedArray::mergeDeep(
              $this->getStorageClientDefaultConfiguration(),
              $storage_client_config
            )
          );
        $this->configuration['storage_clients'][$client_key]['config'] = $this
          ->storageClientPlugins[$client_key]
          ->getConfiguration();
      }
      else {
        $this->configuration['storage_clients'][$client_key]['config'] =
          $storage_client_config;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientNotes(int|string $client_key) :string {
    return $this->configuration['storage_clients'][$client_key]['notes'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setStorageClientNotes(
    string $storage_client_notes,
    int|string $client_key,
  ) :self {
    if (!empty($this->configuration['storage_clients'][$client_key])) {
      $this->configuration['storage_clients'][$client_key]['notes'] = $storage_client_notes;
    }
    elseif ($client_key) {
      $this->logger->warning(
        'Trying to set notes for a storage client not set (@client_key).',
        [
          '@client_key' => $client_key,
        ]
      );
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string|int $id) :array|null {
    $entities = $this->loadMultiple([$id]);
    return array_key_exists($id, $entities)
      ? $entities[$id]
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Check for Ajax events.
    if (($trigger = $form_state->getTriggeringElement())
      && (preg_match('/^(storages_tab\[config\]\[storage_clients|language_settings\[overrides\]\[\w{2}\]\[storages\]\[config\]\[storage_clients)\]\[(\w+)\]\[id\]$/', $trigger['#name'], $matches))
    ) {
      // Remove current user input on storage client config since form content
      // has changed. Otherwise, if left as is, we would have strange behaviors
      // such as new checkboxes being checked (because their keys are there)
      // while they should not (because their associated values are empty). It
      // usualy comes from hidden fields set with an empty/FALSE value that are
      // turned into non-hidden fields such as checkboxes which are testing if
      // their key exists in the user input to tell if they are checked.
      $ui = $form_state->getUserInput();
      $parents = array_merge(preg_split('/\]?\[/', $matches[1]), [$matches[2], 'config']);
      NestedArray::unsetValue($ui, $parents);
      $form_state->setUserInput($ui);
      $form_state->setRebuild(TRUE);
    }

    $storage_client_configs = $form_state->getValue('storage_clients');
    foreach ($storage_client_configs as $client_key => $client_config) {
      // Skip non-storage client config elements (ie. like 'add_storage').
      if (is_array($client_config)
          && !empty($client_config['id'])
        && !empty($client_config['config'])
      ) {
        // Submit new storage client settings.
        $storage_client_config = $this->getStorageClientDefaultConfiguration();
        $storage_client = $this->storageClientManager->createInstance(
          $client_config['id'],
          $storage_client_config
        );
        if ($storage_client instanceof PluginFormInterface) {
          $storage_client_form_state = XnttSubformState::createForSubform(
            ['storage_clients', $client_key, 'config'],
            $form,
            $form_state
          );
          $storage_client->validateConfigurationForm(
            $form['storage_clients'][$client_key]['config'],
            $storage_client_form_state
          );
        }
      }
    }

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $storage_clients = [];
    $storage_client_configs = $form_state->getValue('storage_clients');
    foreach ($storage_client_configs as $client_key => $client_config) {
      if (!empty($client_config['id'])) {
        $client_config['config'] ??= [];
        // Submit new storage client settings.
        $storage_client_config = $this->getStorageClientDefaultConfiguration();
        $storage_client = $this->storageClientManager->createInstance(
          $client_config['id'],
          $storage_client_config
        );
        if ($storage_client instanceof PluginFormInterface) {
          $storage_client_form_state = XnttSubformState::createForSubform(
            ['storage_clients', $client_key, 'config'],
            $form,
            $form_state
          );
          $storage_client->submitConfigurationForm(
            $form['storage_clients'][$client_key]['config'],
            $storage_client_form_state
          );
          $storage_client_config = $storage_client->getConfiguration();
        }
        else {
          // Clear config.
          $storage_client_config = [];
        }
        $storage_clients[$client_key] = [
          'id' => $client_config['id'],
          'config' => $storage_client_config,
        ];
      }
    }

    // Cleanup form values (ie. only use the specified ones).
    $form_state->setValues([
      'storage_clients' => $storage_clients,
    ]);

    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($form_state->getValues());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestedDrupalFields() :array {
    // Loop on storage clients and build field request.
    $field_requests = [];
    $clients = $this->getStorageClients();
    foreach ($clients as $client) {
      $client_fields = $client->getRequestedDrupalFields();
      foreach ($client_fields as $field_name => $field_request) {
        if (empty($field_requests[$field_name])
          || !empty($field_request['required'])
          && (
              empty($field_requests[$field_name]['required'])
              || (($field_request['required'] == 'required')
                  && ($field_requests[$field_name]['required'] != 'required'))
              || (($field_request['required'] != 'optional')
                  && ($field_requests[$field_name]['required'] == 'optional')))
        ) {
          $field_requests[$field_name] = $field_request;
        }
      }
    }
    return $field_requests;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestedMapping(string $field_name, string $field_type) :array {
    // Loop on storage clients and get mapping requests.
    $mapping = [];
    $clients = $this->getStorageClients();
    foreach ($clients as $client) {
      $client_mapping = $client->getRequestedMapping($field_name, $field_type);
      if (empty($mapping)
        || !empty($client_mapping['required'])
        && (
            empty($mapping['required'])
            || (($mapping['required'] != 'required')
                && ($client_mapping['required'] == 'required')))
      ) {
        $mapping = $client_mapping;
      }
      if (!empty($mapping['required'])
          && ($mapping['required'] == 'required')
      ) {
        break;
      }
    }
    return $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function isCountable() :bool {
    $countable = TRUE;
    $clients = $this->getStorageClients();
    foreach ($clients as $client) {
      if (!$client->isCountable()) {
        return FALSE;
      }
    }
    return $countable;
  }

}
