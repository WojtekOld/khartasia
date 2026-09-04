<?php

namespace Drupal\proc\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\proc\Element\ProcField;
use Drupal\proc\Entity\Proc;
use Drupal\proc\Event\ProcElementEvent;
use Drupal\proc\Event\ProcEvents;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcReEncRecSetPluginManager;
use Drupal\proc\ProcRelabellingPluginManager;
use Drupal\proc\Service\ProcFieldProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the 'proc_entity_reference_widget' field widget.
 *
 * @FieldWidget(
 *   id = "proc_entity_reference_widget",
 *   module = "proc",
 *   label = @Translation("Proc Entity Reference Field Widget"),
 *   field_types = {
 *     "proc_entity_reference_field"
 *   }
 * )
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcEntityReferenceWidget extends EntityReferenceAutocompleteWidget {

  /**
   * The plugin.manager.proc_relabelling service.
   *
   * @var \Drupal\proc\ProcRelabellingPluginManager
   */
  protected ProcRelabellingPluginManager $relabelPluginManager;

  /**
   * The event_dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The token service.
   *
   * @var mixed
   */
  protected $tokenService;


  /**
   * The ProcFieldProcessor service.
   *
   * @var \Drupal\proc\Service\ProcFieldProcessor
   */
  protected ProcFieldProcessor $procFieldProcessor;

  /**
   * The plugin.manager.proc_re_enc_rec_set service.
   *
   * @var \Drupal\proc\ProcReEncRecSetPluginManager
   */
  protected ProcReEncRecSetPluginManager $reEnReSetPluginMan;

  /**
   * Constructs a ProcEntityReferenceWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\proc\ProcRelabellingPluginManager $relabelPluginManager
   *   The plugin.manager.proc_relabelling service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event_dispatcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param mixed $tokenService
   *   The token service.
   * @param \Drupal\proc\Service\ProcFieldProcessor $proc_field_processor
   *   The proc field processor.
   * @param \Drupal\proc\ProcReEncRecSetPluginManager $reEnReSetPluginMan
   *   The plugin.manager.proc_re_enc_rec_set service.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ProcRelabellingPluginManager $relabelPluginManager,
    EventDispatcherInterface $eventDispatcher,
    EntityTypeManagerInterface $entityTypeManager,
    CurrentPathStack $currentPath,
    ConfigFactoryInterface $configFactory,
    $tokenService,
    ProcFieldProcessor $proc_field_processor,
    ProcReEncRecSetPluginManager $reEnReSetPluginMan,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
    );

    PluginSettingsBase::__construct([], $plugin_id, $plugin_definition);
    $this->fieldDefinition = $field_definition;
    $this->settings = $settings;
    $this->thirdPartySettings = $third_party_settings;
    $this->relabelPluginManager = $relabelPluginManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentPath = $currentPath;
    $this->configFactory = $configFactory;
    $this->tokenService = $tokenService;
    $this->procFieldProcessor = $proc_field_processor;
    $this->reEnReSetPluginMan = $reEnReSetPluginMan;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.proc_relabelling'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('path.current'),
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('proc.proc_field_processor'),
      $container->get('plugin.manager.proc_re_enc_rec_set')
    );
  }

  /**
   * Return field operation mode options.
   *
   * @return array
   *   The field operation mode options.
   */
  private function getFieldOperationModes(): array {
    return [
      ProcInterface::FIELD_OPS_DISABLED  => $this->t('Disabled'),
      ProcInterface::FIELD_OPS_ONLY_ENCRYPTION => $this->t('Only encryption'),
      ProcInterface::FIELD_OPS_ENCRYPTION_AND_SIGNATURE => $this->t('Encryption and signature'),
    ];
  }

  /**
   * Return field input mode options.
   *
   * @return array
   *   The field input mode options.
   */
  private function getInputModes(): array {
    return [
      ProcInterface::INPUT_MODE_FILE => $this->t('File'),
      ProcInterface::INPUT_MODE_TEXTAREA => $this->t('Text area'),
      ProcInterface::INPUT_MODE_TEXTFIELD => $this->t('Text field'),
      ProcInterface::INPUT_MODE_DATE => $this->t('Date'),
    ];
  }

  /**
   * Return decryption mode options.
   *
   * @return array
   *   The decryption mode options.
   */
  private function getDecryptionModes(): array {
    return [
      ProcInterface::DECRYPT_MODE_NEW_PAGE => $this->t('New page'),
      ProcInterface::DECRYPT_MODE_DIALOG => $this->t('Dialog'),
      ProcInterface::DECRYPT_MODE_INLINE => $this->t('Inline'),
      ProcInterface::DECRYPT_MODE_DISABLED => $this->t('Disabled'),
    ];
  }

  /**
   * Return password caching options.
   *
   * @return array
   *   The password caching options.
   */
  private function getPasswordCachingOptions(): array {
    return [
      ProcInterface::PASS_CACHE_OPS_DISABLED => $this->t('Disable password caching'),
      ProcInterface::PASS_CACHE_OPS_ALLOW => $this->t('Show checkbox for allowing password caching'),
      ProcInterface::PASS_CACHE_OPS_APPLY => $this->t('Hide the checkbox and apply password caching by default'),
    ];
  }

  /**
   * Return trigger format options.
   *
   * @return array
   *   The trigger format options.
   */
  private function getTriggerFormats(): array {
    return [
      ProcInterface::TRIGGER_FORMAT_BUTTONS => $this->t('Encrypt and Decrypt buttons'),
      ProcInterface::TRIGGER_FORMAT_CHECKBOX => $this->t('Toggle checkbox (check for encryption, uncheck for decryption)'),
    ];
  }

  /**
   * Return fetcher endpoint filter type options.
   *
   * @return array
   *   The fetcher endpoint filter type options.
   */
  private function getFetcherEndpointFilterTypes(): array {
    return [
      ProcInterface::FETCHER_FILTER_TYPE_ID => $this->t('ID'),
      ProcInterface::FETCHER_FILTER_TYPE_NAME => $this->t('Name'),
    ];
  }

  /**
   * Return options for a given plugin manager.
   *
   * @param \Drupal\Core\Plugin\DefaultPluginManager $plugin_manager
   *   The plugin manager to get definitions from.
   *   The text to use for the "None" option.
   *
   * @return array
   *   The options array.
   */
  private function getPluginOptions(DefaultPluginManager $plugin_manager): array {
    // Get list of available plugins.
    $plugins = $plugin_manager->getDefinitions();

    // Populate widget options with plugin labels.
    $options = [];
    $options[] = $this->t('- None -');
    foreach ($plugins as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = $plugin_definition['description'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'proc_field_recipients_fetcher_endpoint' => '',
      'proc_field_recipients_fetcher_endpoint_filter' => '',
      'proc_field_recipients_fetcher_endpoint_filter_type' => '',
      'proc_field_recipients_manual_fetcher' => '',
      'proc_field_recipients_to_field' => '',
      'proc_field_recipients_cc_field' => '',
      'proc_field_encrypt_button_label' => '',
      'proc_field_decrypt_button_label' => '',
      'proc_field_mode' => ProcInterface::FIELD_OPS_ONLY_ENCRYPTION,
      'proc_field_input_mode' => ProcInterface::INPUT_MODE_FILE,
      'proc_field_decryption_mode' => '',
      'proc_allow_password_caching' => '',
      'proc_trigger_format' => '',
      'proc_master_field_submit_id' => '',
      'proc_decryption_trigger_fields' => '',
      'proc_relabeling_pattern' => 0,
      'proc_re_encryption_plugin' => 0,
      'proc_recipients_delivery_override' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];
    $element[] = parent::settingsForm($form, $form_state);
    $element['proc_field_recipients_to_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name of the field containing the user IDs of recipients'),
      '#description' => $this->t('It must be a user reference field visible in the same form as this field.'),
      '#default_value' => $this->getSetting('proc_field_recipients_to_field') ?? '',
    ];
    $element['proc_field_recipients_cc_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name of the field containing user IDs of CC recipients'),
      '#description' => $this->t('It must be a user reference field visible in the same form as this field.'),
      '#default_value' => $this->getSetting('proc_field_recipients_cc_field') ?? '',
    ];
    $element['proc_field_recipients_manual_fetcher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Define the user IDs of recipients in a CSV list'),
      '#description' => $this->t('Example: 1,2,3'),
      '#default_value' => $this->getSetting('proc_field_recipients_manual_fetcher') ?? '',
      '#disabled' => FALSE,
    ];
    $element['proc_field_recipients_fetcher_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint for fetching user IDs of recipients'),
      '#description' => $this->t('Example: https://example.com/api/get/users/param-a/param-b/param-c?param-d=1&#38;param-e=2. Accepted json formats: 1. a flat list of user IDs as in [1,2,3] 2. a list of objects with user IDs keyed buy uid as in [{"uid":1},{"uid":2},{"uid":3}]'),
      '#default_value' => $this->getSetting('proc_field_recipients_fetcher_endpoint') ?? '',
      '#disabled' => FALSE,
    ];
    $element['proc_field_recipients_fetcher_endpoint_token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['site', 'current-page'],
      '#show_restricted' => TRUE,
    ];
    $element['proc_field_recipients_fetcher_endpoint_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier of form elements to be used as parameters for the endpoint'),
      '#description' => $this->t('Example: edit-field-a,edit-field-b,edit-field-c'),
      '#default_value' => $this->getSetting('proc_field_recipients_fetcher_endpoint_filter') ?? '',
      '#disabled' => FALSE,
    ];
    $element['proc_field_encrypt_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the encrypt link'),
      '#description' => $this->t('The label for attaching an encrypted file.'),
      '#default_value' => $this->getSetting('proc_field_encrypt_button_label') ?? '',
    ];
    $element['proc_field_decrypt_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the decrypt link'),
      '#description' => $this->t('The label for decrypt an encrypted file.'),
      '#default_value' => $this->getSetting('proc_field_decrypt_button_label') ?? '',
    ];
    $element['proc_field_recipients_fetcher_endpoint_filter_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type of identifier'),
      '#default_value' => $this->getSetting('proc_field_recipients_fetcher_endpoint_filter_type') ?? ProcInterface::FETCHER_FILTER_TYPE_ID,
      '#options' => $this->getFetcherEndpointFilterTypes(),
      '#disabled' => FALSE,
    ];
    $element['proc_field_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Modes of operation'),
      '#default_value' => $this->getSetting('proc_field_mode') ?? ProcInterface::FIELD_OPS_ONLY_ENCRYPTION,
      '#options' => $this->getFieldOperationModes(),
      ProcInterface::FIELD_OPS_ENCRYPTION_AND_SIGNATURE => [
        '#disabled' => TRUE,
      ],
    ];
    $element['proc_field_input_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Modes of input'),
      '#default_value' => $this->getSetting('proc_field_input_mode') ?? ProcInterface::INPUT_MODE_FILE,
      '#options' => $this->getInputModes(),
      ProcInterface::INPUT_MODE_TEXTAREA  => ['#disabled' => TRUE],
      ProcInterface::INPUT_MODE_DATE => ['#disabled' => TRUE],
    ];
    $element['proc_field_decryption_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Modes of decryption'),
      '#default_value' => $this->getSetting('proc_field_decryption_mode') ?? ProcInterface::DECRYPT_MODE_NEW_PAGE,
      '#options' => $this->getDecryptionModes(),
    ];
    $element['proc_allow_password_caching'] = [
      '#type' => 'radios',
      '#title' => $this->t('Password caching'),
      '#default_value' => $this->getSetting('proc_allow_password_caching') ?? ProcInterface::PASS_CACHE_OPS_DISABLED,
      '#options' => $this->getPasswordCachingOptions(),
      '#description' => $this->t('This configuration does not interfere over Armoured field formatter, where password caching is enabled by default.'),
    ];
    $element['proc_trigger_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Trigger format'),
      '#default_value' => $this->getSetting('proc_trigger_format') ?? ProcInterface::TRIGGER_FORMAT_BUTTONS,
      '#options' => $this->getTriggerFormats(),
    ];
    $element['proc_master_field_submit_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Submit element ID for the master field'),
      '#description' => $this->t('Define the submit element ID of the form where this field is located. Example: edit-submit'),
      '#default_value' => $this->getSetting('proc_master_field_submit_id') ?? '',
      '#disabled' => FALSE,
    ];
    $element['proc_decryption_trigger_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trigger decryption of another encrypted field'),
      '#default_value' => $this->getSetting('proc_decryption_trigger_fields') ?? '',
    ];
    $element['proc_relabeling_pattern'] = [
      '#type' => 'select',
      '#title' => $this->t('Relabelling pattern'),
      '#description' => $this->t('Select a plugin for defining the new label of the proc entity. The standard pattern: [host_entity_label]_[proc_id] | Encrypted by [author_label] on [encryption_date_time]'),
      '#default_value' => $this->getSetting('proc_relabeling_pattern') ?? ProcInterface::TRIGGER_FORMAT_BUTTONS,
      '#options' => $this->getPluginOptions($this->relabelPluginManager),
    ];
    $element['proc_re_encryption_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Re-encryption plugin'),
      '#description' => $this->t('Select a plugin for defining the set of wished recipients.'),
      '#default_value' => $this->getSetting('proc_re_encryption_plugin') ?? ProcInterface::TRIGGER_FORMAT_BUTTONS,
      '#options' => $this->getPluginOptions($this->reEnReSetPluginMan),
    ];
    $element['proc_recipients_delivery_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Recipients delivery mode override'),
      '#description' => $this->t('Override the global recipients delivery mode for this field. Use "Force CSV" for fields with a direct/manual fetcher or a small static recipient list.'),
      '#default_value' => $this->getSetting('proc_recipients_delivery_override') ?? '',
      '#options' => [
        '' => $this->t('Use global setting'),
        'csv' => $this->t('Force CSV'),
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Machine name of recipients To: @proc_field_recipients_to_field', [
      '@proc_field_recipients_to_field' => $this->getSetting('proc_field_recipients_to_field'),
    ]);
    $summary[] = $this->t('Machine name of recipients CC: @proc_field_recipients_cc_field', [
      '@proc_field_recipients_cc_field' => $this->getSetting('proc_field_recipients_cc_field'),
    ]);
    $summary[] = $this->t('Default recipients: @proc_field_recipients_manual_fetcher', [
      '@proc_field_recipients_manual_fetcher' => $this->getSetting('proc_field_recipients_manual_fetcher'),
    ]);
    $summary[] = $this->t('Recipients fetcher endpoint: @proc_field_recipients_fetcher_endpoint', [
      '@proc_field_recipients_fetcher_endpoint' => $this->getSetting('proc_field_recipients_fetcher_endpoint'),
    ]);
    $summary[] = $this->t('Machine name of fetcher filter: @proc_field_recipients_fetcher_endpoint_filter', [
      '@proc_field_recipients_fetcher_endpoint_filter' => ($this->getSetting('proc_field_recipients_fetcher_endpoint_filter')) ?: '',
    ]);
    $summary[] = $this->t('Recipients fetcher type: @proc_field_recipients_fetcher_endpoint_filter_type', [
      '@proc_field_recipients_fetcher_endpoint_filter_type' => ($this->getSetting('proc_field_recipients_fetcher_endpoint_filter_type'))
        ? $this->getFetcherEndpointFilterTypes()[$this->getSetting('proc_field_recipients_fetcher_endpoint_filter_type')]
        : '',
    ]);
    $summary[] = $this->t('Encrypt button label: @encrypt_button_label', [
      '@encrypt_button_label' => ($this->getSetting('proc_field_encrypt_button_label')) ?: $this->t('use default'),
    ]);
    $summary[] = $this->t('Decrypt button label: @decrypt_button_label', [
      '@decrypt_button_label' => ($this->getSetting('proc_field_decrypt_button_label')) ?: $this->t('use default'),
    ]);
    $proc_field_mode = $this->getSetting('proc_field_mode');
    $summary[] = $this->t('Operation: @proc_field_mode', [
      '@proc_field_mode' => (isset($proc_field_mode))
        ? $this->getFieldOperationModes()[$proc_field_mode]
        : '',
    ]);
    $in_mode = $this->getSetting('proc_field_input_mode');
    $summary[] = $this->t('Input mode: @proc_field_input_mode', [
      '@proc_field_input_mode' => (isset($in_mode))
        ? $this->getInputModes()[$in_mode]
        : '',
    ]);
    $decry_mode = $this->getSetting('proc_field_decryption_mode');
    $summary[] = $this->t('Decryption mode: @proc_field_decryption_mode', [
      '@proc_field_decryption_mode' => (!empty($decry_mode))
        ? $this->getDecryptionModes()[$decry_mode]
        : '',
    ]);
    $pass_caching = $this->getSetting('proc_allow_password_caching');
    $summary[] = $this->t('Password caching: @proc_allow_password_caching', [
      '@proc_allow_password_caching' => (!empty($pass_caching))
        ? $this->getPasswordCachingOptions()[$pass_caching]
        : '',
    ]);
    $proc_trigger_format = $this->getSetting('proc_trigger_format');
    $summary[] = $this->t('Trigger format: @proc_trigger_format', [
      '@proc_trigger_format' => (!empty($proc_trigger_format))
        ? $this->getTriggerFormats()[$proc_trigger_format]
        : '',
    ]);
    $submit_id = $this->getSetting('proc_master_field_submit_id');
    $summary[] = $this->t('Submit element ID: @proc_master_field_submit_id', [
      '@proc_master_field_submit_id' => $submit_id,
    ]);
    $trigger_fields = $this->getSetting('proc_decryption_trigger_fields');
    $summary[] = $this->t('Trigger other fields on decryption: @proc_decryption_trigger_fields', [
      '@proc_decryption_trigger_fields' => (isset($trigger_fields))
        ? $this->t('Yes')
        : $this->t('No'),
    ]);
    $relabel_pattern = $this->getSetting('proc_relabeling_pattern') ?? '0';
    if (!empty($relabel_pattern)) {
      $summary[] = $this->t('Relabelling pattern: @proc_relabeling_pattern', [
        '@proc_relabeling_pattern' => $this->getPluginOptions($this->relabelPluginManager)[$relabel_pattern],
      ]);
    }
    $re_encryption_plugin = $this->getSetting('proc_re_encryption_plugin') ?? '0';
    if (!empty($re_encryption_plugin)) {
      $summary[] = $this->t('Re-encryption plugin: @proc_re_encryption_plugin', [
        '@proc_re_encryption_plugin' => $this->getPluginOptions($this->reEnReSetPluginMan)[$re_encryption_plugin],
      ]);
    }
    $delivery_override = $this->getSetting('proc_recipients_delivery_override') ?? '';
    $summary[] = $this->t('Recipients delivery: @mode', [
      '@mode' => $delivery_override === 'csv' ? $this->t('Force CSV') : $this->t('Use global setting'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Call the parent formElement method to get the base element.
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // Retrieve element forms state value.
    $element_value = $form_state->getValue($items->getName());

    $proc_settings = [
      'field_input_mode' => $this->getSetting('proc_field_input_mode') ?? '',
      'to_recipients_field_name' => $this->getSetting('proc_field_recipients_to_field') ?? '',
      'carbon_copy_recipients_field_name' => $this->getSetting('proc_field_recipients_cc_field') ?? '',
      'preset_recipients_csv' => $this->getSetting('proc_field_recipients_manual_fetcher') ?? '',
      'fetcher_endpoint' => $this->getSetting('proc_field_recipients_fetcher_endpoint') ?? '',
      'fetcher_endpoint_filter' => $this->getSetting('proc_field_recipients_fetcher_endpoint_filter') ?? '',
      'fetcher_endpoint_filter_type' => $this->getSetting('proc_field_recipients_fetcher_endpoint_filter_type') ?? '',
      'encrypt_button_label' => $this->getSetting('proc_field_encrypt_button_label') ?? '',
      'decrypt_button_label' => $this->getSetting('proc_field_decrypt_button_label') ?? '',
      'cache_password' => $this->getSetting('proc_allow_password_caching') ?? '0',
      'trigger_format' => $this->getSetting('proc_trigger_format') ?? '0',
      'submit_element_id' => $this->getSetting('proc_master_field_submit_id') ?? '',
      'trigger_fields' => $this->getSetting('proc_decryption_trigger_fields') ?? '0',
      'proc_field_decryption_mode' => $this->getSetting('proc_field_decryption_mode') ?? '',
      'proc_field_name' => $items->getName(),
      'proc_field_mode' => $this->getSetting('proc_field_mode') ?? ProcInterface::FIELD_OPS_DISABLED,
      'proc_relabeling_pattern' => $this->getSetting('proc_relabeling_pattern') ?? ProcInterface::TRIGGER_FORMAT_BUTTONS,
      'proc_re_encryption_plugin' => $this->getSetting('proc_re_encryption_plugin') ?? ProcInterface::TRIGGER_FORMAT_BUTTONS,
      'proc_recipients_delivery_override' => $this->getSetting('proc_recipients_delivery_override') ?? '',
      'proc_field_cardinality' => $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality(),
    ];

    // Merge extracted settings with existing element if any.
    $element['#proc-settings'] = array_merge(($element['#proc-settings'] ?? []), $proc_settings);

    if ((int) $element['#proc-settings']['proc_field_mode'] !== ProcInterface::FIELD_OPS_DISABLED) {
      $element['#type'] = 'procfield';
    }

    // Keep form state value after AJAX calls.
    if (!empty($element_value[$delta]['target_id'])) {
      $target_id = $element_value[$delta]['target_id'];
      $proc_entity = $this->entityTypeManager->getStorage('proc')
        ->load($target_id);
      if ($proc_entity instanceof Proc) {
        // Entity Reference requires " (id)" suffix.
        $element['target_id']['#attributes']['value'] = $proc_entity->label() . ' (' . $target_id . ')';
      }
    }

    $element['#element_validate'][] = [$this, 'validate'];

    $procElement = new ProcField($this->currentPath, $this->configFactory, $this->tokenService, $this->procFieldProcessor);
    $event = new ProcElementEvent($procElement);
    $this->eventDispatcher->dispatch($event, ProcEvents::PROC_ELEMENT_SETTINGS);

    return $element;
  }

  /**
   * Remove reference if field is empty or keep it if encryption is unchanged.
   *
   * @param array $element
   *   The element to be validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function validate(array $element, FormStateInterface $form_state): void {
    $procField = $element['#proc-settings']['proc_field_name'];

    $default_value = $form_state->getValue($procField)[0]['target_id'] ?? NULL;

    if (empty($default_value)) {
      // This is needed to prevent "This value should be of the correct
      // primitive type." error. See:
      // https://www.drupal.org/project/drupal/issues/2220381
      $form_state->setValueForElement($element, ['target_id' => NULL]);
    }

    if ($default_value === '*******') {
      $form_state->setValueForElement($element, ['target_id' => $element['target_id']['#default_value']->id()]);
    }
    elseif (preg_match('/[*]+ [(]\d+[)]$/', (string) $default_value)) {
      $target_id = preg_replace('/[^0-9]+/', '', $default_value);
      $form_state->setValueForElement($element, ['target_id' => $target_id]);
    }

    if (!is_array($form_state->getValue($procField))) {
      return;
    }

    $values = array_column($form_state->getValue($procField), 'target_id');

    // Early return if the first value is invalid or the element is a textfield.
    if (empty($values[0]) || $values[0] == '*******' || $element['target_id']['#type'] == 'textfield') {
      return;
    }

    if (!$this->checkValue($values)) {
      return;
    }

    $hostEntity = $element['target_id']['#selection_settings']['entity'] ?? NULL;
    if (!$hostEntity) {
      return;
    }

    $relabelingPattern = $element['#proc-settings']['proc_relabeling_pattern'] ?? '0';
    if ($relabelingPattern == '0') {
      return;
    }

    $type = $this->relabelPluginManager;
    foreach ($values as $key => $value) {
      if (!is_numeric($value)) {
        continue;
      }

      $pluginInstance = $type->createInstance($relabelingPattern, []);
      $pluginInstance->relabel([
        'element' => $element,
        'form_state' => $form_state,
        'host_entity' => $hostEntity,
        'proc_id' => $value,
        'proc_key' => $key,
        'proc_field' => $procField,
      ]);
    }

  }

  /**
   * Check if the value is numeric.
   *
   * @param array $values
   *   The values to be checked.
   *
   * @return bool
   *   TRUE if the value is numeric, FALSE otherwise.
   */
  private function checkValue($values): bool {
    if (count($values) > 2) {
      if (array_pop($values) === '') {
        return FALSE;
      }
    }
    foreach ($values as $value) {
      if (!is_numeric($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
