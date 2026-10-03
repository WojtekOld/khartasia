<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Exception\FilesExternalEntityException;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Abstract class for external entities storage client using files.
 */
abstract class FileClientBase extends StorageClientBase implements FileClientInterface {

  use PluginFormTrait;

  /**
   * File type name (human readable).
   *
   * @var string
   */
  protected $fileType;

  /**
   * Plural file type name (human readable).
   *
   * @var string
   */
  protected $fileTypePlural;

  /**
   * Capitalized file type name (human readable).
   *
   * @var string
   */
  protected $fileTypeCap;

  /**
   * Plural capitalized file type name (human readable).
   *
   * @var string
   */
  protected $fileTypeCapPlural;

  /**
   * File type extensions (including the dot), the first one is the default one.
   *
   * @var string[]
   */
  protected $fileExtensions;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Loaded file data (cache).
   *
   * @var array
   */
  protected $fileEntityData;

  /**
   * Loaded file index (cache).
   *
   * @var array
   */
  protected $entityFileIndex;

  /**
   * Tells if an index file is needed.
   *
   * @var bool
   */
  protected $useIndexFile;

  /**
   * File name filtering regular expression.
   *
   * @var string
   */
  protected $fileFilterRegex;

  /**
   * Tells if entity id is part of the file path.
   *
   * @var bool
   */
  protected $idInPath;

  /**
   * Supported fields for file path filtering.
   *
   * @var array
   */
  protected $supportedFields = [
    'id',
    'path',
    'dirname',
    'basename',
    'extension',
    'filename',
  ];

