<?php

namespace Drupal\proc\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\proc\Element\ProcField;
use Drupal\proc\Entity\Proc;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ProcFieldProcessor service.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcFieldProcessor implements ContainerInjectionInterface {
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;
  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathCurrent;
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;
  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * The key manager.
   *
   * @var \Drupal\proc\ProcKeyManager
   */
  protected $keyManager;
  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new ProcFieldProcessor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Path\CurrentPathStack $path_current
   *   The current path.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\proc\ProcKeyManager $key_manager
   *   The proc key manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    $module_handler,
    $path_current,
    $config_factory,
    $token_service,
    AccountProxyInterface $current_user,
    ProcKeyManager $key_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
  ) {
    $this->moduleHandler = $module_handler;
    $this->pathCurrent = $path_current;
    $this->configFactory = $config_factory;
    $this->tokenService = $token_service;
    $this->currentUser = $current_user;
    $this->keyManager = $key_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('module_handler'),
      $container->get('path.current'),
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('current_user'),
      $container->get('proc.key_manager'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('proc')
    );
  }

  /**
   * Process the proc field.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array|array[]
   *   The processed element.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function processProcField(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $this->moduleHandler->alter('proc_element', $element, $form_state, $complete_form);
    $procElementMetadata = $this->getProcElementMetadata();
    // Get configuration and settings:
    $settings = $this->getSettings($element);
    // If a field is disabled, and it uses a checkbox for encryption, the
    // checkbox must be replaced by a decryption button in case there is a
    // default value. This way, the content remains accessible even if the field
    // is disabled.
    $field_disabled = $this->procFieldIsDisabled($element, $complete_form);
    $default_value = $this->procGetDefaultValue($element);
    $this->addResetButton($element, $settings, $field_disabled, $default_value);
    if ($field_disabled && $default_value) {
      $settings['trigger_format'] = ProcInterface::TRIGGER_FORMAT_BUTTONS;
    }
    $link = $this->generateEncLink(
      $field_disabled,
      $settings,
      $this->generateFetcher(
        $settings,
        $default_value
      ),
      $default_value
    );
    $checkbox = $this->generateSwitchCheckbox($settings);
    $current_user_id = $this->currentUser->id();
    $keyring = $this->keyManager->getKeys($current_user_id, 'user_id');
    $element['target_id']['#attached']['drupalSettings'] = [
      'proc' => [
        'proc_pass' => $this->keyManager->getPrivKeyMetadata(NULL)['proc_pass'],
        'proc_privkey' => $keyring['encrypted_private_key'],
        'proc_keyring_type' => $keyring['keyring_type'],
        'proc_recipients_delivery_mode' => $this->configFactory->get('proc.settings')->get('proc-recipients-delivery-mode') ?? 'csv',
        'proc_cipher_cache_max_entries' => (int) ($this->configFactory->get('proc.settings')->get('proc-cipher-cache-max-entries') ?? 200),
      ],
    ];
    $element['target_id']['#attached']['library'][] = 'proc/openpgpjs';
    $element['target_id']['#attached']['library'][] = 'proc/proc-field';
    if ($settings['submit_element_id']) {
      $element['target_id']['#attached']['drupalSettings']['proc']['submit_element_id'] = $settings['submit_element_id'];
    }

    // Add background re-encryption settings for the web worker.
    $bg_settings = $this->getBackgroundReencryptionFieldSettings();
    if (!empty($bg_settings)) {
      $element['target_id']['#attached']['drupalSettings']['proc'] += $bg_settings;
    }

    // Attach autonomous re-encryption if enabled.
    $autonomous_settings = $this->getAutonomousReencryptionSettings();
    if (!empty($autonomous_settings)) {
      $element['target_id']['#attached']['drupalSettings']['proc'] += $autonomous_settings;
      $element['target_id']['#attached']['library'][] = 'proc/proc-sw-register';
    }
    $decryption_settings = $this->getDecryptionSettings($default_value, $element, $settings);
    if (!empty($decryption_settings['procElementMetadata'])) {
      $procElementMetadata['metadata']['#markup'] = $decryption_settings['procElementMetadata']['metadata']['#markup'];
    }
    $procElementProcOps = [];
    $procElementProcOps['proc-ops'] = $this->assembleDecryptionElements(
      $link,
      $settings,
      $decryption_settings,
      $checkbox
    );
    if (isset($decryption_settings['decryption_link']['#type']) && $decryption_settings['decryption_link']['#type'] == 'link') {
      $checkbox['#attributes']['checked'] = 'checked';
      $procElementProcOps['proc-ops'] = [
        $link,
        $decryption_settings['decryption_link'],
        $checkbox,
      ];
      if ((int) $settings['trigger_format'] === ProcInterface::TRIGGER_FORMAT_BUTTONS) {
        $decryption_settings['decryption_link']['#attributes']['class'] = [$settings['config']->get('proc-encrypt-button-class')];
        $procElementProcOps['proc-ops'] = [$link, $decryption_settings['decryption_link']];
      }
    }
    $this->setFileAutocomplete($element, $settings);
    $this->setTextField($element, $procElementProcOps, $settings, $decryption_settings, $default_value);
    $element['target_id']['#attributes']['proc'] = 'true';
    // If there is a default value, and there is a fetcher_endpoint in the
    // metadata of the proc entity, add the fetcher endpoint as a data attribute
    // to the field.
    $fetcher = NULL;
    if ($element['target_id']['#default_value']) {
      $fetcher = $element['target_id']['#default_value']?->getMeta()[0]['fetcher_endpoint'] ?? NULL;
    }
    if ($default_value && $fetcher) {
      $element['target_id']['#attributes']['data-proc-fetcher'] = $fetcher;
      // Get the current list of recipients:
      $current_recipients = $element['target_id']['#default_value']->get('field_recipients_set')->getValue();
      $recipient_ids = [];
      foreach ($current_recipients as $current_recipient) {
        $recipient_ids[] = $current_recipient['target_id'];
      }
      if (!empty($recipient_ids)) {
        $element['target_id']['#attributes']['data-proc-recipients'] = implode(',', $recipient_ids);
        $element['target_id']['#attributes']['data-proc-id'] = $element['target_id']['#default_value']->id();
      }
      // Get the list of wished recipients, if any:
      $wished_recipients = [];
      $wished_recipient_ids = [];
      if (isset($element['target_id']['#default_value']->toArray()['field_wished_recipients_set'])) {
        $wished_recipients = $element['target_id']['#default_value']->get('field_wished_recipients_set')->getValue();
      }
      foreach ($wished_recipients as $wished_recipient) {
        if (!in_array($wished_recipient['target_id'], $wished_recipient_ids)) {
          $wished_recipient_ids[] = $wished_recipient['target_id'];
        }
      }
      if (!empty($wished_recipient_ids)) {
        $wished_recipient_ids = array_map('intval', $wished_recipient_ids);
        sort($wished_recipient_ids, SORT_NUMERIC);
        $element['target_id']['#attributes']['data-proc-wished-recipients'] = implode(',', $wished_recipient_ids);
      }
    }
    $allow_non_recipients_wished_recipients = (bool) $settings['config']->get('proc-break-chain-of-trust');
    // If user is not a recipient and chain-of-trust bypass is disabled:
    if (empty($decryption_settings) && !$allow_non_recipients_wished_recipients) {
      unset($element['target_id']['#attributes']['proc']);
    }
    $element = $element + $procElementProcOps + $procElementMetadata;
    $element['proc-ops']['#weight'] = 30;
    return $element;
  }

  /**
   * Check if field is disabled.
   *
   * This is used for disabling encrypt operation.
   *
   * @param array $element
   *   The element.
   * @param array $complete_form
   *   The complete form.
   *
   * @return bool
   *   TRUE if field is disabled, FALSE otherwise.
   */
  public function procFieldIsDisabled(array &$element, array &$complete_form): bool {
    if (
      // Disabled by widget settings (Modes of decryption):
      isset($complete_form[$element['#proc-settings']['proc_field_name']]['#disabled']) ||
      // Disabled by form render array:
      isset($element['#disabled'])
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get default value.
   *
   * @param array $element
   *   The element.
   *
   * @return string|null
   *   String with proc ID if field has default value, NULL otherwise.
   */
  public function procGetDefaultValue(array $element): string|null {
    if (empty($element['target_id']['#default_value'])) {
      return NULL;
    }
    $default_value = $element['target_id']['#default_value'];
    if ($default_value instanceof Proc) {
      return $default_value->id();
    }
    return NULL;
  }

  /**
   * Get metadata message.
   *
   * @return array
   *   The metadata message.
   */
  public function getProcElementMetadata(): array {
    return [
      'metadata' => [
        '#type' => 'item',
        '#wrapper_attributes' => ['class' => ['proc-metadata']],
        '#weight' => 20,
      ],
    ];
  }

  /**
   * Get settings.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The settings.
   */
  public function getSettings(array &$element): array {
    if (method_exists($this->configFactory, 'get')) {
      return [
        'current_path' => $this->pathCurrent->getPath(),
        'config' => $this->configFactory->get('proc.settings'),
        'fetcher_endpoint' => $this->tokenService->replace($element['#proc-settings']['fetcher_endpoint']),
        'proc_field_name' => $element['#proc-settings']['proc_field_name'],
        'field_input_mode' => $element['#proc-settings']['field_input_mode'],
        'to_recipients_field_name' => $element['#proc-settings']['to_recipients_field_name'],
        'carbon_copy_recipients_field_name' => $element['#proc-settings']['carbon_copy_recipients_field_name'],
        'preset_recipients_csv' => $element['#proc-settings']['preset_recipients_csv'],
        'fetcher_endpoint_filter' => $element['#proc-settings']['fetcher_endpoint_filter'],
        'fetcher_endpoint_filter_type' => $element['#proc-settings']['fetcher_endpoint_filter_type'],
        'encrypt_button_label' => $element['#proc-settings']['encrypt_button_label'],
        'decrypt_button_label' => $element['#proc-settings']['decrypt_button_label'],
        'cache_password' => $element['#proc-settings']['cache_password'],
        'trigger_format' => $element['#proc-settings']['trigger_format'],
        'submit_element_id' => $element['#proc-settings']['submit_element_id'],
        'trigger_fields' => $element['#proc-settings']['trigger_fields'],
        'proc_field_decryption_mode' => $element['#proc-settings']['proc_field_decryption_mode'] ?? '0',
        'proc_re_encryption_plugin' => $element['#proc-settings']['proc_re_encryption_plugin'] ?? '0',
        'proc_recipients_delivery_override' => $element['#proc-settings']['proc_recipients_delivery_override'] ?? '',
      ];
    }
    return [];

  }

  /**
   * Generate fetcher prefix.
   *
   * @param array $settings
   *   The settings.
   * @param string $default_value
   *   The default value.
   *
   * @return string
   *   The fetcher value.
   */
  private function generateFjsPrefix(array $settings, null|string $default_value): string {
    return <<<JS
  let to_recipients_collection = [];
  let cc_recipients_collection = [];
  let pre_set_recipients_csv = "{$settings['preset_recipients_csv']}";
  let fetcher_endpoint = "{$settings['fetcher_endpoint']}";
  let fetcher_endpoint_filter = "{$settings['fetcher_endpoint_filter']}";
  let fetcher_endpoint_filter_type = "{$settings['fetcher_endpoint_filter_type']}";
  let proc_recipients_delivery_override = "{$settings['proc_recipients_delivery_override']}";
  let recipients = [];
  let default_value = "{$default_value}";


JS;
  }

  /**
   * Generate direct fetcher.
   *
   * @param array $settings
   *   The settings.
   * @param string $type
   *   The type of recipients ('to' or 'cc').
   *
   * @return string
   *   The JS fetcher.
   */
  private static function generateDirectFetcher(array $settings, string $type): string {
    // @todo Make this agnostic for allowing any number of user reference
    // fields.
    $recipients = [
      'to' => ['collection' => 'to_recipients_collection', 'field_name' => 'to_recipients_field_name'],
      'cc' => ['collection' => 'cc_recipients_collection', 'field_name' => 'carbon_copy_recipients_field_name'],
    ];

    // Fallback to 'to' if the type is not 'to' or 'cc'.
    $type = $recipients[$type] ?? $recipients['to'];

    $variable = $type['collection'];
    $settings_key = $type['field_name'];

    return !empty($settings[$settings_key]) ? <<<JS
{$variable} = Object.values(document.querySelectorAll("input[name^='{$settings[$settings_key]}'], select[name^='{$settings[$settings_key]}']"));

JS : '';
  }

  /**
   * Generate the fetcher value.
   *
   * @param null|string $fetcher_endpoint
   *   The endpoint for fetching recipients.
   * @param string $field_name
   *   The proc field name.
   *
   * @return string
   *   The fetcher value.
   */
  private function generateFetcherValue(null|string $fetcher_endpoint, string $field_name): string {

    $proc_build_path_fn = <<<JS
async function procBuildPath(ids_csv, proc_path_prefix_add, proc_path_suffix, fetcherEndpointSuffix) {
  // Widget override takes precedence: if set to 'csv', always use CSV mode.
  const widgetOverride = (typeof proc_recipients_delivery_override !== 'undefined') ? proc_recipients_delivery_override : '';
  const deliveryMode = widgetOverride === 'csv' ? 'csv' : (drupalSettings.proc.proc_recipients_delivery_mode || 'csv');
  if (deliveryMode === 'payload' && ids_csv !== '') {
    // Payload mode: POST recipients to server, get token.
    const idsArray = ids_csv.split(',').map(Number).filter(n => n > 0);
    const tokenUrl = window.location.origin + drupalSettings.path.baseUrl + 'api/proc/store-recipients';
    const csrfResponse = await fetch(window.location.origin + drupalSettings.path.baseUrl + 'session/token', { credentials: 'same-origin' });
    const csrfToken = await csrfResponse.text();
    const storeResponse = await fetch(tokenUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ recipients: idsArray }),
    });
    const storeData = await storeResponse.json();
    if (storeData.token) {
      return proc_path_prefix_add + '/' + storeData.token + proc_path_suffix + (fetcherEndpointSuffix || '');
    }
  }
  // CSV mode (legacy):
  return proc_path_prefix_add + '/' + ids_csv + proc_path_suffix + (fetcherEndpointSuffix || '');
}

