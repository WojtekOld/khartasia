<?php

namespace Drupal\proc\Plugin\Field\FieldFormatter;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcUpdateJobsCountService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity reference label' formatter.
 *
 * @FieldFormatter(
 *   id = "proc_entity_reference_armoured_formatter",
 *   label = @Translation("Armoured"),
 *   description = @Translation("Display the armoured cipher text for decryption on lists."),
 *   field_types = {
 *     "proc_entity_reference_field"
 *   }
 * )
 */
class ProcArmouredFieldFormatter extends EntityReferenceLabelFormatter {

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The update jobs count service.
   *
   * @var \Drupal\proc\Service\ProcUpdateJobsCountService
   */
  protected ProcUpdateJobsCountService $updateJobsCountService;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a ProcArmouredFieldFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\proc\ProcKeyManagerInterface $proc_key_manager
   *   The ProcKeyManager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The extension list module service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\proc\Service\ProcUpdateJobsCountService $update_jobs_count_service
   *   The update jobs count service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ProcKeyManagerInterface $proc_key_manager,
    ModuleHandlerInterface $module_handler,
    AccountProxy $current_user,
    ConfigFactoryInterface $config_factory,
    ProcUpdateJobsCountService $update_jobs_count_service,
    LoggerInterface $logger,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );
    PluginSettingsBase::__construct([], $plugin_id, $plugin_definition);
    $this->fieldDefinition = $field_definition;
    $this->settings = $settings;
    $this->label = $label;
    $this->viewMode = $view_mode;
    $this->thirdPartySettings = $third_party_settings;
    $this->procKeyManager = $proc_key_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->updateJobsCountService = $update_jobs_count_service;
    $this->logger = $logger;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('proc.key_manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('proc.update_jobs_count_service'),
      $container->get('logger.factory')->get('proc')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $current_user_id = $this->currentUser->id();
    try {
      $keyring = $this->procKeyManager->getKeys($current_user_id, 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return $elements;
    }
    $privkey = FALSE;
    if (!empty($keyring)) {
      $privkey = $keyring['encrypted_private_key'];
    }

    $recipient = FALSE;

    $module_path = $this->moduleHandler->getModule('proc')->getPath();
    $template_fa = $module_path . '/templates/proc.armoured_formatter.html.twig';
    $template_file_label = $module_path . '/templates/proc.label_formatter.html.twig';

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $recipients = $entity->get('field_recipients_set')->getValue();
      $label = $entity->label();

      $elements[$delta]['placeholder'] = [
        '#type' => 'inline_template',
        '#context' => [
          'label' => $label,
          'class' => 'proc-armoured-field-formatter',
        ],
        '#template' => file_get_contents($template_file_label),
        '#cache' => [
          'tags' => $entity->getCacheTags(),
          'contexts' => ['user'],
        ],
      ];

      if (in_array($current_user_id, array_column($recipients, 'target_id'))) {
        $recipient = TRUE;

        $elements[$delta]['placeholder']['#context']['value'] = $entity->getCipherText();
        $elements[$delta]['placeholder']['#context']['pid'] = $entity->id();
        $elements[$delta]['placeholder']['#context']['type'] = $entity->getMeta()[0]['source_file_type'];
        $elements[$delta]['placeholder']['#template'] = file_get_contents($template_fa);
      }
    }

    if ($recipient) {
      $bg_settings = [];
      $config = $this->configFactory->get('proc.settings');
      if ($config->get('proc-enable-background-reencryption')) {
        $limit = (int) ($config->get('proc-background-update-jobs-limit') ?? 2);
        if ($limit > 0) {
          try {
            $updateJobsCount = $this->updateJobsCountService->getCountForUser($current_user_id);
            if ($updateJobsCount > 0) {
              $bg_settings = [
                'proc_background_update_jobs_limit' => $limit,
                'proc_background_update_jobs_count' => $updateJobsCount,
                'proc_module_path' => $this->moduleHandler->getModule('proc')->getPath(),
              ];
            }
          }
          catch (\Exception $e) {
            $this->logger->error('Background re-encryption settings failed for user @uid: @error', [
              '@uid' => $current_user_id,
              '@error' => $e->getMessage(),
            ]);
          }
        }
      }

      $autonomous_settings = [];
      if ($config->get('proc-enable-autonomous-reencryption')) {
        $autonomous_jobs_count = 0;
        try {
          /** @var \Drupal\proc\Service\ProcUpdateJobsCountService $countService */
          $countService = \Drupal::service('proc.update_jobs_count_service');
          $autonomous_jobs_count = $countService->getCountForUser($current_user_id);
        }
        catch (\Exception $e) {
          $this->logger->error('Autonomous re-encryption settings failed for user @uid: @error', [
            '@uid' => $current_user_id,
            '@error' => $e->getMessage(),
          ]);
        }
        if ($autonomous_jobs_count > 0) {
          $autonomous_settings = [
            'proc_autonomous_reencryption_enabled' => TRUE,
            'proc_autonomous_reencryption_cooldown' => (int) ($config->get('proc-autonomous-reencryption-cooldown') ?? 300),
            'proc_background_update_jobs_limit' => (int) ($config->get('proc-background-update-jobs-limit') ?? 2),
            'proc_background_update_jobs_count' => $autonomous_jobs_count,
            'proc_module_path' => $this->moduleHandler->getModule('proc')->getPath(),
          ];
        }
      }

      $elements['#attached'] = [
        'library' => [
          0 => 'proc/openpgpjs',
          1 => 'proc/proc-decrypt-list',
        ],
        'drupalSettings' => [
          'proc' => array_merge([
            'proc_labels' => _proc_js_labels(),
            'proc_pass' => $this->procKeyManager->composeProcPassHashForUserId($current_user_id, FALSE, $keyring['created']),
            'proc_privkey' => $privkey,
            'proc_keyring_type' => $keyring['keyring_type'],
          ], $bg_settings, $autonomous_settings),
        ],
      ];

      if (!empty($autonomous_settings)) {
        $elements['#attached']['library'][] = 'proc/proc-sw-register';
      }
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    // We do not need a link to the entity once the content is already being
    // displayed.
    unset($elements['link']);
    return $elements;
  }

}
