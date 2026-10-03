<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Attribute\StorageClient;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities storage client for files.
 *
 * @StorageClient(
 *   id = "files",
 *   label = @Translation("Files"),
 *   description = @Translation("Use files as entities.")
 * )
 */
#[StorageClient(
  id: 'files',
  label: new TranslatableMarkup('Files'),
  description: new TranslatableMarkup('Use files as entities.'),
)]
class Files extends FileClientBase {

  /**
   * Default file identifier field name.
   */
  const DEFAULT_ID_FIELD = 'id';

  /**
   * Constants used for entity save mode.
   */
  const SAVE_NOTHING = 0;
  const SAVE_FILE_MODE_DATE = 1;
  const SAVE_FILE_NAME = 2;

  /**
   * {@inheritdoc}
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
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger,
      $entity_type_manager,
      $entity_field_manager,
      $token_service,
      $config_factory,
      $messenger,
      $cache,
      $event_dispatcher,
      $stream_wrapper_manager
    );

    // Defaults.
    $this->fileType = 'external';
    $this->fileTypePlural = 'external';
    $this->fileTypeCap = 'External';
    $this->fileTypeCapPlural = 'External';
    $this->fileExtensions = [''];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return parent::defaultConfiguration()
    + [
      'data_fields' => [
        'md5_checksum' => FALSE,
        'sha1_checksum' => FALSE,
      ],
      'save_mode' => static::SAVE_NOTHING,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildConfigurationForm($form, $form_state);
    // @todo Document that any field name could be put for the id field mapping
    //   as it will be used by the storage client as field name to store the id.
    // Adjust default File form.
    $form_override = [
      'root' => [],
      'structure' => [
        '#title' => $this->t(
          '@file_type file name pattern',
          ['@file_type' => $this->fileTypeCap]
        ),
        '#required' => FALSE,
      ],
      'structure_help' => [
        'details' => [],
      ],
      'matching_only' => [],
      // Use file as record.
      'record_type' => [
        '#type' => 'hidden',
        '#default_value' => static::RECORD_TYPE_FILE,
      ],
      'performances' => [
        'index_file' => [],
        'use_index' => [],
        'generate_index' => [],
        'update_index' => [],
      ],
      // Hide field list since it won't be used.
      'data_fields' => [
        'field_list' => [
          '#type' => 'hidden',
          '#default_value' => '',
        ],
        // Add checksum options.
        'md5_checksum' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Compute MD5 checksum'),
          '#description' => $this->t(
            'If checked, an MD5 checksum will be computed each time the entity is loaded. Be aware it can slowdown the system for large files.'
          ),
          '#return_value' => TRUE,
          '#default_value' => $this->configuration['data_fields']['md5_checksum'] ?? FALSE,
        ],
        'sha1_checksum' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Compute SHA1 checksum'),
          '#description' => $this->t(
            'If checked, a SHA1 checksum will be computed each time the entity is loaded. Be aware it can slowdown the system for large files.'
          ),
          '#return_value' => TRUE,
          '#default_value' => $this->configuration['data_fields']['sha1_checksum'] ?? FALSE,
        ],
      ],
      // Add save option radio.
      'save_mode' => [
        '#type' => 'radios',
        '#title' => $this->t('Save behavior:'),
        '#options' => [
          static::SAVE_NOTHING => $this->t('Do nothing'),
          static::SAVE_FILE_MODE_DATE => $this->t('Update file last modification date (create empty file if missing)'),
          static::SAVE_FILE_NAME => $this->t('Rename file according to file name pattern (if changed)'),
        ],
        '#default_value' => $this->configuration['save_mode'] ?? static::SAVE_NOTHING,
      ],
      // Add save option radio.
      'info' => [
        '#type' => 'details',
        '#title' => $this->t('Info'),
        '#open' => FALSE,
        'loaded_file_fields' => [
          '#type' => 'item',
          '#markup' => $this->t('<h4>Loaded file fields:</h4>
            <dl>
              <dt>@id*</dt>      <dd>entity identifier<br/>*: this field name is set by the name used to map the entity ID field, therefore, if you map the field name "bla" to this entity ID field, this field will be named "bla" automatically.</dd>
              <dt>path</dt>      <dd>full path to the file including file name</dd>
              <dt>dirname</dt>   <dd>path to the file (excluding file name and trailing slash)</dd>
              <dt>basename</dt>  <dd>file name (including extension)</dd>
              <dt>filename</dt>  <dd>file name (excluding extension)</dd>
              <dt>extension</dt> <dd>file extension</dd>
              <dt>dev</dt>       <dd>ID of device containing file</dd>
              <dt>ino</dt>       <dd>inode number</dd>
              <dt>mode</dt>      <dd>protection</dd>
              <dt>nlink</dt>     <dd>number of hard links</dd>
              <dt>uid</dt>       <dd>user ID of owner</dd>
              <dt>gid</dt>       <dd>group ID of owner</dd>
              <dt>rdev</dt>      <dd>device ID (if special file)</dd>
              <dt>size</dt>      <dd>total size, in bytes</dd>
              <dt>blksize</dt>   <dd>blocksize for file system I/O</dd>
              <dt>blocks</dt>    <dd>number of 512B blocks allocated</dd>
              <dt>atime</dt>     <dd>time of last access (timestamp)</dd>
              <dt>mtime</dt>     <dd>time of last modification (timestamp)</dd>
              <dt>ctime</dt>     <dd>time of last status change (timestamp)</dd>
              <dt>md5</dt>       <dd>MD5 checksum if option is set</dd>
              <dt>sha1</dt>      <dd>SHA1 checksum if option is set</dd>
              <dt>*</dt>         <dd>other fields as specified by the user file name pattern setting</dd>
            </dl>
          ',
          ['@id' => $this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD]
          ),
        ],
      ],
    ];

    // Merge forms.
    $form = $this->overrideForm(
      $form,
      $form_override,
      [
        '#type' => 'hidden',
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
    parent::validateConfigurationForm($form, $form_state);
    // Cleanup unnecessary values.
    $form_state->unsetValue('info');
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Get file pattern.
    $structure = $form_state->getValue('structure');
    $structure = rtrim(ltrim($structure, static::PATH_TRIM), static::PATH_TRIM);
    if (empty($structure)) {
      $structure = '{id}.{ext}';
      $form_state->setValue('structure', $structure);
    }

    // Additional data.
    $data_fields = $form_state->getValue('data_fields');
    $form_state->setValue('data_fields', $data_fields);

    // Check save mode.
    $save_mode = $form_state->getValue('save_mode');
    $form_state->setValue('save_mode', $save_mode);

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) :int {
    $entity_data = $entity->toRawData();
    $id = $entity_data[$this->getSourceIdFieldName() ?? static::DEFAULT_ID_FIELD];
    // Get entity path.
    $entity_file_path = $this->getEntityFilePath($entity_data);
    switch ($this->configuration['save_mode']) {
      case static::SAVE_FILE_MODE_DATE:
        touch($entity_file_path);
        $result = SAVED_UPDATED;
        break;

      case static::SAVE_FILE_NAME:
        // Check if name changed.
        if (empty($entity_data[static::ORIGINAL_PATH_FIELD])) {
          // New file.
          touch($entity_file_path);
          $result = SAVED_NEW;
        }
        elseif ($entity_file_path != $entity_data[static::ORIGINAL_PATH_FIELD]) {
          // Manage renaming.
          // Check that target file does not exist.
          if (file_exists($entity_file_path)) {
            // It exists, leave both original files and warn user.
            $this->messenger->addWarning(
              $this->t(
                'The new file name is already in use by another existing file. Could not rename entity file for entity "@entity".',
                [
                  '@entity' => $id,
                ]
              )
            );
            $this->logger->warning(
              'The new file name "'
              . $entity_file_path
              . '" is already in use by another existing file. Could not rename entity file "'
              . $entity_data[static::ORIGINAL_PATH_FIELD]
              . '" for entity "'
              . $id
              . '".'
            );
          }
          else {
            // Check for missing target directories and create them.
            $directory = dirname($entity_file_path);
            if (!file_exists($directory)) {
              if (FALSE === mkdir($directory, 0777, TRUE)) {
                $this->messenger->addWarning(
                  $this->t(
                    'Unable to create the new target directory for entity "@entity".',
                    [
                      '@entity' => $id,
                    ]
                  )
                );
                $this->logger->warning(
                  'Unable to create the new target directory "'
                  . $directory
                  . '" for entity "'
                  . $id
                  . '".'
                );
              }
              else {
                // Rename/move file.
                if (!rename($entity_data[static::ORIGINAL_PATH_FIELD], $entity_file_path)
                ) {
                  $this->messenger->addWarning(
                    $this->t(
                      'Unable to rename/move file for entity "@entity".',
                      [
                        '@entity' => $id,
                      ]
                    )
                  );
                  $this->logger->warning(
                    'Unable to rename/move file to "'
                    . $entity_file_path
                    . '" for entity "'
                    . $id
                    . '".'
                  );
                }
              }
            }
          }
          $result = SAVED_UPDATED;
        }
        break;

      case static::SAVE_NOTHING:
      default:
        $result = SAVED_UPDATED;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseFile(string $file_path) :array {
    // Get file info.
    $data = $this->getFileInfo($file_path);
    $id = key($data);

    // Adds checksum.
    if ($this->configuration['data_fields']['md5_checksum']) {
      $data[$id]['md5'] = md5_file($file_path);
    }
    if ($this->configuration['data_fields']['sha1_checksum']) {
      $data[$id]['sha1'] = sha1_file($file_path);
    }

    // Get columns.
    $data[''] = array_keys($data[$id]);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateRawData(array $entities_data) :string {
    return '';
  }

}
