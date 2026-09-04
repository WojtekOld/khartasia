<?php

namespace Drupal\proc\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\proc\ProcKeyManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ProcKeysCacheTypeForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Drupal logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * ProcController controller.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The file repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   * @param \Drupal\proc\ProcKeyManagerInterface $procKeyManager
   *   The ProcKeyManager service.
   */
  public function __construct(
    AccountProxy $current_user,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $logger,
    ProcKeyManagerInterface $procKeyManager,
  ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->procKeyManager = $procKeyManager;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('proc'),
      $container->get('proc.key_manager')
    );
  }

  /**
   * Build the form.
   *
   * A build form method constructs an array that defines how markup and
   * other form elements are included in an HTML form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get the latest proc key of the current user:
    $user = $this->currentUser;

    $keyring_proc_object = NULL;
    try {
      $keyring_proc_object = $this->procKeyManager->getKeys($user->id());
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log error on key retrieval:
      $this->logger('proc')->error('Error retrieving proc keyring: @error', ['@error' => $e->getMessage()]);
    }

    if (!$keyring_proc_object) {
      // Return access denied if the user does not have a proc key.
      throw new AccessDeniedHttpException('No Protected Content key found for user.');
    }
    $keyring_cache_type = $keyring_proc_object['keyring_type'];

    $form['proc_keys_cache_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cache type for your Protected Content key'),
      '#description' => $this->t('You should stay attentive while caching is enabled, as unauthorized users could exploit unattended access.'),
      '#default_value' => $keyring_cache_type ?? '',
      '#options' => [
        'keyring' => $this->t('No cache'),
        'keyring_1h' => $this->t('1 hour cache'),
        'keyring_2h' => $this->t('2 hours cache'),
      ],
    ];
    $form['proc_keys_cache_type_pid'] = [
      '#type' => 'hidden',
      '#default_value' => $keyring_proc_object['keyring_cid'] ?? '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Getter method for Form ID.
   *
   * @return string
   *   The unique ID of the form defined by this class.
   */
  public function getFormId(): string {
    return 'proc_keyring_type_form';
  }

  /**
   * Form submit handler.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Proc keyring Id for updating:
    $proc_id = $form_state->getValues()['proc_keys_cache_type_pid'];
    // Proc keyring type for updating:
    $keyring_type = $form_state->getValues()['proc_keys_cache_type'];

    $keyring = NULL;
    // Update the proc keyring entity:
    try {
      $keyring = $this->entityTypeManager->getStorage('proc')->load($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log error on key retrieval:
      $this->logger('proc')->error('Error retrieving proc keyring: @error', ['@error' => $e->getMessage()]);
    }
    $keyring->set('type', $keyring_type);
    $keyring->save();

    // Set a message that the proc keyring has been updated:
    $this->messenger()->addMessage($this->t('Your Protected Content key cache type has been updated.'));
  }

}