JS;

    $fetcher_value = $proc_build_path_fn . <<<JS
let proc_path = await procBuildPath(ids_csv, proc_path_prefix_add, proc_path_suffix, '');

JS;
    $ajax_settings = $this->generateAjaxSettings($field_name);
    $fetcher_value = $fetcher_value . $ajax_settings;

    if (!empty($fetcher_endpoint)) {
      $fetcher_value = $proc_build_path_fn . <<<JS
// Fetcher endpoint CSV:
let fetcher_endpoint_csv = "";
let endpoint_response;
let dynamic_endpoint_filter;
let dynamic_endpoint_filter_prefix;
let encodedUrl = "";

if (fetcher_endpoint_filter !== "") {
  // Turn fetcher_endpoint_filter CSV into an array:
  const filter_element_identifiers = fetcher_endpoint_filter.split(",");
  let filter_element_values = [];
  const identifier_type_string = fetcher_endpoint_filter_type === '0' ? 'id' : 'name';
  filter_element_identifiers.map((fieldIdentifier) => {
    // Get the value of the field:
    const selector = '[' + identifier_type_string + '^="' + fieldIdentifier + '"]';
    const field_element = document.querySelector('input' + selector) || document.querySelector('select' + selector);
    const field_value = field_element.value;
    filter_element_values.push(fieldIdentifier + "=" + field_value);
  });
  dynamic_endpoint_filter = filter_element_values.join("&");
  dynamic_endpoint_filter_prefix = "?";
}
if (fetcher_endpoint !== "") {
  if (dynamic_endpoint_filter) {
    fetcher_endpoint = fetcher_endpoint + dynamic_endpoint_filter_prefix + dynamic_endpoint_filter;
  }
  console.info("Fetching recipients from endpoint: " + fetcher_endpoint);
  encodedUrl = encodeURIComponent(fetcher_endpoint);

  endpoint_response = fetch(fetcher_endpoint, {
    method: "get"
  }).then(async function(response) {
    return response.json();
  }).catch(async function(err) {
    console.info("Error.");
  });
}
let endpoint_recipients_csv = endpoint_response.then(async function(result) {
  if (typeof result === 'object') {
    result = Object.values(result);
  }
  // Suppose this is a flat list of IDs:
  let result_map = result.map(item => item.id);
  if (!result_map[0]) {
    // Use 'uid' as standard UIDs key:
    return result.map(obj => obj['uid']).filter(value => value !== undefined).join();
  }
  return result_map.join();
});

endpoint_recipients_csv.then(async function(result) {
  if (result !== "") {
    var separator = "";
    if (ids_csv !== "") {
      separator = ",";
    }
    ids_csv = ids_csv + separator + result;

    let proc_path = await procBuildPath(ids_csv, proc_path_prefix_add, proc_path_suffix, "&fetcher_endpoint=" + encodedUrl);

JS;
      $fetcher_value = $fetcher_value . $ajax_settings . <<<JS
return false;
}
});
JS;
    }
    return $fetcher_value;
  }

  /**
   * Generate ajax settings.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The ajax settings.
   */
  private function generateAjaxSettings(string $field_name): string {
    return <<<JS
let ajaxSettings = {
  url: proc_path,
  dialogType: "dialog",
  dialog: { width: 400, classes: { "ui-dialog": "class-{$field_name} proc-encrypt-dialog" } },
};

let myAjaxObject = Drupal.ajax(ajaxSettings);
myAjaxObject.execute();
JS;
  }

  /**
   * Generate the direct fetcher.
   *
   * @param array $settings
   *   The settings.
   * @param string $value
   *   The value.
   *
   * @return string
   *   The JS fetcher.
   */
  private function generateDfetcher(array $settings, string $value): string {
    return <<<JS
jQuery(self).attr("href", "#");

// Check if this is an autocomplete deluxe field:
if (to_recipients_collection.length) {
  if (to_recipients_collection[0].value === "" && to_recipients_collection[1].value.includes('""""')) {
    // This is an autocomplete deluxe field. Get values from the second element.
    let autocomplete_deluxe_recipients_string;
    if (cc_recipients_collection.length) {
      autocomplete_deluxe_recipients_string = to_recipients_collection[1].value + cc_recipients_collection[1].value;
    }
    else {
      autocomplete_deluxe_recipients_string = to_recipients_collection[1].value;
    }

    const regex = /\((\d+)\)/g;
    const matches = autocomplete_deluxe_recipients_string.match(regex);
    recipients = matches.map((match) => parseInt(match.match(/\d+/)));

  }
  else {
    // Suppose this is a regular autocomplete field:
    let recipients_length;
    if (cc_recipients_collection.length) {
      recipients_length = to_recipients_collection.length + cc_recipients_collection.length;
    }
    else {
      recipients_length = to_recipients_collection.length;
    }
    if (recipients_length > 0) {
      let recipients_collection = to_recipients_collection.concat(cc_recipients_collection);
      recipients_collection.forEach(function (recipient, index) {
        if (index < recipients_length) {
          if (recipient.value.match(/\(\d+\)/)) {
            let id_parenthesis = recipient.value.match(/\(\d+\)/)[0];
            recipients.push(id_parenthesis.substring(1, id_parenthesis.length - 1));
          }
        }
      });
    }
  }
}

let proc_path_prefix_add = window.location.origin + drupalSettings.path.baseUrl + "proc/add";
let ids_csv = recipients.join();

// Concatenate predefined recipients:
if (pre_set_recipients_csv !== "") {
  ids_csv = ids_csv ? ids_csv + ',' + pre_set_recipients_csv : pre_set_recipients_csv;
}

let proc_field_name = "{$settings['proc_field_name']}";
let parent_id = document.querySelector("input[name^='" + proc_field_name + "']").id;

let field_input_mode = {$settings['field_input_mode']};

let dashed_proc_field_name = proc_field_name.replace(/_/g, "-");
let delta = self.getAttribute('data-drupal-selector').replace(/-proc-ops-.+$/, "").replace(/^.+-/, "");

let proc_path_suffix =
  "?proc_standalone_mode=FALSE&proc_form_triggering_field=" + parent_id +
  "&proc_field_delta=" + delta +
  "&proc_field_name={$settings['proc_field_name']}&proc_in_mode=" + field_input_mode +
  "&field-default-proc-id=" + default_value +
  "&proc_re_encryption_plugin={$settings['proc_re_encryption_plugin']}" +
  "&host_page_url={$settings['current_path']}";

{$value}
return false;
JS;

  }

  /**
   * Generate the fetcher.
   *
   * @param array $settings
   *   The settings.
   * @param null|string $default_value
   *   The default value.
   *
   * @return string
   *   The JS fetcher.
   */
  private function generateFetcher(array $settings, null|string $default_value): string {
    $direct_fetcher_js = $this->generateDfetcher(
      $settings,
      $this->generateFetcherValue($settings['fetcher_endpoint'], $settings['proc_field_name'])
    );
    return $this->generateFjsPrefix($settings, $default_value) . $this->generateDirectFetcher($settings, 'to') . $this->generateDirectFetcher($settings, 'cc') . $direct_fetcher_js;
  }

  /**
   * Generate the encryption link.
   *
   * @param bool $field_disabled
   *   The field disabled flag.
   * @param array $settings
   *   The settings.
   * @param string $fetcher
   *   The fetcher.
   * @param null|string $default_value
   *   The default value.
   *
   * @return array
   *   The link.
   */
  private function generateEncLink(bool $field_disabled, array $settings, string $fetcher, null|string $default_value): array {
    $link = [];
    if (!$field_disabled) {
      $label = ($settings['encrypt_button_label']) ?: $settings['config']->get('proc-encrypt-button-label') ?: $this->t('Encrypt');
      // Check if field is filled for applying conditional encrypt button label:
      if ($default_value) {
        $label = ($settings['config']->get('proc-encrypt-button-label-filled-field')) ?: $label;
      }

      $link = $this->generateOnClickActionLink($label, $fetcher, 'encrypt', $settings);

    }

    return $link;

  }

  /**
   * The encryption/decryption switch checkbox for text fields.
   *
   * @param array $settings
   *   The settings.
   *
   * @return array
   *   The checkbox array.
   */
  private function generateSwitchCheckbox(array $settings): array {
    $checkbox_onclick = <<<JS
if (this.checked) {
  document.getElementById('encrypt-{$settings['proc_field_name']}').click();
  this.value = 1;
}
else {
  // Do not try to decrypt if there is no default value and instead bring back
  // the data-proc-plaintext attribute:
  let fieldMachineName = this.getAttribute('name').replace(/\[.+/,'');
  const input_element = document.querySelector('[name^={$settings['proc_field_name']}]');
  if (input_element.hasAttribute('data-proc-plaintext')) {
    const plaintext = input_element.getAttribute('data-proc-plaintext');
    // Remove the input clone:
    input_element.parentNode.removeChild(input_element.nextElementSibling);
    const input_original = document.querySelector('[name^={$settings['proc_field_name']}]');
    input_original.value = plaintext;
    input_original.style.display = 'block';
  }
  else {
    document.getElementById('decrypt-{$settings['proc_field_name']}').click();
  }
}
JS;
    // Create the checkbox for encryption.
    return [
      '#type' => 'checkbox',
      '#title' => $this->t('Encrypt'),
      '#default_value' => 0,
      '#id' => 'encrypt-checkbox-' . $settings['proc_field_name'],
      '#attributes' => [
        'onclick' => $checkbox_onclick,
        'value' => 0,
      ],
    ];

  }

  /**
   * Generate the decryption dialog.
   *
   * @param bool $curr_recipient
   *   The current recipient flag.
   * @param mixed $proc_id
   *   The proc ID.
   * @param array $settings
   *   The settings.
   * @param string $decryption_mode
   *   The decryption mode.
   *
   * @return string
   *   The JS dialog for decryption.
   */
  private function generateDecryptionDialog(bool $curr_recipient, mixed $proc_id, array $settings, string $decryption_mode): string {
    if ($curr_recipient) {
      $dialog_decrypt_js = <<<JS
  let proc_path_prefix_add = window.location.origin + drupalSettings.path.baseUrl + "proc/{$proc_id}";

  let proc_field_name = "{$settings['proc_field_name']}";
  let parent_id = document.querySelector("input[name^='" + proc_field_name + "']").id;

  let field_input_mode = {$settings['field_input_mode']};
  let field_decryption_mode = {$decryption_mode};
  const cache_password = {$settings['cache_password']};

  let proc_path_suffix =
    "?proc_standalone_mode=FALSE&proc_cache_password_mode=" + cache_password +
    "&proc_form_triggering_field=" + parent_id +
    "&proc_field_name={$settings['proc_field_name']}&proc_in_mode=" + field_input_mode +
    "&proc_decryption_mode=" + field_decryption_mode +
    "&proc_decryption_trigger_fields={$settings['trigger_fields']}" +
    "&proc_reencryption_plugin={$settings['proc_re_encryption_plugin']}" +
    "&host_page_url={$settings['current_path']}";

  let proc_path = proc_path_prefix_add + "/" + proc_path_suffix;

  jQuery(this).attr("href", "#");
JS;
      $dialog_decrypt_js .= $this->generateAjaxSettings($settings['proc_field_name']);
      $dialog_decrypt_js .= <<<JS
  return false;
JS;
      return $dialog_decrypt_js;
    }

    return <<<JS
  let proc_field_name = "{$settings['proc_field_name']}";
  let triggering_field = document.querySelector("input[name^='" + proc_field_name + "']");
  triggering_field.value = '';
  triggering_field.removeAttribute("disabled");
  return false;
JS;
  }

  /**
   * Generate the decryption dialog URL.
   *
   * @param array $settings
   *   The settings.
   * @param string $dialog_decrypt_js
   *   The JS dialog for decryption.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  private function generateDialogDecUrl(array $settings, string $dialog_decrypt_js): Url {
    $dec_link_class = [$settings['config']->get('proc-encrypt-button-class')];
    if ((int) $settings['trigger_format'] === ProcInterface::TRIGGER_FORMAT_CHECKBOX) {
      $dec_link_class = ['hidden'];
    }
    $url = new Url('<none>', [], ['fragment' => '']);
    $url->setOption('attributes', [
      'onclick' => $dialog_decrypt_js,
      'class' => array_merge($dec_link_class, [
        'proc-link',
        'proc-decrypt-link',
      ]),
      'id' => 'decrypt-' . $settings['proc_field_name'],
    ]);
    return $url;

  }

  /**
   * Check if current user is a recipient.
   *
   * @param array $element
   *   The element.
   *
   * @return string|bool
   *   The current user recipient or FALSE.
   */
  private function getCurrentUserRecipient(array $element): string|bool {
    $current_user_id = $this->currentUser->id();
    if (!in_array($current_user_id, array_column(
      $element['target_id']['#default_value']->get('field_recipients_set')->getValue(),
      'target_id'))
    ) {
      return FALSE;
    }
    return $current_user_id;
  }

  /**
   * Get decryption settings.
   *
   * @param null|string $default_value
   *   The default value.
   * @param array $element
   *   The element.
   * @param array $settings
   *   The settings.
   *
   * @return array
   *   The decryption settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getDecryptionSettings(null|string $default_value, array $element, array $settings): array {
    // If decryption is disabled, return empty:
    if ($settings['proc_field_decryption_mode'] == ProcInterface::DECRYPT_MODE_DISABLED) {
      return [];
    }
    $decryption_link = [];
    $procElementMetadata = NULL;
    // If there is a default value, add also the Decrypt button:
    if ($default_value && $element['target_id']['#default_value'] instanceof Proc) {
      $label = $element['target_id']['#default_value']->get('label')->getValue()[0]['value'];
      $curr_recipient = $this->getCurrentUserRecipient($element);
      if (!$curr_recipient) {
        return [];
      }

      $dec_link_label = ($settings['decrypt_button_label']) ?: $this->t('Decrypt');

      $url = new Url('<none>', [], ['path' => '/proc/' . $default_value]);
      $decryption_link = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $dec_link_label,
      ];
      $decryption_link['#attributes']['id'] = 'decrypt-' . $settings['proc_field_name'];

      // If decryption has to happen in a dialog:
      if ($settings['proc_field_decryption_mode'] == ProcInterface::DECRYPT_MODE_DIALOG) {
        $decryption_link['#url'] = $this->generateDialogDecUrl(
          $settings,
          $this->generateDecryptionDialog($curr_recipient, $default_value, $settings, $settings['proc_field_decryption_mode'])
        );
      }
      // If decryption has to happen in a new page, make the decryption link to
      // point to proc/<proc ID> and set the target _blank:
      if ($settings['proc_field_decryption_mode'] == ProcInterface::DECRYPT_MODE_NEW_PAGE) {
        $decryption_link['#url'] = Url::fromUserInput('/proc/' . $default_value);
        $decryption_link['#attributes']['target'] = '_blank';
        $decryption_link['#attributes']['rel'] = 'noopener';
      }
      if ($settings['proc_field_decryption_mode'] == ProcInterface::DECRYPT_MODE_INLINE) {
        $decryption_link['#url'] = Url::fromUri('internal:#');
        $decryption_link['#attributes']['data-drupal-proc-inline-decryption'] = $default_value;
        $proc_entity = $this->entityTypeManager->getStorage('proc')->load($default_value);
        $decryption_link['#attributes']['data-drupal-proc-inline-decryption-changed'] = $proc_entity?->get('changed')->getValue()[0]['value'] ?? 0;
        $decryption_link['#attributes']['data-drupal-proc-inline-decryption-item-type'] = $proc_entity?->getMeta()[0]['source_file_type'];
        $decryption_link['#attributes']['data-drupal-proc-inline-decryption-item-name'] = $proc_entity?->getMeta()[0]['source_file_name'] ?? '';
      }
      if ($settings['field_input_mode'] != ProcInterface::INPUT_MODE_TEXTFIELD) {
        $procElementMetadata['metadata']['#markup'] = (new ProcField(
          $this->pathCurrent,
          $this->configFactory,
          $this->tokenService,
          $this
        ))->getMetadataMessage($element['target_id']['#default_value']);
      }
    }
    return [
      'label' => $label ?? '',
      'current_user_recipient' => $curr_recipient ?? NULL,
      'decryption_link' => $decryption_link,
      'procElementMetadata' => $procElementMetadata ?? '',
    ];
  }

  /**
   * Get text field attributes.
   *
   * @param object $proc_entity
   *   The proc entity.
   *
   * @return array
   *   The text field attributes.
   */
  private function getTextFieldAttributes(object $proc_entity): array {
    $proc_id = $proc_entity->id();
    return [
      'data-proc-list-item-cipher-text' => $proc_entity->getCipherText(),
      'data-proc-list-item-pid' => $proc_id,
      'class' => ['proc-armoured-field-formatter'],
      'data-proc-list-item' => 'proc-list-item-' . $proc_id,
      'data-proc-list-item-type' => $proc_entity->getMeta()[0]['source_file_type'],
    ];
  }

  /**
   * Attach decryption libraries.
   *
   * @param array $element
   *   The element.
   * @param int $current_user_id
   *   The current user ID.
   */
  private function attachDecryptionLibraries(array &$element, int $current_user_id): void {
    try {
      $keyring = $this->keyManager->getKeys($current_user_id, 'user_id');
      $element['#attached'] = [
        'library' => ['proc/openpgpjs', 'proc/proc-decrypt-list'],
        'drupalSettings' => [
          'proc' => [
            'proc_labels' => _proc_js_labels(),
            'proc_pass' => $this->keyManager->getPrivKeyMetadata(NULL)['proc_pass'],
            'proc_privkey' => $keyring['encrypted_private_key'],
            'proc_keyring_type' => $keyring['keyring_type'],
          ],
        ],
      ];
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error('Error retrieving proc keyring: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Assemble decryption elements.
   *
   * @param array $link
   *   The link.
   * @param array $settings
   *   The settings.
   * @param array $decryption_settings
   *   The decryption settings.
   * @param array $checkbox
   *   The checkbox.
   *
   * @return array
   *   The assembled decryption element.
   */
  private function assembleDecryptionElements(array $link, array $settings, array $decryption_settings, array $checkbox): array {
    $desc_proc_elem = [$link];
    if ((int) $settings['trigger_format'] === ProcInterface::TRIGGER_FORMAT_CHECKBOX && !empty($decryption_settings)) {
      $desc_proc_elem = [
        $link,
        $decryption_settings['decryption_link'],
        $checkbox,
      ];
    }

    return $desc_proc_elem;

  }

  /**
   * Set text field.
   *
   * @param array $element
   *   The element.
   * @param array $procElementProcOps
   *   The proc element proc ops.
   * @param array $settings
   *   The settings.
   * @param array $decryption_settings
   *   The decryption settings.
   * @param null|string $default_value
   *   The default value.
   */
  private function setTextField(array &$element, array &$procElementProcOps, array $settings, array $decryption_settings, null|string $default_value): void {

    // If this is an encrypted text field:
    if ($settings['field_input_mode'] == ProcInterface::INPUT_MODE_TEXTFIELD) {
      $element['target_id']['#type'] = 'textfield';
      // Do not show decryption button if the field is disabled. The content
      // is already being decrypted within the field. There is no need of having
      // a button.
      if (isset($element['#disabled'])) {
        $procElementProcOps['proc-ops'] = [];
      }

      $proc_object = FALSE;
      if ($default_value) {
        $proc_object = $element['target_id']['#default_value'];
        $element['target_id']['#disabled'] = TRUE;
      }

      // If current user is not a recipient we still want to display the
      // asterisks as a marker for the presence of encrypted content. This is
      // also going to be used for keeping the entity reference value in the
      // form state. See ProcEntityReferenceWidget->validate.
      $element['target_id']['#value'] = $decryption_settings['label'] ?? '*******';

      // Add hidden field for proc_id:
      $procElementProcOps['proc-ops'] = [$procElementProcOps['proc-ops']];
      $element['target_id']['#default_value'] = $proc_object ?? '';

      // Add ciphertext if current user is a recipient:
      if (!empty($element['target_id']['#value']) && isset($decryption_settings['current_user_recipient'])) {
        $element['target_id']['#attributes'] = $this->getTextFieldAttributes($element['target_id']['#default_value']);
        $this->attachDecryptionLibraries($element, $decryption_settings['current_user_recipient']);
      }
    }
  }

  /**
   * Generate the Reset button.
   *
   * @param array $element
   *   The element.
   * @param array $settings
   *   The settings.
   *
   * @return array
   *   The Reset button.
   */
  private function generateResetAction(array $element, array $settings): array {
    $js_script = <<<JS
jQuery('[name^={$element['#proc-settings']['proc_field_name']}]')[0].value = '';
return false;
JS;
    $reset_label = $settings['config']->get('proc-single-value-remove-button-label') ?: $this->t('Remove');
    return $this->generateOnClickActionLink($reset_label, $js_script, 'reset', $settings);
  }

  /**
   * Generate the OnClick action link.
   *
   * @param string $label
   *   The label of the link.
   * @param string $js_script
   *   The JS script.
   * @param string $action_name
   *   The action name.
   * @param array $settings
   *   The settings.
   *
   * @return array
   *   The link.
   */
  private function generateOnClickActionLink(string $label, string $js_script, string $action_name, array $settings): array {
    // Create the Encrypt button link.
    $encrypt_button_class = isset($settings['config']) ? [$settings['config']->get('proc-encrypt-button-class')] : [];
    if ((int) $settings['trigger_format'] === ProcInterface::TRIGGER_FORMAT_CHECKBOX) {
      $encrypt_button_class = ['hidden'];
    }

    $url = new Url('<none>', [], ['fragment' => '']);
    $url->setOption('attributes', [
      'onclick' => 'event.preventDefault();(async (self) => {' . $js_script . '})(this)',
      'id' => $action_name . '-' . $settings['proc_field_name'],
    ]);

    return [
      '#type' => 'link',
      '#title' => $label,
      '#url' => $url,
      '#attributes' => [
        'class' => array_merge($encrypt_button_class, ['proc-link']),
        'name' => $action_name . '-' . $settings['proc_field_name'],
        'id' => $action_name . '-' . $settings['proc_field_name'],
      ],
      '#weight' => 40,
    ];
  }

  /**
   * Disable autocomplete for non-admin users by default.
   *
   * @param array $element
   *   The element.
   * @param array $settings
   *   The settings.
   */
  private function setFileAutocomplete(array &$element, array $settings): void {
    $enable_autocomp = isset($settings['config']) ? $settings['config']->get('proc-file-enable-autocomplete') : 0;

    if (
      $settings['field_input_mode'] == 0 &&
      !$this->currentUser->hasPermission('administer proc configuration') &&
      !$enable_autocomp
    ) {
      $element['target_id']['#attributes']['readonly'] = TRUE;
      $element['target_id']['#attributes']['class'] = ['form-autocomplete-proc-read-only'];
    }
  }

  /**
   * Add Reset button.
   *
   * @param array $element
   *   The element.
   * @param array $settings
   *   The settings.
   * @param bool $field_disabled
   *   The field disabled flag.
   * @param null|string $default_value
   *   The default value.
   */
  private function addResetButton(array &$element, array $settings, bool $field_disabled, null|string $default_value): void {
    // If this is single valued field and has a default value, and the field
    // is not disabled, add a Reset button:
    if (!isset($settings['config'])) {
      return;
    }
    if (
      $element['#proc-settings']['proc_field_cardinality'] == 1 &&
      $default_value &&
      $settings['config']->get('proc-single-value-remove-button') &&
      !isset($element['#disabled'])
    ) {
      $reset_link = $this->generateResetAction($element, $settings);
      $element['reset'] = $reset_link;
    }
  }

  /**
   * Get background re-encryption settings for field widget drupalSettings.
   *
   * @return array
   *   Array of settings for background re-encryption, empty if disabled.
   */
  private function getBackgroundReencryptionFieldSettings(): array {
    $config = $this->configFactory->get('proc.settings');

    // Check global toggle.
    if (!$config->get('proc-enable-background-reencryption')) {
      return [];
    }

    $limit = (int) ($config->get('proc-background-update-jobs-limit') ?? 2);
    if ($limit <= 0) {
      return [];
    }

    $uid = (int) $this->currentUser->id();
    $updateJobsCount = 0;
    try {
      /** @var \Drupal\proc\Service\ProcUpdateJobsCountService $countService */
      $countService = \Drupal::service('proc.update_jobs_count_service');
      $updateJobsCount = $countService->getCountForUser($uid);
    }
    catch (\Exception $e) {
      $this->logger->error('Background re-encryption settings failed for user @uid: @error', [
        '@uid' => $uid,
        '@error' => $e->getMessage(),
      ]);
    }

    if ($updateJobsCount <= 0) {
      return [];
    }

    return [
      'proc_background_update_jobs_limit' => $limit,
      'proc_background_update_jobs_count' => $updateJobsCount,
      'proc_module_path' => $this->moduleHandler->getModule('proc')->getPath(),
    ];
  }

  /**
   * Get autonomous re-encryption settings for drupalSettings.
   *
   * @return array
   *   Array with autonomous re-encryption settings, empty if disabled.
   */
  private function getAutonomousReencryptionSettings(): array {
    $config = $this->configFactory->get('proc.settings');
    if (!$config->get('proc-enable-autonomous-reencryption')) {
      return [];
    }

    // The autonomous system needs the pending-jobs count, a jobs limit, and the
    // module path independently of the decryption-triggered system's toggle.
    $uid = (int) $this->currentUser->id();
    $updateJobsCount = 0;
    try {
      /** @var \Drupal\proc\Service\ProcUpdateJobsCountService $countService */
      $countService = \Drupal::service('proc.update_jobs_count_service');
      $updateJobsCount = $countService->getCountForUser($uid);
    }
    catch (\Exception $e) {
      $this->logger->error('Autonomous re-encryption settings failed for user @uid: @error', [
        '@uid' => $uid,
        '@error' => $e->getMessage(),
      ]);
    }

    if ($updateJobsCount <= 0) {
      return [];
    }

    return [
      'proc_autonomous_reencryption_enabled' => TRUE,
      'proc_autonomous_reencryption_cooldown' => (int) ($config->get('proc-autonomous-reencryption-cooldown') ?? 300),
      'proc_background_update_jobs_limit' => (int) ($config->get('proc-background-update-jobs-limit') ?? 2),
      'proc_background_update_jobs_count' => $updateJobsCount,
      'proc_module_path' => $this->moduleHandler->getModule('proc')->getPath(),
    ];
  }

}