  /**
   * Constructs a files external storage object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token_service,
    ConfigFactory $config_factory,
    MessengerInterface $messenger,
    CacheBackendInterface $cache,
    EventDispatcherInterface $event_dispatcher,
    StreamWrapperManagerInterface $stream_wrapper_manager,
  ) {
    // Services injection.
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->cache = $cache;
    $this->streamWrapperManager = $stream_wrapper_manager;

    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger,
      $entity_type_manager,
      $entity_field_manager,
      $token_service,
      $event_dispatcher,
    );

    // Defaults.
    $this->fileType = 'external entity';
    $this->fileTypePlural = 'external entities';
    $this->fileTypeCap = 'External Entity';
    $this->fileTypeCapPlural = 'External Entities';
    $this->fileExtensions = ['.xntt'];
    $this->fileEntityData = [];

    $no_id_structure = $this->configuration['structure'] ?? '';
    $regex =
      '~\{'
      . static::SUBSTR_REGEXP
      . '\Q'
      . ($this->getSourceIdFieldName() ?? '*')
      . '\E\}~';
    $no_id_structure = preg_replace(
      $regex,
      '',
      $no_id_structure
    );
    $this->useIndexFile =
      !empty($this->configuration['performances']['index_file'])
      && str_contains($no_id_structure, '{');
    // Init file filter.
    $this->fileFilterRegex = $this->generateFileFilterRegex();
    // Check if id in file path.
    $this->idInPath = str_contains(
      $this->configuration['structure'],
      '{' . ($this->getSourceIdFieldName() ?? '') . '}'
    );
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
      $container->get('logger.factory')->get('external_entities'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('cache.default'),
      $container->get('event_dispatcher'),
      $container->get('stream_wrapper_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'root' => '',
      'structure' => '',
      'matching_only' => TRUE,
      'record_type' => static::RECORD_TYPE_MULTI,
      'performances' => [
        'use_index' => FALSE,
        'index_file' => '',
      ],
      'data_fields' => [
        'field_list' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    $this->idInPath = str_contains(
      $this->configuration['structure'],
      '{' . ($this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD) . '}'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    // Get id provided by parent form. This id remains between form generations.
    $sc_id = ($form['#attributes']['id'] ??= uniqid('sc', TRUE));

    $form = NestedArray::mergeDeep(
      $form,
      [
        'root' => [
          '#type' => 'textfield',
          '#title' => $this->t(
            'Directory where the @file_type files are stored (base path)',
            ['@file_type' => $this->fileType]
          ),
          '#description' => $this->t(
            'Path to the main directory where @file_type files are stored (or sub-directories containing files when a pattern that includes sub-directories is used). You should include file scheme (ie. "public://") but trailing slash does not matter. Note: absolute/relative path are ignored and mapped to "@scheme://" file scheme (ie. Drupal site\'s "files" directory or its default file scheme path).',
            [
              '@file_type' => $this->fileType,
              '@scheme' => $this
                ->configFactory
                ->get('system.file')
                ->get('default_scheme'),
            ]
          ),
          '#required' => TRUE,
          '#default_value' => $this->configuration['root'],
          '#attributes' => [
            'placeholder' => $this->t('ex.: public://data_path/'),
          ],
        ],
        'structure' => [
          '#type' => 'textfield',
          '#title' => $this->t(
            '@file_type file name or file name pattern',
            ['@file_type' => $this->fileTypeCap]
          ),
          '#description' => $this->t(
            'This field can be set to a single @file_type file name if you store all
            your external entities into that file or a file pattern to match
            multiple files. See help for details.',
            [
              '@file_type' => $this->fileType,
            ]
          ),
          '#required' => TRUE,
          '#default_value' => $this->configuration['structure'],
          '#attributes' => [
            'placeholder' => $this->t(
              'ex.: data_file@file_ext',
              ['@file_ext' => $this->fileExtensions[0]]
            ),
          ],
        ],
        'structure_help' => [
          '#type' => 'details',
          '#title' => $this->t('File name and pattern help'),
          '#open' => FALSE,
          'details' => [
            '#type' => 'theme',
            '#theme' => 'file_client_base_pattern_help',
            '#file_ext' => $this->fileExtensions[0] ?? '' ?: '.txt',
          ],
        ],
        // Restrict to files matching file name pattern.
        'matching_only' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Enforce file name pattern'),
          '#description' => $this->t(
            'Only allow files matching the file name pattern or present in the index file.'
          ),
          '#return_value' => TRUE,
          '#default_value' => $this->configuration['matching_only'] ?? TRUE,
        ],
        // How to handle records.
        'record_type' => [
          '#type' => 'select',
          '#title' => $this->t('How to process file data'),
          '#options' => [
            static::RECORD_TYPE_SINGLE => $this->t('A single file holds a single entity.'),
            static::RECORD_TYPE_MULTI => $this->t('A single file holds multiple entity records.'),
            static::RECORD_TYPE_FILE => $this->t('Just gather file statistics.'),
          ],
          '#default_value' => $this->configuration['record_type'] ?? static::RECORD_TYPE_MULTI,
          '#attributes' => [
            'data-xnttfile-selector' => 'rt' . $sc_id,
          ],
        ],
        'record_path' => [
          '#type' => 'textfield',
          '#title' => $this->t('JSONPath to entity records'),
          '#description' => $this->t('If the list of entity records is not in the root of the file data strcuture, provide that JSONPath to the list of records. Leave empty if not needed.'),
          '#default_value' => $this->configuration['record_path'] ?? NULL,
          '#states' => [
            'visible' => [
              'select[data-xnttfile-selector="rt' . $sc_id . '"]' => [
                ['value' => static::RECORD_TYPE_MULTI],
              ],
            ],
          ],
        ],
        // Index file.
        'performances' => [
          '#type' => 'details',
          '#title' => $this->t('Performances'),
          '#open' => !empty($this->configuration['performances']['index_file']),
          'index_file' => [
            '#type' => 'textfield',
            '#title' => $this->t('Index file'),
            '#description' => $this->t(
              'Path to the file used to index entities and their corresponding
              @file_type files. You can leave this field empty unless you use a
              file name pattern involving other fields than the entity
              identifier, or if you want to map a specific set of entities to
              other files than the default one (provided by the file name or
              name pattern). This file may be updated by this plugin when a
              non-indexed entity is saved and needs to be indexed (advanced file
              patterns). Already indexed entities (ie. the ones already present
              in this index file) will remain unchanged. Each line contains a
              entity identifier followed by a tabulation and the path to its
              associated file. WARNING: it is recommanded to store this file in
              a private scheme and ensure it can not be altered by unwanted
              elements.',
              ['@file_type' => $this->fileType]
            ),
            '#default_value' => $this->configuration['performances']['index_file'],
          ],
          'use_index' => [
            '#type' => 'checkbox',
            '#title' => $this->t(
              'Only use index file to list files (no directory listing)'
            ),
            '#return_value' => TRUE,
            '#default_value' => $this->configuration['performances']['use_index'] ?? FALSE,
          ],
          // Regenerate index button.
          'generate_index' => [
            '#type' => 'submit',
            '#value' => $this->t('Regenerate entity-file index file'),
            '#description' => $this->t(
              'Clear previous entries (even manual) and regenerate a complete index file by listing and parsing valid data files.'
            ),
            // We need to use a specific name because this button may appear
            // more than once in current form (when multiple storage clients).
            // If we don't use a specific name, Drupal won't be able to identify
            // the triggering element and will fallback to the first submit
            // button.
            '#name' => 'rgn_' . $sc_id,
            '#validate' => [
              [
                $this,
                'validateIndexFileForm',
              ],
            ],
            '#submit' => [
              [
                $this,
                'submitRegenerateIndexFileForm',
              ],
            ],
          ],
          // Regenerate index button.
          'update_index' => [
            '#type' => 'submit',
            '#value' => $this->t('Update entity-file index file'),
            '#description' => $this->t(
              'Adds missing entries to the index file, remove entries with unexisting files and leave other entries unchanged.'
            ),
            // We need to use a specific name because this button may appear
            // more than once in current form (when multiple storage clients).
            // If we don't use a specific name, Drupal won't be able to identify
            // the triggering element and will fallback to the first submit
            // button.
            '#name' => 'upd_' . $sc_id,
            '#validate' => [
              [
                $this,
                'validateIndexFileForm',
              ],
            ],
            '#submit' => [
              [
                $this,
                'submitUpdateIndexFileForm',
              ],
            ],
          ],
        ],
        'data_fields' => [
          '#type' => 'details',
          '#title' => $this->t('Fields'),
          '#open' => FALSE,
          // User field list.
          'field_list' => [
            '#type' => 'textarea',
            '#title' => $this->t(
              'List of entity field names to store in @file_type files',
              ['@file_type' => $this->fileType]
            ),
            '#description' => $this->t(
              'Leave this field empty if you want to save all the entity fields in new
              @file_type files. Otherwise, you can limit the fields saved into new
              @file_type files as well as set their order using this field. Just
              specify the field names separated by new lines, spaces, comas or
              semi-columns.',
              ['@file_type' => $this->fileType]
            ),
            '#default_value' => implode(
              ';',
              (array) $this->configuration['data_fields']['field_list'] ?? []
            ),
          ],
        ],
      ]
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $default_scheme = $this
      ->configFactory
      ->get('system.file')
      ->get('default_scheme')
      . '://';

    // Check root directory.
    $root = $form_state->getValue('root', '');
    $root = rtrim(ltrim($root, static::PATH_TRIM), static::PATH_TRIM);
    // Adds ending slashes after scheme if removed above or missing.
    $root = preg_replace('#:$#', '://', $root);
    // Check file scheme.
    if (0 === preg_match('#^\w+://#', $root)) {
      $root = $default_scheme . $root;
    }
    if (!file_exists($root) || !is_dir($root)) {
      $this->messenger->addWarning(
        $this->t(
          'The bath path directory "@root" is not accessible. File listing will not be possible.',
          ['@root' => $root]
        )
      );
      $this->logger->warning(
        'The bath path directory "@root" is not accessible. File listing will not be possible.',
        ['@root' => $root]
      );
    }
    $form_state->setValue('root', $root);

    // Validate file pattern (structure).
    $structure = $form_state->getValue('structure', '');
    $structure = rtrim(ltrim($structure, static::PATH_TRIM), static::PATH_TRIM);
    $using_pattern = FALSE;
    if (empty($structure)) {
      // No structure specified, use a default file name.
      // Note: file storage clients working on file content will use a file
      // extension while file storage clients working on file themselves will
      // not and do not require a file name pattern to be set here. It will be
      // set by default to '{id}.{ext}' by the 'Files' file storage client.
      $entity_type = $this->externalEntityType
        ? ($this->externalEntityType->getDerivedEntityTypeId() ?? 'data') :
        'data';
      $structure = 'xntt-' . preg_replace('#[^\w\-]+#', '', $entity_type);
      if (!empty($this->fileExtensions[0])) {
        $structure .= ($this->fileExtensions[0] ?? '');
      }
    }
    elseif (str_contains($structure, '{')) {
      $using_pattern = TRUE;
    }

    if ($using_pattern) {
      // Check if a pattern is used and is correct.
      $no_pattern_structure = preg_replace(
        '~\{' . static::SUBSTR_REGEXP . '\w+' . static::FIELDRE_REGEXP . '\}~',
        '',
        $structure
      );
      if (str_contains($no_pattern_structure, '{')) {
        $form_state->setErrorByName(
          'structure',
          $this->t(
            'The file name pattern "@structure" is not valid.',
            ['@structure' => $structure]
          )
        );
      }

      // Check filter pattern.
      $struct_re = $this->generateFileFilterRegex($structure);
      if (FALSE === preg_match($struct_re, '')) {
        $form_state->setErrorByName(
          'structure',
          $this->t(
            'At least one regular expression used in the file name pattern "@pattern" is not valid. Generated regex: "@pattern_re". Error message: @preg_message',
            [
              '@pattern' => $structure,
              '@pattern_re' => $struct_re,
              '@preg_message' => preg_last_error_msg(),
            ]
          )
        );
      }
      else {
        $this->fileFilterRegex = $struct_re;
      }
    }
    $form_state->setValue('structure', $structure);

    // File name pattern restriction.
    $matching_only = $form_state->getValue('matching_only', FALSE);
    $form_state->setValue('matching_only', $matching_only);

    // Type of records by file.
    $record_type = $form_state->getValue('record_type', static::RECORD_TYPE_MULTI);
    $form_state->setValue('record_type', $record_type);

    // Index file.
    $use_index = $form_state->getValue(['performances', 'use_index'], FALSE);
    $form_state->setValue(['performances', 'use_index'], $use_index);

    $index_file = $form_state->getValue(['performances', 'index_file'], '');
    $index_file = rtrim(
      ltrim($index_file, static::PATH_TRIM),
      static::PATH_TRIM
    );
    if (!empty($index_file)) {
      // Check file scheme.
      if (0 === preg_match('#^\w+://#', $index_file, $scheme_match)) {
        $index_file = $default_scheme . $index_file;
        $scheme_match[0] = $default_scheme;
      }
      if (!$form_state->isRebuilding()) {
        if (!file_exists($index_file)) {
          // Tries to create the file.
          if (touch($index_file)) {
            $this->messenger->addMessage(
              'The selected index file "'
              . $index_file
              . '" did not exist and was created.'
            );
          }
          $this->logger->info('Created missing index file "' . $index_file . '".');
        }
        // Check for public scheme.
        if ('public://' == $scheme_match[0]) {
          $this->messenger->addWarning(
            'The selected index file "'
            . $index_file
            . '" is in the public file scheme. For security reasons, it is recommended to use a private scheme instead. Be aware that a public index file allows any user to view the list of indexed files and users that can alter that file (upload) may gain access to private files (ie. private:// scheme) as well.'
          );
        }
      }
      if (!file_exists($index_file) || !is_file($index_file)) {
        $form_state->setErrorByName(
          'index_file',
          $this->t(
            'File not accessible "@index_file".',
            ['@index_file' => $index_file]
          )
        );
      }
    }
    $form_state->setValue(['performances', 'index_file'], $index_file);

    // Field list.
    $field_list = trim($form_state->getValue(['data_fields', 'field_list'], ''));
    $has_id_field = preg_match(
      '#(?:^|[\n ,;])\Q' . ($this->getSourceIdFieldName() ?? '*') . '\E(?:[\n ,;]|$)#',
      $field_list
    );
    if (!empty($field_list)) {
      if (!$has_id_field) {
        $form_state->setErrorByName(
          'field_list',
          $this->t('Field list must contain the entity identifier field.')
        );
      }
      $field_list = preg_split('#[\n ,;]+#', $field_list);
    }
    else {
      $field_list = [];
    }
    $form_state->setValue(['data_fields', 'field_list'], $field_list);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $form_state->unsetValue(['performances', 'generate_index']);
    $form_state->unsetValue(['performances', 'update_index']);
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * Validate handler for regenerate and update index file buttons.
   */
  public function validateIndexFileForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // We are validating parent form which contains more than this plugin form
    // knows about. We need to extract our sub-form part to work.
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#array_parents'], 0, -2);

