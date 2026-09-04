<?php

namespace Drupal\tmgmt_deepl;

use DeepL\Usage;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * DeepL translator UI.
 *
 * @phpstan-consistent-constructor
 */
class DeeplTranslatorUi extends TranslatorPluginUiBase implements ContainerFactoryPluginInterface {

  /**
   * The DeepL translator API interface.
   *
   * @var \Drupal\tmgmt_deepl\DeeplTranslatorApiInterface
   */
  protected DeeplTranslatorApiInterface $deeplApi;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a DeeplTranslatorUi object.
   *
   * TMGMT's TranslatorManager creates UI plugins via
   * `new $class($configuration, $plugin_id, $plugin_definition)`
   * (3 arguments), bypassing ContainerFactoryPluginInterface::create().
   * The three service parameters are therefore optional and fall back to
   * \Drupal::service() when NULL.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\tmgmt_deepl\DeeplTranslatorApiInterface|null $deeplApi
   *   The DeepL translator API service.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $moduleHandler
   *   The module handler service.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, ?DeeplTranslatorApiInterface $deeplApi = NULL, ?MessengerInterface $messenger = NULL, ?ModuleHandlerInterface $moduleHandler = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    $this->deeplApi = $deeplApi ?? \Drupal::service('tmgmt_deepl.api');
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    $this->messenger = $messenger ?? \Drupal::service('messenger');
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    $this->moduleHandler = $moduleHandler ?? \Drupal::service('module_handler');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tmgmt_deepl.api'),
      $container->get('messenger'),
      $container->get('module_handler'),
    );
  }

  /**
   * Overrides TMGMTDefaultTranslatorUIController::pluginSettingsForm().
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Only proceed, if we have an EntityForm.
    if (!$this->isEntityForm($form_state)) {
      return $form;
    }
    /** @var \Drupal\Core\Entity\EntityFormInterface $entity_form */
    $entity_form = $form_state->getFormObject();

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $entity_form->getEntity();
    if (!$translator->isNew()) {
      $this->deeplApi->setTranslator($translator);
      // Show usage information.
      $this->showUsageInfo($this->deeplApi);
    }

    $form['auth_key_entity'] = [
      '#type' => 'key_select',
      '#title' => $this->t('DeepL API authentication key'),
      '#description' => $this->t('If you don\'t have an API key visit <a href="@url" target="_blank">DeepL API registration</a> to create new API key.',
        [
          '@url' => 'https://www.deepl.com/de/pro#developer',
        ]),
      '#key_filters' => ['type' => 'deepl_api_key'],
      '#default_value' => $translator->getSetting('auth_key_entity'),
      '#required' => TRUE,
    ];

    // Additional query options.
    $form['enable_context'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable context for translation'),
      '#description' => $this->t('Include additional context that can influence a translation but is not translated itself. Therefore an additional text field will be shown in checkout form.'),
      '#default_value' => $translator->getSetting('enable_context'),
    ];

    $form['translate_documents'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Translate documents'),
      '#default_value' => $translator->getSetting('translate_documents'),
    ];

    $form['enable_document_minification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use document minification'),
      '#default_value' => $translator->getSetting('enable_document_minification'),
      '#states' => [
        'visible' => [
          [':input[name="settings[translate_documents]"][value="1"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    $omit_partner_id = $translator->getSetting('omit_partner_id');
    $form['omit_partner_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not send the DeepL Partner ID in API requests.'),
      '#description' => $this->t('If this option is enabled, the module will not send the partner identifier <em>@partner_id</em> in each DeepL API request.', [
        '@partner_id' => 'deepl-partner-integration-undpaul',
      ]),
      '#default_value' => $omit_partner_id,
    ];

    // Translation model.
    $form['model_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Translation model'),
      '#options' => [
        'latency_optimized' => $this->t('latency_optimized (default)'),
        'prefer_quality_optimized' => $this->t('prefer_quality_optimized'),
      ],
      '#description' => $this->t('Specifies the type of translation model to use.<ul><li><strong>latency_optimized</strong>: uses lower latency “classic” translation models, which support all language pairs (default)</li><li><strong>prefer_quality_optimized</strong>: prioritizes use of higher latency, improved quality “next-gen” translation models, which support only a subset of DeepL languages; if a request includes a language pair not supported by next-gen models, the request will fall back to latency_optimized classic models (supported only in the Pro v2 API)</li></ul>'),
      '#default_value' => $translator->getSetting('model_type'),
      '#prefix' => '<p>' . $this->t('Please have a look at the <a href="@url" target="_blank">DeepL API documentation</a> for more information on the settings listed below.',
          ['@url' => 'https://www.deepl.com/en/docs-api/']) . '</p>',
    ];

    $form['split_sentences'] = [
      '#type' => 'select',
      '#title' => $this->t('Split sentences'),
      '#options' => [
        '0' => $this->t('No splitting at all, whole input is treated as one sentence'),
        '1' => $this->t('Splits on punctuation and on newlines (default)'),
        'nonewlines' => $this->t('Splits on punctuation only, ignoring newlines'),
      ],
      '#description' => $this->t('Sets whether the translation engine should first split the input into sentences.'),
      '#default_value' => $translator->getSetting('split_sentences'),
      '#required' => TRUE,
    ];

    $form['formality'] = [
      '#type' => 'select',
      '#title' => $this->t('Formality'),
      '#options' => [
        'default' => $this->t('default'),
        'more' => $this->t('more - for a more formal language'),
        'less' => $this->t('less - for a more informal language'),
        'prefer_more' => $this->t('prefer_more - for a more formal language if available, otherwise fallback to default formality'),
        'prefer_less' => $this->t('prefer_less - for a more informal language if available, otherwise fallback to default formality'),
      ],
      '#description' => $this->t('Sets whether the translated text should lean towards formal or informal language. This feature currently only works for target languages <strong>"DE" (German), "FR" (French), "IT" (Italian), "ES" (Spanish), "NL" (Dutch), "PL" (Polish), "PT-PT", "PT-BR" (Portuguese) and "RU" (Russian).</strong> To prevent possible errors while translating, it is recommended to use the <em>prefer_...</em> options.'),
      '#default_value' => $translator->getSetting('formality') !== '' ? $translator->getSetting('formality') : 'default',
    ];

    $form['preserve_formatting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve formatting'),
      '#description' => $this->t('Sets whether the translation engine should preserve some aspects of the formatting, even if it would usually correct some aspects.'),
      '#default_value' => $translator->getSetting('preserve_formatting'),
    ];

    $form['tag_handling'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag handling'),
      '#options' => [
        '0' => $this->t('off'),
        // phpcs:disable
        'xml' => 'xml',
        'html' => 'html',
        // phpcs:enable
      ],
      '#description' => $this->t('Sets which kind of tags should be handled. By default, the translation engine does not take tags into account. For the translation of generic XML content use <a href="@url_xml" target="_blank">xml</a>, for HTML content use <a href="@url_html" target="_blank">html</a>.',
        [
          '@url_xml' => 'https://developers.deepl.com/docs/xml-and-html-handling/xml',
          '@url_html' => 'https://developers.deepl.com/docs/xml-and-html-handling/html',
        ]
      ),
      '#default_value' => $translator->getSetting('tag_handling'),
      '#required' => TRUE,
    ];

    $form['tag_handling_version'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag handling version'),
      '#options' => [
        // phpcs:disable
        'v1' => 'v1',
        'v2' => 'v2',
        // phpcs:enable
      ],
      '#description' => $this->t('Select which version of the tag handling algorithm should be used. In case you are using translation model type <strong>latency_optimized</strong> only version "v1" is working. <a href="@url_html" target="_blank">Further information</a>.',
        [
          '@url' => 'https://developers.deepl.com/docs/xml-and-html-handling/tag-handling-v2',
        ]
      ),
      '#default_value' => $translator->getSetting('tag_handling_version'),
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
          [':input[name="settings[tag_handling]"]' => ['value' => 'html']],
        ],
        'required' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
          [':input[name="settings[tag_handling]"]' => ['value' => 'html']],
        ],
      ],
    ];

    $form['outline_detection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatic outline detection'),
      '#description' => $this->t("The automatic detection of the XML structure won't yield best results in all XML files. You can disable this automatic mechanism altogether by setting the outline_detection parameter to 0 and selecting the tags that should be considered structure tags. This will split sentences using the splitting_tags parameter."),
      '#default_value' => $translator->getSetting('outline_detection'),
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
        ],
      ],
    ];

    $form['splitting_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Splitting tags'),
      '#description' => $this->t('Comma-separated list of XML or HTML tags which always cause splits.'),
      '#default_value' => $translator->getSetting('splitting_tags'),
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
        ],
      ],
    ];

    $form['non_splitting_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Non-splitting tags'),
      '#description' => $this->t('Comma-separated list of XML or HTML tags which never split sentences.'),
      '#default_value' => $translator->getSetting('non_splitting_tags'),
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
        ],
      ],
    ];

    $form['ignore_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ignore tags'),
      '#description' => $this->t('Comma-separated list of XML or HTML tags whose content is never translated.'),
      '#default_value' => $translator->getSetting('ignore_tags'),
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
        ],
      ],
    ];

    // Allow alteration of buildConfigurationForm.
    $this->moduleHandler->alter('tmgmt_deepl_build_configuration_form', $form, $form_state);

    assert(is_array($form));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    // Only proceed, if we have an EntityForm.
    if (!$this->isEntityForm($form_state)) {
      return;
    }

    /** @var array $settings */
    $settings = $form_state->getValue('settings');
    // Reset tag_handling options in case of not selecting xml.
    if ($settings['tag_handling'] !== 'xml') {
      assert(is_array($form['plugin_wrapper']));
      assert(is_array($form['plugin_wrapper']['settings']));
      // Outline detection.
      assert(is_array($form['plugin_wrapper']['settings']['outline_detection']));
      $form_state->setValueForElement($form['plugin_wrapper']['settings']['outline_detection'], 0);

      // Splitting tags.
      assert(is_array($form['plugin_wrapper']['settings']['splitting_tags']));
      $form_state->setValueForElement($form['plugin_wrapper']['settings']['splitting_tags'], '');

      // Non splitting tags.
      assert(is_array($form['plugin_wrapper']['settings']['non_splitting_tags']));
      $form_state->setValueForElement($form['plugin_wrapper']['settings']['non_splitting_tags'], '');

      // Ignore tags.
      assert(is_array($form['plugin_wrapper']['settings']['ignore_tags']));
      $form_state->setValueForElement($form['plugin_wrapper']['settings']['ignore_tags'], '');
    }

    // Check tag_handling_version in case of model type 'latency_optimized'.
    if ($settings['model_type'] === 'latency_optimized' && $settings['tag_handling_version'] === 'v2') {
      assert(is_array($form['plugin_wrapper']));
      assert(is_array($form['plugin_wrapper']['settings']));
      assert(is_array($form['plugin_wrapper']['settings']['tag_handling_version']));
      $form_state->setError($form['plugin_wrapper']['settings']['tag_handling_version'], $this->t('You cannot use Tag handling version to "v2" for model type latency_optimized, please select version "v1".'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job): array {
    // Allow alteration of checkoutSettingsForm.
    $this->moduleHandler->alter('tmgmt_deepl_checkout_settings_form', $form, $job);
    assert(is_array($form));

    // Add a context field if enabled in the translator settings.
    assert($job instanceof JobInterface);
    $translator = $job->getTranslator();

    if ((bool) $translator->getSetting('enable_context')) {
      $form['context'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Translation context'),
        '#description' => $this->t('Provide additional context for translation (e.g., product descriptions, article summaries).'),
        '#default_value' => $job->getSetting('context'),
      ];
    }

    if (count(Element::children($form)) === 0) {
      $form['#description'] = $this->t("The @translator translator doesn't provide any checkout settings.", [
        '@translator' => $translator->label(),
      ]);
    }
    return $form;
  }

  /**
   * Show messages about usage information.
   *
   * @param \Drupal\tmgmt_deepl\DeeplTranslatorApiInterface $deepl_api
   *   The DeepL translator api object.
   */
  protected function showUsageInfo(DeeplTranslatorApiInterface $deepl_api): void {
    $usage_info = $deepl_api->getUsage();
    // Usage data found, but limits were reached.
    if ($usage_info instanceof Usage) {
      if ($usage_info->anyLimitReached()) {
        $this->messenger->addWarning($this->t('The translation limit of your account has been reached.'));
      }
      else {
        $this->messenger->addMessage($this->t('Usage information: :usage_count of :usage_limit characters', [
          ':usage_count' => $usage_info->character->count ?? '',
          ':usage_limit' => $usage_info->character->limit ?? '',
        ]));
      }
    }
  }

  /**
   * Helper to check for EntityForm.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return bool
   *   Whether the form is an EntityForm or not.
   */
  protected function isEntityForm(FormStateInterface $form_state): bool {
    if ($form_state->getFormObject() instanceof EntityFormInterface) {
      return TRUE;
    }
    return FALSE;
  }

}
