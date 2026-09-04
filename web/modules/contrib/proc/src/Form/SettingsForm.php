<?php

namespace Drupal\proc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\proc\ProcInterface;
use Drupal\proc\Service\CssIdentifierCleanerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Protected Content settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The stream_wrapper_manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * The CSS identifier cleaner service.
   *
   * @var \Drupal\proc\Service\CssIdentifierCleanerInterface
   */
  protected CssIdentifierCleanerInterface $cssIdentifierCleaner;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager service.
   * @param \Drupal\proc\Service\CssIdentifierCleanerInterface $cssIdentifierCleaner
   *   The CSS identifier cleaner service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StreamWrapperManagerInterface $streamWrapperManager,
    CssIdentifierCleanerInterface $cssIdentifierCleaner,
    TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->setConfigFactory($config_factory);
    $this->streamWrapperManager = $streamWrapperManager;
    $this->cssIdentifierCleaner = $cssIdentifierCleaner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('proc.css_identifier_cleaner'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['proc-stream-wrapper'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Global stream wrapper'),
      '#description' => $this->t('Set a stream wrapper for the storage of cipher texts.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-stream-wrapper'),
    ];
    $form['proc-enable-stream-wrapper'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable stream wrapper globally'),
      '#description' => $this->t('Enable stream wrapper storage of cipher texts.'),
      '#default_value' => 1,
      '#disabled' => 'disabled',
    ];
    $form['proc-rsa-key-size'] = [
      '#type' => 'select',
      '#title' => $this->t('RSA keys size'),
      '#options' => [
        '2048' => $this->t('2048'),
        '4096' => $this->t('4096'),
      ],
      '#empty_option' => $this->t('-select-'),
      '#description' => $this->t('Set the RSA key size.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-rsa-key-size'),
    ];
    $form['proc-file-entity-max-filesize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum size for encryption in bytes'),
      '#description' => $this->t('Set the maximum size in bytes allowed for encryption. Default if left empty: 50000000 bytes (50 megabytes).'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-file-entity-max-filesize'),
    ];
    $form['proc-file-block-size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Block size (lines of armored cipher text)'),
      '#description' => $this->t('Set the size in number of armored cipher text lines for storage blocks. Leave it empty for unlimited. Warning: if there are too few lines and a too big file, there is a risk of time out.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-file-block-size'),
    ];
    $form['proc-enable-block-size'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable block size limit'),
      '#description' => $this->t('Enable block size limit. Cipher texts will be split by block. This setting only has effect with stream wrapper storage on.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-enable-block-size'),
    ];
    $form['proc-encrypt-button-label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Global standard label for the encrypt link'),
      '#description' => $this->t('The label for attaching an encrypted file. This configuration is overwritten by the widget label on encrypted file fields.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-encrypt-button-label'),
    ];
    $form['proc-encrypt-button-label-filled-field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Global label for encryption on a file field when there is a default value.'),
      '#description' => $this->t('The label for creating an encrypted file when the field is filled. Leave it empty for using the standard label.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-encrypt-button-label-filled-field'),
    ];
    $form['proc-encrypt-button-class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Classes for the encrypt link'),
      '#description' => $this->t('The CSS class for attaching an encrypted file. Example: button encrypt-button'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-encrypt-button-class'),
    ];
    $form['proc-decrypt-button-class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Classes for the decrypt/re-encrypt link'),
      '#description' => $this->t('The CSS classes for decrypt and re-encryption links. Example: button decrypt-button'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-decrypt-button-class'),
    ];
    $form['proc-allow-password-cache-fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable local password cache for fields'),
      '#description' => $this->t('Add an option on decryption for allowing the password to be reused while being cached at client side.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-allow-password-cache-fields'),
    ];
    $form['proc-allow-password-cache-standalone'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable local password cache in stand-alone mode'),
      '#description' => $this->t('When enabled, the passphrase is automatically cached in the browser session after a successful decryption on the stand-alone decryption page or its modal variant. Subsequent decryptions on the same session will not require re-entering the password.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-allow-password-cache-standalone'),
    ];
    $form['proc-auto-filter-recipients'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption strategy'),
      '#description' => $this->t('<p><strong>Restrictive</strong> encryption strategy requires the existence of valid PGP key for each and every recipient of a protected content.<br/>When using the restrictive encryption strategy, protected content can only be created when all recipients have a valid PGP key.</p><p><strong>Permissive</strong> encryption strategy filters-out from encryption recipients all users without valid PGP key.<br/>When using the permissive encryption strategy, protected content is created skipping any user from the list of recipients that still has no valid PGP key.</p>'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-auto-filter-recipients'),
      '#options' => [
        ProcInterface::STRATEGY_RESTRICTIVE => $this->t('Restrictive'),
        ProcInterface::STRATEGY_PERMISSIVE => $this->t('Permissive'),
      ],
    ];
    $form['proc-suppress-decrypt-link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress decrypt link from encryption success message'),
      '#description' => $this->t('Decrypt link will be registered in system log but not displayed to users.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-suppress-decrypt-link'),
    ];
    $form['proc-suppress-encryption-success-msg'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress entire encryption success message.'),
      '#description' => $this->t('Encryption procedure will be registered in system log but not displayed to users.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-suppress-encryption-success-msg'),
    ];
    $form['proc-single-value-remove-button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add <em>Remove</em> button to single-valued encrypted file fields'),
      '#description' => $this->t('The button will reset the field value.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-single-value-remove-button'),
    ];
    $form['proc-single-value-remove-button-label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the <em>Remove</em> button'),
      '#description' => $this->t('Leave it empty for using the default label: Remove.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-single-value-remove-button-label'),
    ];
    $form['proc-file-enable-autocomplete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable autocomplete in file fields for non-admin users'),
      '#description' => $this->t('Administrators (or any role with permission <em>administer proc configuration</em>) always have access to autocomplete in order to enhance content management. Consider extending this to non-admin users for a better user experience.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-file-enable-autocomplete'),
    ];
    $form['metadata_message_created'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use entity creation date for metadata message'),
      '#description' => $this->t('When enabled, the metadata message shown to recipients will display the entity creation date with "Created by" text. When disabled (default), it displays the generation timestamp with "Encrypted by" text.'),
      '#default_value' => $this->config('proc.settings')
        ->get('metadata_message_created'),
    ];
    $form['proc-break-chain-of-trust'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow non recipients to trigger automatic recipient updates'),
      '#description' => $this->t('Break the chain of trust by allowing non recipients to trigger automatic filling of the wished set of recipients field'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-break-chain-of-trust'),
    ];
    $form['proc-keyring-meta-update-log-enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable queue info log for keyring meta updates'),
      '#description' => $this->t('When enabled, each processed queue item logs an informational message for keyring metadata update jobs. Disabled by default to reduce log volume.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-keyring-meta-update-log-enabled'),
    ];
    $form['proc-enable-inline-decryption-standalone'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable inline decryption in stand-alone mode'),
      '#description' => $this->t('When enabled, decrypted content is rendered inline in a dialog on the stand-alone decryption page instead of triggering a file download.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-enable-inline-decryption-standalone'),
    ];
    $form['proc-max-files-update'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of files for re-encryption at Re-encryption Tasks form'),
      '#description' => $this->t('Set the maximum number of files for re-encryption at Re-encryption Tasks form. Leave it 0 (zero) or empty for unlimited.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-max-files-update'),
    ];
    $form['proc-max-size-update'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum sum size (MB) of files for re-encryption at Re-encryption Tasks form'),
      '#description' => $this->t('Set the maximum size (MB) of files for re-encryption at Re-encryption Tasks form.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-max-size-update'),
    ];
    $form['proc-enable-background-reencryption'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable background re-encryption during decryption'),
      '#description' => $this->t('When enabled, pending re-encryption jobs are processed in the background after a successful decryption using a web worker. Disable for testing or to reduce server load.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-enable-background-reencryption') ?? 1,
    ];
    $form['proc-background-update-jobs-limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Background re-encryption jobs during decryption'),
      '#description' => $this->t('Number of pending re-encryption jobs to process in the background after a successful decryption. The decryption passphrase is reused by a web worker to re-encrypt content without blocking the user. Set to 0 to disable.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-background-update-jobs-limit') ?? 2,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="proc-enable-background-reencryption"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['proc-enable-autonomous-reencryption'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable autonomous background re-encryption on page load'),
      '#description' => $this->t('When enabled, background re-encryption starts automatically on pages where Protected Content is loaded, using the cached password from sessionStorage. Does not require the user to perform a decryption first. Only triggers if a cached password is available from a previous decryption in the same browser session.'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-enable-autonomous-reencryption') ?? 0,
    ];
    $form['proc-autonomous-reencryption-cooldown'] = [
      '#type' => 'number',
      '#title' => $this->t('Autonomous re-encryption cooldown (seconds)'),
      '#description' => $this->t('Minimum time in seconds between autonomous re-encryption runs across page navigations. Prevents excessive spawning. Default: 300 (5 minutes).'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-autonomous-reencryption-cooldown') ?? 300,
      '#min' => 10,
      '#states' => [
        'visible' => [
          ':input[name="proc-enable-autonomous-reencryption"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['proc-cipher-cache-max-entries'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum cipher cache entries (browser Cache Storage)'),
      '#description' => $this->t('Upper bound on how many cipher-text responses are kept in the browser Cache Storage before the oldest are evicted (FIFO). Prevents the cache from exhausting the browser storage quota during bulk decryption/re-encryption. Set 0 to disable the cap (not recommended).'),
      '#default_value' => $this->config('proc.settings')
        ->get('proc-cipher-cache-max-entries') ?? 200,
      '#min' => 0,
    ];
    $form['proc-update-batch-label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the re-encryption action'),
      '#description' => $this->t('Replace the default "Batch Update" label used in the UI. Leave empty to use the default.'),
      '#default_value' => $this->config('proc.settings')->get('proc-update-batch-label'),
    ];
    $form['proc-recipients-delivery-mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Recipients delivery mode'),
      '#description' => $this->t('Controls how recipient user IDs are passed to the encryption form. <strong>CSV in URL</strong> passes them as a comma-separated list in the URL path (transparent, but fails with many recipients). <strong>Payload (token)</strong> stores recipients server-side and passes a short token in the URL (supports unlimited recipients).'),
      '#default_value' => $this->config('proc.settings')->get('proc-recipients-delivery-mode') ?? 'csv',
      '#options' => [
        'csv' => $this->t('CSV in URL (legacy)'),
        'payload' => $this->t('Payload (token)'),
      ],
    ];
    $form['proc-request-re-encryption-description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Request re-encryption description'),
      '#description' => $this->t('Text shown in the confirmation form before requesting re-encryption. Leave empty to use the default message.'),
      '#default_value' => $this->config('proc.settings')->get('proc-request-re-encryption-description'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // phpcs:ignore
    $sw_manager = $this->streamWrapperManager;
    if (!$sw_manager->getScheme($form_state->getValue('proc-stream-wrapper'))) {
      $form_state->setErrorByName('stream_wrapper', $this->t('The provided stream wrapper is invalid.'));
    }
    if (!empty($form_state->getValue('proc-file-entity-max-filesize')) && !is_numeric($form_state->getValue('proc-file-entity-max-filesize'))) {
      $form_state->setErrorByName('proc-file-entity-max-filesize', $this->t('The maximum size for encryption must be a number.'));
    }
    if (!empty($form_state->getValue('proc-file-block-size')) && !is_numeric($form_state->getValue('proc-file-block-size'))) {
      $form_state->setErrorByName('proc-file-block-size', $this->t('The block size must be a number.'));
    }

    $invalid_identifier = $this->validateClassIdentifier($form_state->getValue('proc-encrypt-button-class'));

    if ($invalid_identifier) {
      $form_state->setErrorByName('proc-encrypt-button-class', $this->t('Invalid class identifier.'));
    }

    $invalid_decrypt_identifier = $this->validateClassIdentifier($form_state->getValue('proc-decrypt-button-class'));

    if ($invalid_decrypt_identifier) {
      $form_state->setErrorByName('proc-decrypt-button-class', $this->t('Invalid class identifier.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('proc.settings')
      ->set('proc-stream-wrapper', $form_state->getValue('proc-stream-wrapper'))
      ->set('proc-enable-stream-wrapper', $form_state->getValue('proc-enable-stream-wrapper'))
      ->set('proc-rsa-key-size', $form_state->getValue('proc-rsa-key-size'))
      ->set('proc-file-entity-max-filesize', $form_state->getValue('proc-file-entity-max-filesize'))
      ->set('proc-file-block-size', $form_state->getValue('proc-file-block-size'))
      ->set('proc-enable-block-size', $form_state->getValue('proc-enable-block-size'))
      ->set('proc-encrypt-button-label', $form_state->getValue('proc-encrypt-button-label'))
      ->set('proc-encrypt-button-label-filled-field', $form_state->getValue('proc-encrypt-button-label-filled-field'))
      ->set('proc-encrypt-button-class', $form_state->getValue('proc-encrypt-button-class'))
      ->set('proc-decrypt-button-class', $form_state->getValue('proc-decrypt-button-class'))
      ->set('proc-allow-password-cache-fields', $form_state->getValue('proc-allow-password-cache-fields'))
      ->set('proc-allow-password-cache-standalone', $form_state->getValue('proc-allow-password-cache-standalone'))
      ->set('proc-auto-filter-recipients', $form_state->getValue('proc-auto-filter-recipients'))
      ->set('proc-suppress-decrypt-link', $form_state->getValue('proc-suppress-decrypt-link'))
      ->set('proc-suppress-encryption-success-msg', $form_state->getValue('proc-suppress-encryption-success-msg'))
      ->set('proc-single-value-remove-button', $form_state->getValue('proc-single-value-remove-button'))
      ->set('proc-single-value-remove-button-label', $form_state->getValue('proc-single-value-remove-button-label'))
      ->set('proc-file-enable-autocomplete', $form_state->getValue('proc-file-enable-autocomplete'))
      ->set('metadata_message_created', $form_state->getValue('metadata_message_created'))
      ->set('proc-break-chain-of-trust', $form_state->getValue('proc-break-chain-of-trust'))
      ->set('proc-keyring-meta-update-log-enabled', $form_state->getValue('proc-keyring-meta-update-log-enabled'))
      ->set('proc-enable-inline-decryption-standalone', $form_state->getValue('proc-enable-inline-decryption-standalone'))
      ->set('proc-max-files-update', $form_state->getValue('proc-max-files-update'))
      ->set('proc-max-size-update', $form_state->getValue('proc-max-size-update'))
      ->set('proc-enable-background-reencryption', $form_state->getValue('proc-enable-background-reencryption'))
      ->set('proc-enable-autonomous-reencryption', $form_state->getValue('proc-enable-autonomous-reencryption'))
      ->set('proc-autonomous-reencryption-cooldown', $form_state->getValue('proc-autonomous-reencryption-cooldown'))
      ->set('proc-cipher-cache-max-entries', $form_state->getValue('proc-cipher-cache-max-entries'))
      ->set('proc-background-update-jobs-limit', $form_state->getValue('proc-background-update-jobs-limit'))
      ->set('proc-update-batch-label', $form_state->getValue('proc-update-batch-label'))
      ->set('proc-recipients-delivery-mode', $form_state->getValue('proc-recipients-delivery-mode'))
      ->set('proc-request-re-encryption-description', $form_state->getValue('proc-request-re-encryption-description'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['proc.settings'];
  }

  /**
   * Validate the class identifier.
   *
   * @param mixed $classes_string
   *   The string of classes.
   *
   * @return bool
   *   TRUE if the class identifier is invalid.
   */
  private function validateClassIdentifier(mixed $classes_string): bool {
    $classes = explode(' ', $classes_string);
    $invalid_id = FALSE;
    if (!empty($classes_string)) {
      foreach ($classes as $class) {
        if (empty($class)) {
          $invalid_id = TRUE;
          break;
        }
        if ($class != $this->cssIdentifierCleaner->cleanCssIdentifier($class)) {
          $invalid_id = TRUE;
          break;
        }
      }
    }
    return $invalid_id;
  }

}
