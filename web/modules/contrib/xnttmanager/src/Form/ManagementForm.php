<?php

namespace Drupal\xnttmanager\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ManagementForm.
 *
 * Provides a simple form to inspect or batch process external entities.
 *
 * Selection du type d'external entity
 * Bouton de test de chargement de toutes les entities
 * Bouton de test de chargement+sauveguarde de toutes les external entities
 * Generation de stats sur les entities synchronisées: nombre dispo, nouvelles,
 *   orphœlines
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ManagementForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypePluginManager;

  /**
   * External entitites field mapper plugin manager.
   *
   * @var \Drupal\external_entities\FieldMapper\FieldMapperManager
   */
  protected $fieldMapperPluginManager;

  /**
   * External entitites storage client plugin manager.
   *
   * @var \Drupal\external_entities\StorageClient\StorageClientManager
   */
  protected $storageClientPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    $instance->fieldMapperPluginManager = $container->get('plugin.manager.external_entities.field_mapper');
    $instance->storageClientPluginManager = $container->get('plugin.manager.external_entities.storage_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $xntt_type_list = xnttmanager_get_external_entity_type_list();
    $form['xntt_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select an external entity type'),
      '#description' => $this->t('Only entities with required fields mapped will be shown here.'),
      '#options' => $xntt_type_list,
      '#empty_value' => '',
      '#sort_options' => TRUE,
    ];

    $form['inspect_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Mapping inspection'),
    ];

    $form['inspect_group']['xntt_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('External Entity identifier (optional)'),
      '#description' => $this->t('Enter the identifier of a specific external entity you want to inspect. Leave empty to select the first available one.'),
      '#required' => FALSE,
    ];

    $form['inspect_group']['actions'] = [
      '#type' => 'actions',
      'inspect' => [
        '#type' => 'submit',
        '#name' => 'inspect',
        '#value' => $this->t('Inspect'),
        '#submit' => [[$this, 'submitInspectForm']],
      ],
    ];
    // @todo Add a button to autogenerate mapping with the simple field mapper.
    $form['batch_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Batch processing'),
      '#description' => $this->t('When batch processing, all external entities of the selected type are
        loaded to check if they load fine and a global report is generated.
        Other additional actions can also be performed by checking the checkboxes below.'),
    ];

    $form['batch_group']['xntt_save'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save external entities as well'),
      '#description' => $this->t('Check to save selected external entities during batch processing. It might be useful for mass data conversion with xnttmulti.'),
      '#default_value' => FALSE,
    ];

    $form['batch_group']['xntt_annotate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Annotate external entities'),
      '#description' => $this->t('Check to add missing annotation to selected external entities during batch processing. Note: the selected external entity type must have an annotation content set in its settings.'),
      '#default_value' => FALSE,
    ];

    $form['batch_group']['actions'] = [
      '#type' => 'actions',
      'process' => [
        '#type' => 'submit',
        '#name' => 'process',
        '#value' => $this->t('Batch process'),
        '#submit' => [[$this, 'submitBatchForm']],
      ],
    ];

    $form['config_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Config (experimental)'),
      '#description' => $this->t('You can export or import external entities fields, field mapping and storage configuration to and from YAML files.'),
    ];

    $form['config_group']['xntt_config_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Config file to import'),
      '#multiple' => FALSE,
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'yml yaml json',
        ],
      ],
    ];

    $form['config_group']['actions'] = [
      '#type' => 'actions',
    ];
    $form['config_group']['actions']['export'] = [
      '#type' => 'submit',
      '#name' => 'export',
      '#value' => $this->t('Export config'),
      '#submit' => [[$this, 'submitExportForm']],
    ];

    $form['config_group']['actions']['import'] = [
      '#type' => 'submit',
      '#name' => 'import',
      '#value' => $this->t('Import config'),
      '#validate' => [[$this, 'validateImportForm']],
      '#submit' => [[$this, 'submitImportForm']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xnttmanager_management_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (empty($form_state->getValue('xntt_type'))) {
      $form_state->setErrorByName(
        'xntt_type',
        $this->t('You must select an external entity type.')
      );
    }

    if ($form_state->getValue('xntt_annotate')) {
      // Check if external entity type has an annotation.
      $entity_type = \Drupal::service('entity_type.manager')
        ->getStorage('external_entity_type')
        ->load($form_state->getValue('xntt_type'));
      if (empty($entity_type) || !$entity_type->isAnnotatable()) {
        $form_state->setErrorByName(
          'xntt_annotate',
          $this->t('The selected external entity type does not have annotations.')
        );
      }
    }
  }

  /**
   * Default submit method.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Inspect external entity fields and their mapping.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitInspectForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('page', 'inspect');
    $xntt_type = $form_state->getValue('xntt_type');
    $xntt_id = $form_state->getValue('xntt_id');
    $url = Url::fromRoute(
      'xnttmanager.inspect',
      [
        'xntt_type' => $xntt_type,
        'xntt_id' => $xntt_id,
      ]
    );
    $form_state->setRedirectUrl($url);
  }

  /**
   * Submit method for batch processing.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitBatchForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('page', 'batch');
    $params = [
      'xntt_type'  => $form_state->getValue('xntt_type'),
    ];

    // List actions to perform on entities.
    $actions = ['loading'];
    if ($form_state->getValue('xntt_save')) {
      $params['save'] = TRUE;
      $actions[] = 'saving';
    }

    if ($form_state->getValue('xntt_annotate')) {
      $params['annotate'] = TRUE;
      $actions[] = 'saving';
    }

    if (1 == count($actions)) {
      $title = 'External entity ' . $actions[0];
    }
    else {
      $title = ' and ' . array_pop($actions);
      $title = 'External entity ' . implode(', ', $actions) . $title;
    }

    $this->launchBatchProcessing(
      $form_state,
      $params,
      $title
    );
  }

  /**
   * Export external entity config.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitExportForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('page', 'export');
    $xntt_type = $form_state->getValue('xntt_type');

    $entity_type = $this->entityTypeManager
      ->getStorage('external_entity_type')
      ->load($xntt_type);
    if ($entity_type === NULL) {
      throw new NotFoundHttpException();
    }
    $entity_type_id = $entity_type->id();

    // Keep track of dependencies.
    $dependencies = [];
    $loaded_configs = [];

    // Get external entity config.
    $xntt_config = \Drupal::configFactory()->get("external_entities.external_entity_type.{$entity_type_id}")->getRawData();
    $dependencies = NestedArray::mergeDeep(
      $dependencies,
      $xntt_config['dependencies'] ?? []
    );
    $loaded_configs["external_entities.external_entity_type.{$entity_type_id}"] = $xntt_config;

    // @todo Manage multiple displays.
    // Get form settings.
    $form_config = \Drupal::configFactory()->get("core.entity_form_display.{$entity_type_id}.{$entity_type_id}.default")->getRawData();
    $dependencies = NestedArray::mergeDeep(
      $dependencies,
      $form_config['dependencies'] ?? []
    );
    $loaded_configs["core.entity_form_display.{$entity_type_id}.{$entity_type_id}.default"] = $form_config;

    // Get view settings.
    $view_config = \Drupal::configFactory()->get("core.entity_view_display.{$entity_type_id}.{$entity_type_id}.default")->getRawData();
    $dependencies = NestedArray::mergeDeep(
      $dependencies,
      $view_config['dependencies'] ?? []
    );
    $loaded_configs["core.entity_view_display.{$entity_type_id}.{$entity_type_id}.default"] = $view_config;

    // Normalize dependencies.
    $dependencies = array_map('array_unique', $dependencies);

    // Process config dependencies.
    $missing_configs = $dependencies['config'] ?? [];
    while (!empty($missing_configs)) {
      $config_id = array_shift($missing_configs);
      if (empty($loaded_configs[$config_id])) {
        $loaded_configs[$config_id] = TRUE;
        $config = \Drupal::configFactory()->get($config_id)->getRawData();
        if (!empty($config)) {
          $loaded_configs[$config_id] = $config;
          if (!empty($config['dependencies']['config'])) {
            $missing_configs = array_merge($missing_configs, $config['dependencies']['config']);
          }
        }
      }
    }
    unset($dependencies['config']);

    // Remove dependencies handled separately.
    unset($loaded_configs["external_entities.external_entity_type.{$entity_type_id}"]);
    unset($loaded_configs["core.entity_form_display.{$entity_type_id}.{$entity_type_id}.default"]);
    unset($loaded_configs["core.entity_view_display.{$entity_type_id}.{$entity_type_id}.default"]);
    $config_data = [
      'id' => $xntt_type,
      'date' => date('Ymd'),
      // 'fields' => $field_def_array,
      'xntt' => [
        'definition' => $xntt_config,
        'form_display' => $form_config,
        'view_display' => $view_config,
      ],
      'configs' => $loaded_configs,
      'dependencies' => $dependencies,
    ];

    // Convert to YAML.
    $yaml_content = \Drupal::service('serialization.yaml')->encode($config_data);
    $response = new Response($yaml_content);
    $disposition = HeaderUtils::makeDisposition(
      HeaderUtils::DISPOSITION_ATTACHMENT,
      'xnttmanager.' . $xntt_type . '.config.yml'
    );
    $response->headers->set('Content-Disposition', $disposition);

    $form_state->setResponse($response);

    $url = Url::fromRoute('xnttmanager.management');
    $form_state->setRedirectUrl($url);
    // @todo After submit, we stay on the same form page but buttons are
    // inactive. The page should be reloaded. Maybe rebuild form and perform
    // file creation on the building?
  }

  /**
   * {@inheritdoc}
   */
  public function validateImportForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Get uploaded file content.
    $file_upload = file_save_upload(
      'xntt_config_file',
      ['file_validate_extensions' => ['yml yaml json']],
      FALSE,
      NULL,
      FileSystemInterface::EXISTS_RENAME
    );
    if (!empty($file_upload) && is_array($file_upload)) {
      $yaml_file = $file_upload[0];
      $yaml_file_uri = $yaml_file->getFileUri();
      if (!empty($yaml_file_uri)) {
        try {
          $file_content = file_get_contents($yaml_file_uri);
          $yaml_data = \Drupal::service('serialization.yaml')->decode($file_content);
          // Check config file content.
          $config_keys = [
            'id',
            'date',
            'xntt',
            'configs',
            'dependencies',
          ];
          $missing_keys = [];
          foreach ($config_keys as $config_key) {
            if (!array_key_exists($config_key, $yaml_data)) {
              $missing_keys[] = $config_key;
            }
          }
          if (!empty($missing_keys)) {
            throw new \Exception('Invalid config file: missing key(s) "' . implode('", "', $missing_keys) . '".');
          }
          // Check if the external entity type already exists.
          $new_entity_type = $this->entityTypeManager
            ->getStorage('external_entity_type')
            ->load($yaml_data['id']);
          if (!empty($new_entity_type)) {
            throw new \Exception(
              'The external entity type to create ('
              . $yaml_data['id']
              . ') already exists. Import canceled.'
            );
          }

          if (!is_array($yaml_data['xntt'])) {
            throw new \Exception(
              'Invalid config file: invalid "xntt" value.'
            );
          }
          elseif (!is_array($yaml_data['xntt']['definition'])) {
            throw new \Exception(
              'Invalid config file: invalid "xntt.definition" value.'
            );
          }

          if (!is_array($yaml_data['configs'])) {
            throw new \Exception(
              'Invalid config file: invalid "configs" value.'
            );
          }

          if (!is_array($yaml_data['dependencies'])) {
            throw new \Exception(
              'Invalid config file: invalid "dependencies" value.'
            );
          }

          // Check missing modules.
          if (!empty($yaml_data['dependencies']['module'])) {
            $missing_modules = [];
            $module_handler = \Drupal::service('module_handler');
            foreach ($yaml_data['dependencies']['module'] as $module) {
              if (!$module_handler->moduleExists($module)) {
                $missing_modules[] = $module;
              }
            }
          }
          if (!empty($missing_modules)) {
            throw new \Exception('Dependencies: missing module(s) "' . implode('", "', $missing_modules) . '".');
          }

          // Check for unsupported dependency types ('content', 'theme',
          // 'enforced').
          $unsupported_dependencies = $yaml_data['dependencies'];
          unset($unsupported_dependencies['config']);
          unset($unsupported_dependencies['module']);
          if (!empty($unsupported_dependencies)) {
            throw new \Exception('Dependencies: unsupported dependency type(s) "' . implode('", "', array_keys($unsupported_dependencies)) . '".');
          }

          // Check unsupported configs.
          $missing_configs = [];
          foreach (array_keys($yaml_data['configs']) as $extra_config) {
            if (!preg_match('#^field\.(?:field\.' . $yaml_data['id'] . '|storage)\.' . $yaml_data['id'] . '\.\w+$#', $extra_config)
              && \Drupal::configFactory()->get($extra_config)->isNew()
            ) {
              // Not a field or a storage and the config does not already exist.
              $missing_configs[] = $extra_config;
            }
          }
          if (!empty($missing_configs)) {
            throw new \Exception('Dependencies: missing (unsupported) config(s) "' . implode('", "', $missing_configs) . '".');
          }

          $form_state->set('config_data', $yaml_data);
        }
        catch (ParseException $e) {
          $err_line = $e->getParsedLine();
          if (0 <= $err_line) {
            $form_state->setErrorByName(
              'xntt_config_file',
              'Failed to parse Yaml config file near line '
              . $e->getParsedLine()
              . ': '
              . $e->getSnippet()
            );
          }
          else {
            $form_state->setErrorByName(
              'xntt_config_file',
              'Failed to parse Yaml config file: '
              . $config_data
            );
          }
        }
        catch (\Exception $e) {
          $form_state->setErrorByName(
            'xntt_config_file',
            $e->getMessage()
          );
        }
      }
      else {
        $form_state->setErrorByName(
          'xntt_config_file',
          $this->t('Unable to read uploaded file: empty file.')
        );
      }
    }
    else {
      $form_state->setErrorByName(
        'xntt_config_file',
        $this->t('You must provide an external entity config file.')
      );
    }
  }

  /**
   * Prepare external entity config import.
   *
   * This is a two steps form.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitImportForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('page', 'import');

    $yaml_config = $form_state->get('config_data');

    // Create new external entity type.
    $xntt_type = $this->entityTypeManager
      ->getStorage('external_entity_type')
      ->create($yaml_config['xntt']['definition']);
    $xntt_type->save();
    $xntt_type_id = $xntt_type->id();

    // Add and configure fields.
    $field_storage_man = $this
      ->entityTypeManager
      ->getStorage('field_storage_config');
    $field_config_man = $this
      ->entityTypeManager
      ->getStorage('field_config');
    try {
      $field_configs = [];
      $field_storage_configs = [];
      // Filter fields and field storages.
      foreach ($yaml_config['configs'] as $config_id => $config) {
        if (preg_match("#^field\\.field\\.$xntt_type_id\\.$xntt_type_id\\.(\\w+)\$#", $config_id, $match)) {
          // Got a field config.
          $field_configs[$match[1]] = $config;
        }
        elseif (preg_match("#^field\\.storage\\.$xntt_type_id\\.(\\w+)\$#", $config_id, $match)) {
          // Got a field storage config.
          $field_storage_configs[$match[1]] = $config;
        }
      }

      // Field storages first.
      foreach ($field_storage_configs as $field_name => $field_storage_config) {
        // Cleanup config.
        unset($field_storage_config['uuid']);
        $field_storage_config['id'] = "$xntt_type_id.$field_name";
        $field_storage_config['entity_type'] = $xntt_type_id;
        $field_storage_config['bundle'] = $xntt_type_id;
        $field_storage_config['field_name'] = $field_name;

        $field_storage_def = $field_storage_man->load($field_storage_config['id']);
        if (empty($field_storage_def)) {
          // Not existing, create.
          $field_storage_def = $field_storage_man
            ->create($field_storage_config)
            ->save();
        }
      }

      // Then fields.
      foreach ($field_configs as $field_name => $field_config) {
        // Cleanup config.
        unset($field_config['uuid']);
        $field_config['id'] = "$xntt_type_id.$xntt_type_id.$field_name";
        $field_config['entity_type'] = $xntt_type_id;
        $field_config['bundle'] = $xntt_type_id;

        $field_def = $field_config_man->load($field_config['id']);
        if (empty($field_def)) {
          // Not existing, create.
          $field_def = $field_config_man
            ->create($field_config)
            ->save();
        }
      }
    }
    catch (PluginNotFoundException $e) {
      $this->logger('xnttmanager')->warning($e);
    }

    // @todo Manage multiple displays.
    // Add form config.
    'form_display';
    $formdisp_storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
    $form_display = $formdisp_storage->load("$xntt_type_id.$xntt_type_id.default");
    if (empty($form_display) && !empty($yaml_config['xntt']['form_display'])) {
      // Not existing, create.
      $form_display = $formdisp_storage
        ->create($yaml_config['xntt']['form_display'])
        ->save();
    }

    // Add view config.
    $viewdisp_storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
    $view_display = $viewdisp_storage->load("$xntt_type_id.$xntt_type_id.default");
    if (empty($view_display) && !empty($yaml_config['xntt']['view_display'])) {
      // Not existing, create.
      $view_display = $viewdisp_storage
        ->create($yaml_config['xntt']['view_display'])
        ->save();
    }
  }

  /**
   * Batch-process all external entities of the given type.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   * @param array $params
   *   The batch parameters.
   * @param string $title
   *   The batch title.
   */
  public function launchBatchProcessing(
    FormStateInterface $form_state,
    array $params,
    string $title,
  ) {
    $operations = [
      [
        'xnttmanager_bulk_process',
        [$params],
      ],
    ];
    $batch = [
      // phpcs:disable
      // Users may want to use translated titles.
      'title' => $this->t($title),
      // phpcs:enable
      'operations' => $operations,
      'finished' => 'xnttmanager_bulk_finished',
      'progressive' => TRUE,
    ];
    batch_set($batch);
  }

}