    // Now get config elements.
    $root = $form_state->getValue(array_merge($parents, ['root']), '');
    $structure = $form_state->getValue(
      array_merge($parents, ['structure']),
      ''
    );
    $index_file = $form_state->getValue(
      array_merge($parents, ['performances', 'index_file']),
      ''
    );
    $parent_string = implode('][', $parents) . '][';
    if (empty($index_file)) {
      // Indexing requires an index file.
      $form_state->setErrorByName(
        $parent_string . 'performances][index_file',
        $this->t('You must specify an index file before re-indexing.')
      );
    }
    elseif ($this->configuration['performances']['index_file'] != $index_file) {
      // Warn config changed without saving.
      $form_state->setErrorByName(
        $parent_string . 'performances][index_file',
        $this->t('You must save configuration changes before re-indexing.')
      );
    }
    if ($this->configuration['root'] != $root) {
      // Warn config changed without saving.
      $form_state->setErrorByName(
        $parent_string . 'root',
        $this->t('You must save configuration changes before re-indexing.')
      );
    }
    if ($this->configuration['structure'] != $structure) {
      // Warn config changed without saving.
      $form_state->setErrorByName(
        $parent_string . 'structure',
        $this->t('You must save configuration changes before re-indexing.')
      );
    }
  }

  /**
   * Submission handler for regenerate index file button.
   */
  public function submitRegenerateIndexFileForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $this->launchIndexFileRegenerationBatch(
      $form_state,
      'regenerate',
      'Regenerating entity-file index file.'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitUpdateIndexFileForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $this->launchIndexFileRegenerationBatch(
      $form_state,
      'update',
      'Updating entity-file index file... Loading current index.'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function launchIndexFileRegenerationBatch(
    FormStateInterface $form_state,
    string $mode,
    string $title,
  ) :void {
    // Make sure we work on an existing xntt type.
    if (empty($this->externalEntityType)) {
      return;
    }

    // Prepare required info to launch batch.
    $plugin_id = $this->getPluginId();
    $config = $this->getConfiguration();
    $regex = $this->generateFileFilterRegex();
    $params = [
      'plugin'     => $plugin_id,
      'config'     => $config,
      'regex'      => $regex,
      'xntt'       => $this->externalEntityType->id(),
    ];
    $operations = [
      [
        'external_entities_regenerate_index_file_process',
        [
          // Params.
          $params,
          $mode,
        ],
      ],
    ];
    $batch = [
      'title' => $title,
      'operations' => $operations,
      'finished' => 'external_entities_regenerate_index_file_finished',
      'progressive' => TRUE,
    ];
    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) {
    // Do nothing for info on files.
    if (static::RECORD_TYPE_FILE == $this->configuration['record_type']) {
      return;
    }

    $entity_data = $entity->toRawData();
    // Get entity file path.
    if (empty($entity_file_path = $this->getEntityFilePath($entity_data))) {
      // Not found.
      return;
    }

    // Single file?
    if (static::RECORD_TYPE_SINGLE == $this->configuration['record_type']) {
      // Remove file.
      if ($success = unlink($entity_file_path)) {
        unset($this->fileEntityData[$entity_file_path][$entity->id()]);
      }
    }
    elseif (static::RECORD_TYPE_MULTI == $this->configuration['record_type']) {
      // Get entities in file.
      $entities = &$this->getFileEntityData($entity_file_path);
      if (!empty($entities)) {
        $entity_id = $entity_data[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD];
        $initial_count = count($entities);
        // Remove the requested entity.
        unset($entities[$entity_id]);
        // Check if we removed something.
        if (count($entities) == ($initial_count - 1)) {
          $success = TRUE;
          try {
            $this->saveFile($entity_file_path, $entities);
            if ($this->useIndexFile) {
              $this->removeEntityFileIndex($entity_id, TRUE);
            }
          }
          catch (FilesExternalEntityException $e) {
            $success = FALSE;
          }
        }
      }
    }

    if (empty($success)) {
      $this->messenger->addError(
        'Failed to delete entity ' . $entity->id()
      );
      $this->logger->error(
        'Failed to delete entity '
        . $entity->id()
        . ' in file "'
        . $entity_file_path
        . '"'
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) :array {
    $data = [];
    // @todo Handle when $ids is NULL to load all entities.
    // Load each entity.
    if (is_array($ids)) {
      foreach ($ids as $id) {
        $loaded = $this->load($id);
        if (isset($loaded)) {
          $data[$id] = $loaded;
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string|int $id) :array|null {
    $entity_data = [($this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD) => $id];
    if ($this->getDebugLevel()) {
      $this->logger->debug(
        "FileClientBase::load():\nid:\n@id\nentity data:\n@entity_data",
        [
          '@id' => $id,
          '@entity_data' => print_r($entity_data, TRUE),
        ]
      );
    }

    // Get entity path.
    if (empty($entity_file_path = $this->getEntityFilePath($entity_data))) {
      // Not found.
      if ($this->getDebugLevel()) {
        $this->logger->debug(
          "FileClientBase::load(): no path found"
        );
      }
      return NULL;
    }

    if ($this->getDebugLevel()) {
      $this->logger->debug(
        "FileClientBase::load():\npath: @path",
        [
          '@path' => $entity_file_path,
        ]
      );
    }

    // Get entity data.
    $file_entity_data = $this->getFileEntityData($entity_file_path);
    $entity = $file_entity_data[$id] ?? [];

    if (empty($entity)) {
      // Check for an alternate id specified in index file.
      $alternate_id = $this->filePathToId($entity_file_path);
      $entity = $file_entity_data[$alternate_id] ?? [];
    }

    if (empty($entity)) {
      $entity = NULL;
    }
    if ($this->getDebugLevel()) {
      $this->logger->debug(
        "FileClientBase::load():\nloaded data: @entity",
        [
          '@entity' => print_r($entity, TRUE),
        ]
      );
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) :int {
    // Do nothing for info on files.
    if (static::RECORD_TYPE_FILE == $this->configuration['record_type']) {
      return 0;
    }

    $entity_data = $entity->toRawData();
    // Get entity path.
    if (empty($entity_file_path = $this->getEntityFilePath($entity_data))) {
      // No file, stop here.
      return 0;
    }
    // Get entities in file.
    $entities = &$this->getFileEntityData($entity_file_path);
    // Check if file was empty.
    if (empty($entities) || !array_key_exists('', $entities)) {
      // Add columns.
      if ($fields = $this->configuration['data_fields']['field_list']) {
        $entities[''] = $fields;
        $this->logger->info(
          '"'
          . $entity_file_path
          . '" file did not exist and was created using configuration columns.'
        );
      }
      else {
        // Save all available fields.
        $entities[''] = array_keys($entity_data);
        $this->logger->info(
          '"'
          . $entity_file_path
          . '" file did not exist and was created using entity "'
          . $entity_data[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD]
          . '" columns ('
          . ($this->externalEntityType
            ? $this->externalEntityType->getDerivedEntityTypeId() . ' data type'
            : 'data type not set')
          . ').'
        );
      }
    }

    try {
      // Check if ID exists in base.
      $idf = $this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD;
      if (isset($entity_data[$idf]) && ('' != $entity_data[$idf])) {
        // Update existing.
        $entity_id = $entity_data[$idf];
        $entities[$entity_id] = $entity_data;
        $this->saveFile($entity_file_path, $entities);
        $result = SAVED_UPDATED;
      }
      else {
        // Save new.
        // Create a new id.
        $all_entity_ids = array_keys($this->entityFileIndex + $entities);
        $entity_data[$idf] = max(array_keys($all_entity_ids)) ?: 0;
        ++$entity_data[$idf];
        $this->messenger->addWarning(
          $this->t(
            'A new external entity identifier "@id" had to be generated. Be aware that it may conflict with existing entities not stored in the same file and not registered in the entity-file index file.',
            ['@id' => $entity_data[$idf]]
          )
        );
        $this->logger->warning(
          'New external entity identifier generated for type "'
          . $this->externalEntityType->getDerivedEntityTypeId()
          . '": "'
          . $entity_data[$idf]
          . '"'
        );
        $entity_id = $entity_data[$idf];
        $entities[$entity_id] = $entity_data;
        $this->saveFile($entity_file_path, $entities);
        $result = SAVED_NEW;
      }
      // Record file in index if needed.
      if (($this->useIndexFile) && (!$this->isEntityIndexed($entity_id))) {
        $this->appendEntityFileIndex($entity_id, $entity_file_path, TRUE);
      }
    }
    catch (FilesExternalEntityException $e) {
      $this->logger->warning(
        'Failed to save "'
        . $entity_file_path
        . '" for external entity type "'
        . $this->externalEntityType->getDerivedEntityTypeId()
        . "\":\n"
        . $e
      );
      $result = 0;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array {
    $unhandled_filters['start'] = $unhandled_filters['length'] = TRUE;
    return parent::query($parameters, $sorts, $start, $length, $unhandled_filters);
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL,
  ) :array {
    // @todo Add caching as this process can be long if not using an index file?
    // Get list of files.
    $files = $this->getAllEntitiesFilePath($this->configuration['performances']['use_index'], $parameters);
    // Default sort to always get files in the same order.
    sort($files);
    $all_entities = [];
    foreach ($files as $file) {
      // Load file data.
      $file_entities = $this->loadFile($file);
      $all_entities = array_merge($all_entities, $file_entities);
    }
    // Sorting and paging will be managed by StorageClientBase.
    return $all_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ) :array {
    // We don't have means to filter files from source as OS don't provide
    // native filters when listing files. All filtration needs to be performed
    // on loaded entities.
    $source_filters = [];
    $drupal_filters = [];
    foreach ($parameters as $parameter) {
      if (!isset($parameter['field'])
        || !isset($parameter['value'])
        || !is_string($parameter['field'])
        || (FALSE !== strpos($parameter['field'], '.'))
      ) {
        $drupal_filters[] = $parameter;
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug("Complex filter passed to Drupal-side filtering");
        }
        continue;
      }
      // Get field mapped on source.
      $field_mapper = $this->externalEntityType->getFieldMapper($parameter['field']);
      if ($field_mapper) {
        $source_field = $field_mapper->getMappedSourceFieldName();
      }
      if (!isset($source_field)) {
        $drupal_filters[] = $parameter;
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "No reversible mapping for Drupal field '@field' passed to Drupal-side filtering",
            [
              '@field' => $parameter['field'],
            ]
          );
        }
        continue;
      }
      // Check if we can process that field here (ie. on the source side).
      $supported_operators = [
        '=',
        '<>',
        '!=',
        'STARTS_WITH',
        'CONTAINS',
        'ENDS_WITH',
        'IN',
        'NOT IN',
      ];
      if (in_array($source_field, $this->supportedFields)
        && (in_array($parameter['operator'] ?? '=', $supported_operators))
      ) {
        $parameter['field'] = $source_field;
        $source_filters[] = $parameter;
      }
      else {
        $drupal_filters[] = $parameter;
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug("Unsupported filter ('" . $parameter['operator'] . "' on '" . $source_field . "') passed to Drupal-side filtering");
        }
      }
    }
    return $this->transliterateDrupalFiltersAlter(
      ['source' => $source_filters, 'drupal' => $drupal_filters],
      $parameters,
      $context
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalSorts(
    array $sorts,
    array $context = [],
  ) :array {
    // Move all sorting on the Drupal side.
    $trans_sorts = [
      'drupal' => $sorts,
      'source' => [],
      'unhandled' => [],
    ];

    return $this->transliterateDrupalSortsAlter(
      $trans_sorts,
      $sorts,
      $context,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function filePathToId(string $file_path) :string {
    // Remove base directory.
    $base_path = $this->configuration['root'] ?? '://';
    $file_path = preg_replace('#^\Q' . $base_path . '\E/?#', '', $file_path);
    if ($this->idInPath) {
      $values = $this->getValuesFromPath($file_path);
      $id =
        $values[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD]
        ?? str_replace('/', '\\', $file_path);
    }
    else {
      $id = str_replace('/', '\\', $file_path);
    }
    return $id;
  }

  /**
   * {@inheritdoc}
   */
  public function idToFilePath(string $id) :string {
    $file_path = str_replace('\\', '/', $id);
    if ($this->idInPath) {
      $file_path = str_replace(
        '{' . ($this->getSourceIdFieldName() ?? '') . '}',
        $id,
        $file_path
      );
    }
    return $file_path;
  }

  /**
   * {@inheritdoc}
   */
  public function generateFileFilterRegex(?string $structure = NULL) :string {
    $struct_re = $structure ?? $this->configuration['structure'] ?? '';
    // First, replace placeholders with user-provided regex.
    $pattern_re =
      '~\{' . static::SUBSTR_REGEXP . '(\w+)' . static::FIELDRE_REGEXP . '\}~';
    $struct_re = preg_replace_callback(
      $pattern_re,
      function ($matches) {
        // Get filter pattern to use. If none specified, allow word characters
        // and dash.
        $re = $matches[4] ?? '[\w\-]+';
        // If using a substring, just use filter pattern.
        if ('' != $matches[1]) {
          return '\E' . $re . '\Q';
        }
        else {
          // Full field string, use named capture filter pattern.
          return '\E(?P<' . $matches[3] . '>' . $re . ')\Q';
        }
      },
      $struct_re
    );
    // Then remove duplicate captures.
    preg_match_all('#\?P<\w+>#', $struct_re, $matches);
    foreach (array_unique($matches[0]) as $named_capture) {
      $named_capture_pos = strpos($struct_re, $named_capture) + strlen($named_capture);
      $struct_re =
        substr($struct_re, 0, $named_capture_pos)
        . str_replace(
            $named_capture,
            '',
            substr($struct_re, $named_capture_pos)
        );
    }
    // Finish regex and cleanup useless '\Q\E'.
    $struct_re = preg_replace('#\\\\Q\\\\E#', '', '#^\Q' . $struct_re . '\E$#');

    return $struct_re;
  }

  /**
   * {@inheritdoc}
   */
  public function getValuesFromPath(string $file_path) :array {
    $values = [];
    $path = $this->configuration['root'];
    $structure = $this->configuration['structure'];
    if (FALSE !== strpos($structure, '{')) {
      // Remove root from file path to match (if present).
      $file_path_to_match = preg_replace('#^\Q' . $path . '\E/?#', '', $file_path);
      if (FALSE === preg_match($this->fileFilterRegex, $file_path_to_match, $values)) {
        $this->messenger->addError(
          $this->t(
            'Failed to extract field values from given pattern. Please check external entity file name pattern. @preg_message',
            [
              '@preg_message' => preg_last_error_msg(),
            ]
          )
        );
        $this->logger->error(
          'Failed to extract field values in "'
          . $file_path
          . '" from given file name pattern "'
          . $structure
          . '" using generated regex "'
          . $this->fileFilterRegex
          . '": '
          . preg_last_error_msg()
        );
      }
      else {
        // Got values, remove duplicates keyed by integers.
        $values = array_filter(
          $values,
          function ($k) {
            return !is_int($k);
          },
          ARRAY_FILTER_USE_KEY
        );

        // Set a default id using file path with backslash if missing.
        if (empty($values[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD])) {
          $values[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD] =
            str_replace('/', '\\', $file_path_to_match);
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFileIndex(bool $reload = FALSE) :array {
    if (empty($this->entityFileIndex) || $reload) {
      $entity_file_index = [];
      if (!empty($index_file = $this->configuration['performances']['index_file'])
          && !empty($this->externalEntityType)
      ) {
        // Nothing from cache, check for an index file.
        if (!empty($raw_lines = file($index_file))) {
          $line_number = 1;
          foreach ($raw_lines as $raw_line) {
            $values = explode("\t", trim($raw_line));
            if (2 == count($values)) {
              // If no scheme is specified, add the config one by default.
              if (0 === preg_match('#^\w+://#', $values[1])) {
                $values[1] =
                  $this->configuration['root']
                  . ('/' == $this->configuration['root'][-1] ? '' : '/')
                  . $values[1];
              }
              // @todo Security concern: document that if the index file can be
              // modified externally, then any files in the available Drupal
              // schemes (including private://) could be accessed as one could
              // associate an entity identifier to an arbitrary path.
              // However, this behavior is expected as an admin may want to
              // expose manually certain private files for instance. The
              // important thing to recall is that the index file should be
              // protected against modification by external elements (file
              // upload replacement for instance).
              $entity_file_index[$values[0]] = $values[1];
            }
            elseif (FALSE === preg_match('!^\s*(?:(?:/|;|#).*)$!', $raw_line)) {
              // Not a comment or an empty line, warn for a file format error.
              $this->logger->warning(
                'Invalid line format in index file "'
                . $index_file
                . '" at line '
                . $line_number
                . ': "'
                . $raw_line
                . '"'
              );
            }
            ++$line_number;
          }
        }
        if ($this->getDebugLevel()) {
          $this->logger->debug(
            "FileClientBase::getEntityFileIndex(): got @count files listed in index",
            [
              '@count' => count($entity_file_index),
            ]
          );
        }
      }
      else {
        if ($this->getDebugLevel()) {
          $this->logger->debug(
            "FileClientBase::getEntityFileIndex(): not using an index file"
          );
        }
      }
      $this->entityFileIndex = $entity_file_index;
    }

    return $this->entityFileIndex;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityFileIndex(array $entity_file_index, $save = FALSE) {
    $this->entityFileIndex = $entity_file_index;
    if ($save && !empty($index_file = $this->configuration['performances']['index_file'])) {
      if ($fh = fopen($index_file, 'w')) {
        $index_data = array_reduce(
          array_keys($entity_file_index),
          function ($data, $id) use ($entity_file_index) {
            $file_path = preg_replace(
              '#^(?:\Q' . $this->configuration['root'] . '\E)?/*#',
              '',
              $entity_file_index[$id]
            );
            return $data . $id . "\t" . $file_path . "\n";
          },
          ''
        );
        $index_data = preg_replace('#^\s+#', '', $index_data);
        fwrite($fh, $index_data);
        fclose($fh);
      }
      else {
        $this->logger->warning(
          'Failed to save entity-file index file "'
          . $index_file
          . '".'
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityIndexed(string $entity_id) :bool {
    return ($entity_file_index = $this->getEntityFileIndex())
      && !empty($entity_file_index[$entity_id]);
  }

  /**
   * Removes an entity identifier from the entity-file index.
   *
   * @param string $entity_id
   *   Entity identifier to remove from the index file.
   * @param bool $save
   *   If TRUE, saves the update into the index file otherwise, the file is left
   *   unchanged and just current class instance will use the changes.
   */
  protected function removeEntityFileIndex(string $entity_id, $save = FALSE) {
    unset($this->entityFileIndex[$entity_id]);
    if ($save && !empty($index_file = $this->configuration['performances']['index_file'])) {
      $index_data = file_get_contents($index_file);
      $index_data = preg_replace(
        '#^\Q' . $entity_id . '\E\t[^\n]*(?:\n|$)#m',
        '',
        $index_data
      );
      if ($fh = fopen($index_file, 'w')) {
        fwrite($fh, $index_data);
        fclose($fh);
      }
      else {
        $this->logger->warning(
          'Failed to remove entity "'
          . $entity_id
          . '" from index file "'
          . $index_file
          . '".'
        );
      }
    }
  }

  /**
   * Append an entity identifier to the entity-file index file.
   *
   * @param string $entity_id
   *   Entity identifier to add to the index file.
   * @param string $path
   *   Corresponding entity file path.
   * @param bool $save
   *   If TRUE, saves the update into the index file otherwise, the file is left
   *   unchanged and just current class instance will use the changes.
   */
  protected function appendEntityFileIndex(
    string $entity_id,
    string $path,
    $save = FALSE,
  ) {
    $file_path = preg_replace(
      '#^(?:\Q' . $this->configuration['root'] . '\E)?/*#',
      '',
      $path
    );
    if (0 === preg_match('#^\w+://#', $path)) {
      $path = $this->configuration['root'] . '/' . $path;
    }
    $this->entityFileIndex[$entity_id] = $path;
    if ($save && !empty($index_file = $this->configuration['performances']['index_file'])) {
      if ($fh = fopen($index_file, 'a+')) {
        // Check if last character is an EOL.
        $stat = fstat($fh);
        if ($stat['size'] > 1) {
          fseek($fh, $stat['size'] - 1);
          if ("\n" != fgetc($fh)) {
            // Nope, add one.
            fwrite($fh, "\n");
          }
        }
        fwrite($fh, $entity_id . "\t" . $file_path . "\n");
        fclose($fh);
      }
      else {
        $this->logger->warning(
          'Failed to add entity "'
          . $entity_id
          . '" to index file "'
          . $index_file
          . '".'
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAllEntitiesFilePath(
    bool $only_from_index = FALSE,
    array $parameters = [],
  ) :array {
    $files_path = [];

    // Make sure indexed entity records are loaded first.
    if ($entity_file_index = $this->getEntityFileIndex()) {
      $files_path = array_unique(array_values($entity_file_index));
    }

    if (!$only_from_index) {
      $path = $this->configuration['root'];
      $structure = $this->configuration['structure'];
      if (empty($structure)) {
        $this->messenger->addError(
          'No file name or file name pattern specified in the config. Please check entity type settings.'
        );
        return [];
      }
      // Check if the user specified a pattern rather than a regular file.
      if (str_contains($structure, '{')) {
        $logger = FALSE;
        if (2 <= $this->getDebugLevel()) {
          $logger = $this->logger;
        }
        $file_filter_regex = $this->fileFilterRegex;
        // There is a pattern, get all available files matching the filter.
        $root_length = strlen($path) + 1;
        // @todo Take into account $parameters here for better performances.
        $scan_for_files = function (string $dir, bool $report = FALSE) use (&$scan_for_files, $root_length, $file_filter_regex, $logger) {
          $matching_files = [];
          $not_readable = [];
          if (is_readable($dir)) {
            $files = scandir($dir, SCANDIR_SORT_NONE);
            if ($files) {
              foreach ($files as $file) {
                $file_path = "$dir/$file";
                // Remove leading root path.
                $path_to_filter = substr($file_path, $root_length);
                if (is_file($file_path)
                    && (preg_match($file_filter_regex, $path_to_filter))
                ) {
                  $matching_files[$file_path] = TRUE;
                }
                elseif (is_dir($file_path) && !str_starts_with($file, '.')) {
                  $matching_files += $scan_for_files($file_path);
                }
              }
            }
            if ($report && $logger) {
              $logger->debug(
                "FileClientBase::getAllEntitiesFilePath():\nprocessed path @path\nfiles found: @count\nfilter: @filter\nmatching files: @matching",
                [
                  '@path' => $dir,
                  '@count' => count($files),
                  '@filter' => $file_filter_regex,
                  '@matching' => count($matching_files),
                ]
              );
            }
          }
          else {
            $not_readable[] = $dir;
          }
          if ($report && $logger && !empty($not_readable)) {
            $logger->debug(
              "FileClientBase::getAllEntitiesFilePath():\nFollowing paths from @path were not readable: @not_readable",
              [
                '@path' => $dir,
                '@not_readable' => implode(', ', $not_readable),
              ]
            );
          }
          return $matching_files;
        };
        if ($this->getDebugLevel()) {
          $this->logger->debug(
            "FileClientBase::getAllEntitiesFilePath(): file pattern with fields. Starting from path @path",
            [
              '@path' => $path,
            ]
          );
        }
        // Get file path from directory search.
        $files_path = array_unique(
          array_merge(
            array_keys($scan_for_files($path, TRUE)),
            $files_path
          )
        );
      }
      else {
        // Regular (single) file.
        $files_path[] = $path . ('/' == $path[-1] ? '' : '/') . $structure;
        $files_path = array_unique($files_path);
        if ($this->getDebugLevel()) {
          $this->logger->debug(
            "FileClientBase::getAllEntitiesFilePath(): single file pattern detected: @path",
            [
              '@path' => $path . ('/' == $path[-1] ? '' : '/') . $structure,
            ]
          );
        }
      }
      if ($this->getDebugLevel()) {
        $this->logger->debug(
          "FileClientBase::getAllEntitiesFilePath(): finally got @count files to process:\n@files",
          [
            '@count' => count($files_path),
            '@files' => implode(', ', $files_path),
          ]
        );
      }
    }

    // Process file list filtration.
    if (!empty($parameters)) {
      $files_path = $this->filterPath($files_path, $parameters);
    }
    return $files_path;
  }

  /**
   * Filter a list of file paths based on parameters.
   *
   * This default implementation filters file paths based on basic fields:
   * - id: the entity identifier derived from the file path.
   * - path: the full file path.
   * - dirname: the directory name.
   * - basename: the base name.
   * - extension: the file extension.
   * - filename: the file name without extension.
   *
   * @param array $files_path
   *   The list of file paths to filter.
   * @param array $parameters
   *   The filtering parameters.
   *
   * @return array
   *   The filtered list of file paths.
   */
  public function filterPath(array $files_path, array $parameters = []) :array {
    // Get Path info for each file.
    $pathinfos = [];
    foreach ($files_path as $file_path) {
      $pathinfos[$file_path] = pathinfo($file_path);
    }
    foreach ($parameters as $parameter) {
      if (!in_array($parameter['field'], $this->supportedFields)) {
        // Ignore unsupported fields as they might be handled later
        // (ie. by inheritance for instance).
        continue;
      }
      if (empty($files_path)) {
        break;
      }
      $filtered_files_path = [];
      foreach ($files_path as $file_path) {
        if ('id' == $parameter['field']) {
          $value = $this->filePathToId($file_path);
        }
        elseif ('path' == $parameter['field']) {
          $value = $file_path;
        }
        else {
          $value = $pathinfos[$file_path][$parameter['field']] ?? '';
        }

        if (empty($parameter['operator']) || ('=' == $parameter['operator'])) {
          if ($value == $parameter['value']) {
            $filtered_files_path[] = $file_path;
          }
        }
        elseif (('<>' == $parameter['operator'])
          || ('!=' == $parameter['operator'])
        ) {
          if ($value != $parameter['value']) {
            $filtered_files_path[] = $file_path;
          }
        }
        elseif ('STARTS_WITH' == $parameter['operator']) {
          if (str_starts_with($value, $parameter['value'])) {
            $filtered_files_path[] = $file_path;
          }
        }
        elseif ('CONTAINS' == $parameter['operator']) {
          if (str_contains($value, $parameter['value'])) {
            $filtered_files_path[] = $file_path;
          }
        }
        elseif ('ENDS_WITH' == $parameter['operator']) {
          if (str_ends_with($value, $parameter['value'])) {
            $filtered_files_path[] = $file_path;
          }
        }
        elseif ('IN' == $parameter['operator']) {
          if (in_array($value, $parameter['value'])) {
            $filtered_files_path[] = $file_path;
          }
        }
        elseif ('NOT IN' == $parameter['operator']) {
          if (!in_array($value, $parameter['value'])) {
            $filtered_files_path[] = $file_path;
          }
        }
        else {
          // Warn unsupported operator.
          $this->logger->warning(
            'Unsupported operator "'
            . $parameter['operator']
            . '" for file path filtering on field "'
            . $parameter['field']
            . '".'
          );
          continue;
        }
      }
      $files_path = $filtered_files_path;
    }

    return $files_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFilePath($entity) :string {
    // Make sure we work on an array of values.
    if (is_object($entity)) {
      $entity = $entity->toRawData();
    }

    // Get data root directory.
    $root = $path = $this->configuration['root'];
    $id = $entity[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD];

    // Use a do-while structure trick to break and avoid many "if".
    do {
      // Check if we got an indexed file for this entity.
      if ($entity_file_index = $this->getEntityFileIndex()) {
        $entity_path = $entity_file_index[$id] ?? NULL;
        if (!empty($entity_path)) {
          // We got a specific file path for this entity, stop here.
          if (1 !== preg_match('#^\w+://#', $entity_path)) {
            // Missing file scheme, add it.
            $entity_path =
              $this->configuration['root']
              . ('/' == $this->configuration['root'][-1] ? '' : '/')
              . $entity_path;
          }
          $path = $entity_path;
          break;
        }
      }

      if ($this->configuration['performances']['use_index']) {
        // Failed to find entity file from the index.
        $this->logger->warning(
          'Entity "'
          . $id
          . '" not found in the index file. Please check your "entity-file index" file.'
        );
        $path = '';
        break;
      }

      // Get file name pattern.
      $structure = $this->configuration['structure'];
      if (empty($structure)) {
        $this->messenger->addError(
          $this->t(
            'No file name or file name pattern specified in the config. Please check entity type settings.'
          )
        );
        $this->logger->error(
          'No file name or file name pattern specified in the config. Please check entity type settings.'
        );
        $path = '';
        break;
      }
      // Check if the user specified a pattern rather than a regular file.
      if (str_contains($structure, '{')) {
        // Replace pattern placeholders by their field values.
        if (FALSE === preg_match_all(
              '~\{' . static::SUBSTR_REGEXP . '([\w\-]+)' . static::FIELDRE_REGEXP . '\}~',
              $structure,
              $matches,
              PREG_SET_ORDER
            )
        ) {
          // We got a problem.
          $this->messenger->addError(
            $this->t(
              'Invalid sub-directory pattern.'
            )
          );
          $this->logger->error(
            'Invalid sub-directory pattern. Message: ' . preg_last_error_msg()
          );
          $path = '';
          break;
        }
        $subdir = $structure;
        $unmatched_fields = [];
        foreach ($matches as $match) {
          $offset = $match[1];
          $length = $match[2];
          $field  = $match[3];
          // Check if we are missing an entity field value.
          if (!array_key_exists($field, $entity)) {
            $unmatched_fields[] = $field;
            break;
          }
          if ('' == $offset) {
            $offset = 0;
          }
          // Process substrings.
          if (empty($length)) {
            $pat_replacement = substr($entity[$field], $offset);
          }
          else {
            $pat_replacement = substr($entity[$field], $offset, $length);
          }
          $subdir = str_replace($match[0], $pat_replacement, $subdir);
        }
        // Check if we must use indexation because a field was missing.
        if (!empty($unmatched_fields)) {
          // If the identifier is not part of the file name pattern and it has
          // not been provided in the index file, it means the identifier is the
          // file path.
          if (!str_contains($structure, '{' . ($this->getSourceIdFieldName() ?? '*'))
              && ($file_path = $this->idToFilePath($id))
          ) {
            $path .= ('/' == $path[-1] ? '' : '/') . $file_path;
          }
          else {
            // File indexation did not index this entity. We can't guess the
            // file.
            if (!empty($index_file = $this->configuration['performances']['index_file'])) {
              $this->logger->warning(
                'Entity "'
                . $id
                . '" not present in index file ('
                . $index_file
                . ') while file name pattern uses unavailable fields: '
                . implode(', ', $unmatched_fields)
              );
              $this->messenger->addWarning(
                $this->t(
                  'Entity "@id" not present in index file while file name pattern uses unavailable fields.',
                  ['@id' => $id]
                )
              );
              $path = '';
              break;
            }
            else {
              // Unmatched fields in patterns and identifier was used.
              $this->logger->warning(
                'No index file set while using non-identifier fields in pattern: '
                . implode(', ', $unmatched_fields)
              );
              $this->messenger->addWarning(
                $this->t(
                  'No index file set while using non-identifier fields in pattern.'
                )
              );
              $path = '';
              break;
            }
          }
        }
        else {
          // All patterns matched.
          $path .= ('/' == $path[-1] ? '' : '/') . $subdir;
        }

        // Make sure the file path matches the file name pattern.
        if ($this->configuration['matching_only']) {
          $path_to_check = substr($path, strlen($root) + 1);
          if (!preg_match($this->fileFilterRegex, $path_to_check)) {
            $this->logger->warning(
              'The file path "'
              . $path
              . '" corresponding to the given entity '
              . $id
              . ' does not match the entity type file name pattern while file name pattern has been enforced in entity type configuration.'
            );
            $this->messenger->addWarning(
              $this->t(
                'Entity file path does not match the entity type file name pattern.'
              )
            );
            $path = '';
            break;
          }
        }
      }
      else {
        // Just a single main file to use.
        $path .= ('/' == $path[-1] ? '' : '/') . $structure;
      }
    } while (FALSE);
    if ($this->getDebugLevel()) {
      $this->logger->debug(
        "FileClientBase::getEntityFilePath(): entity @id path: @path",
        [
          '@id' => $id,
          '@path' => $path,
        ]
      );
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function loadFile(
    string $file_path,
    bool $with_column_names = FALSE,
    bool $reload = FALSE,
  ) :array {
    $entities = $this->getFileEntityData($file_path, $reload);
    if (!$with_column_names) {
      unset($entities['']);
    }
    return $entities;
  }

  /**
   * Load the given file or return corresponding class member data if available.
   *
   * @param string $file_path
   *   Full path to an existing file.
   * @param bool $reload
   *   If TRUE, reloads the cache if it exists.
   *
   * @return array
   *   Returns a reference to the corresponding class member containing the
   *   loaded entities keyed by identifiers.
   */
  protected function &getFileEntityData(string $file_path, bool $reload = FALSE)
  :array {
    if (!file_exists($file_path)) {
      // Log issue only once (by thread).
      static $logged_warnings = [];
      if (empty($logged_warnings[$file_path])) {
        $this->logger->warning(
          'Unable to load data: file "' . $file_path . '" does not exist.'
        );
        $logged_warnings[$file_path] = TRUE;
      }
      $this->fileEntityData[$file_path] = [];
    }
    elseif (empty($this->fileEntityData[$file_path]) || $reload) {
      $this->fileEntityData[$file_path] = $this->parseFile($file_path);
    }
    return $this->fileEntityData[$file_path];
  }

  /**
   * Parse file content according to the given format.
   *
   * The returned array must contain entities (array) keyed by identifier. An
   * additional special key, the empty string '', must be filled with an array
   * of entity field names.
   *
   * @param string $file_path
   *   Full path to an existing file.
   *
   * @return array
   *   An array of loaded entities keyed by identifiers. There is also the
   *   special key empty string '' used to store the array of column (field)
   *   names.
   */
  abstract protected function parseFile(string $file_path) :array;

  /**
   * Get file info.
   *
   * This method can be used to gather file information and statistics. It can
   * be overriden and called by a child class in order to aggregate more file
   * info; for instance EXIF and other image info for JPEG files, MP3
   * informations, video file format, length and resolution, etc.
   *
   * @param string $file_path
   *   Full path to an existing file.
   *
   * @return array
   *   An array of loaded info keyed by their names.
   */
  protected function getFileInfo(string $file_path) :array {
    $data = [];
    // Get file "identifier".
    $structure = $this->configuration['structure'];
    if (FALSE !== strpos($structure, '{')) {
      // Extract field values from file path.
      if ($entity = $this->getValuesFromPath($file_path)) {
        // Set a default id using file path if missing.
        $id = $entity[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD];
        $data[$id] = $entity;
      }
    }
    else {
      $id = $this->filePathToId($file_path);
      $data[$id] = [($this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD) => $id];
    }

    // Save current file name.
    $data[$id][static::ORIGINAL_PATH_FIELD] = $file_path;

    // Open file.
    $fh = fopen($file_path, "r");

    // Gather statistics (remove non-string keys).
    $data[$id] += array_filter(
      fstat($fh),
      function ($k) {
        return !is_int($k);
      },
      ARRAY_FILTER_USE_KEY
    );

    // Close the file.
    fclose($fh);

    // @todo maybe convert uid to names with corresponding settings?
    // Might be useless since files are created by the web server but might be
    // usefull when using private directories outside Drupal's path.
    // See posix_getpwuid().
    // Get other file info.
    $pathinfo = pathinfo($file_path);

    // Adds full file path.
    if (empty($data[$id]['path'])) {
      $data[$id]['path'] = $file_path;
    }

    // Adds file directory.
    if (empty($data[$id]['dirname'])) {
      $data[$id]['dirname'] = $pathinfo['dirname'];
    }

    // Adds full filename.
    if (empty($data[$id]['basename'])) {
      $data[$id]['basename'] = $pathinfo['basename'];
    }

    // Adds extension if not in pattern.
    if (empty($data[$id]['extension'])) {
      $data[$id]['extension'] = $pathinfo['extension'];
    }

    // Adds filename without extension.
    if (empty($data[$id]['filename'])) {
      $data[$id]['filename'] = $pathinfo['basename'];
    }

    // Adds public URL.
    if (empty($data[$id]['public_url'])) {
      if ($wrapper = $this->streamWrapperManager->getViaUri($file_path)) {
        $data[$id]['public_url'] = $wrapper->getExternalUrl();
      }
    }

    // Now add possible aliases from index file.
    if ($entity_file_index = $this->getEntityFileIndex()) {
      foreach ($entity_file_index as $alt_id => $alt_path) {
        if ($this->filePathToId($alt_path) == $id) {
          $data[$alt_id] = $data[$id];
          $data[$alt_id][$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD] = $alt_id;
        }
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function saveFile(string $file_path, array $entities_data) {
    // Make sure we got something valid to store.
    if (empty($entities_data[''])) {
      throw new FilesExternalEntityException(
        'Invalid entity data to save in file: no field names set.'
      );
    }

    // Check if directory exist.
    $stream_wrapper = $this->streamWrapperManager->getViaUri($file_path);
    $directory = $stream_wrapper->dirname($file_path);
    if (($directory != '.') && (!file_exists($directory))) {
      // Try to create directory structure.
      if (FALSE === mkdir($directory, 0777, TRUE)) {
        throw new FilesExternalEntityException(
          'Unable to create the specified directory where the file should be stored: '
          . $directory
        );
      }
    }

    // Prepare header and footer.
    $file_header = $this->generateFileHeader($file_path, $entities_data);
    $file_footer = $this->generateFileFooter($file_path, $entities_data);

    $fh = fopen($file_path, 'w');
    if (!$fh) {
      throw new FilesExternalEntityException(
        'Unable to write the specified file: '
        . $file_path
      );
    }
    elseif (!file_exists($file_path)) {
      throw new FilesExternalEntityException(
        'Unaccessible or inexisting file: '
        . $file_path
      );
    }

    // @todo use a safer approach to avoid issue if file writting fails:
    // - Create and fill a new file.
    // - Rename old file.
    // - Rename new file.
    // - Remove old file.
    $entities_raw_data = $this->generateRawData($entities_data);

    if ('' !== $file_header) {
      fwrite($fh, $file_header);
    }
    fwrite($fh, $entities_raw_data);
    if ('' !== $file_footer) {
      fwrite($fh, $file_footer);
    }

    // Update cache data member.
    $this->fileEntityData[$file_path] = $entities_data;
    fclose($fh);
  }

  /**
   * Generates a raw string of entity data ready to be written in a file.
   *
   * @param array $entities_data
   *   An array of entity data.
   *
   * @return string
   *   The raw string that can be written into an entity data file.
   */
  abstract protected function generateRawData(array $entities_data) :string;

  /**
   * Generates file header.
   *
   * @param string $file_path
   *   Path to the original file.
   * @param array $entities_data
   *   An array of entity data.
   *
   * @return string
   *   The file header.
   */
  protected function generateFileHeader(
    string $file_path,
    array $entities_data,
  ) :string {
    return '';
  }

  /**
   * Generates file footer.
   *
   * @param string $file_path
   *   Path to the original file.
   * @param array $entities_data
   *   An array of entity data.
   *
   * @return string
   *   The file header.
   */
  protected function generateFileFooter(
    string $file_path,
    array $entities_data,
  ) :string {
    return '';
  }

}
