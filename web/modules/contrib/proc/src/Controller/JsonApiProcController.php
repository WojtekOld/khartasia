<?php

namespace Drupal\proc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\proc\Entity\Proc;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcUpdateJobsCountService;
use Drupal\proc\Traits\ProcCsvTrait;
use Drupal\proc\Traits\ProcRecipientTrait;
use Drupal\proc_reporting\Service\ProcReportingService;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller.
 *
 * @package Drupal\proc\Controller
 */
class JsonApiProcController extends ControllerBase {

  use ProcRecipientTrait;
  use ProcCsvTrait;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The proc_reporting service (NULL when the submodule is not enabled).
   *
   * @var \Drupal\proc_reporting\Service\ProcReportingService|null
   */
  protected ?ProcReportingService $reportingService;

  /**
   * The proc_reporting logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected ?LoggerChannelInterface $logger;

  /**
   * The update-jobs count service.
   *
   * @var \Drupal\proc\Service\ProcUpdateJobsCountService
   */
  protected ProcUpdateJobsCountService $updateJobsCountService;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The proc key manager.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * JsonApiProcController constructor.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\proc\Service\ProcUpdateJobsCountService $update_jobs_count_service
   *   The update-jobs count service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store factory.
   * @param \Drupal\proc\ProcKeyManagerInterface $proc_key_manager
   *   The proc key manager.
   * @param \Drupal\proc_reporting\Service\ProcReportingService|null $reportingService
   *   The proc_reporting service, or NULL when the submodule is not enabled.
   * @param \Drupal\Core\Logger\LoggerChannelInterface|null $logger
   *   The logger channel for proc_reporting, or NULL when it is not enabled.
   */
  public function __construct(
    CurrentPathStack $current_path,
    EntityTypeManagerInterface $entityTypeManager,
    ProcUpdateJobsCountService $update_jobs_count_service,
    PrivateTempStoreFactory $temp_store_factory,
    ProcKeyManagerInterface $proc_key_manager,
    ?ProcReportingService $reportingService = NULL,
    ?LoggerChannelInterface $logger = NULL,
  ) {
    $this->currentPath = $current_path;
    $this->entityTypeManager = $entityTypeManager;
    $this->reportingService = $reportingService;
    $this->logger = $logger;
    $this->updateJobsCountService = $update_jobs_count_service;
    $this->procKeyManager = $proc_key_manager;
    $this->tempStoreFactory = $temp_store_factory;
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
      $container->get('path.current'),
      $container->get('entity_type.manager'),
      $container->get('proc.update_jobs_count_service'),
      $container->get('tempstore.private'),
      $container->get('proc.key_manager'),
      $container->has('proc_reporting.service')
        ? $container->get('proc_reporting.service')
        : NULL,
      $container->has('logger.channel.proc_reporting')
        ? $container->get('logger.channel.proc_reporting')
        : NULL,
    );
  }

  /**
   * Index of keys.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   \Symfony\Component\HttpFoundation\JsonResponse
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function index(): JsonResponse {
    $response = new JsonResponse(['pubkey' => $this->getData()]);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Return the number of update jobs in the current user's latest keyring.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The update-jobs count payload.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function myUpdateJobsCount(): JsonResponse {
    $response = new JsonResponse([
      'update_jobs_count' => $this->updateJobsCountService->getCountForUser((int) $this->currentUser()->id()),
    ]);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Get data on latest public keys created.
   *
   * @return array
   *   Array of data containing keys and changed timestamp.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getData(): array {
    $current_path = $this->currentPath->getPath();
    $path_array = explode('/', $current_path);
    $ids_string = $path_array[4];
    $ids = explode(',', $ids_string);

    $search_by = $path_array[5];

    $proc_ids = [];
    foreach ($ids as $id) {
      if (is_numeric($id)) {
        $proc_ids[] = $this->latestKeyringId($id, $search_by);
      }
    }

    $result = [];

    foreach ($proc_ids as $proc_id) {
      if ($proc_id) {
        $proc = $this->entityTypeManager->getStorage('proc')->load($proc_id);
        $update_jobs = $proc->get('meta')?->getValue()[0]['update_jobs'] ?? [];

        $result[] = [
          'key' => $proc->get('armored')->getValue()[0]['pubkey'],
          'changed' => $proc->get('created')->getValue()[0]['value'],
          'update_jobs' => array_values($update_jobs) ?? [],
        ];
      }
    }

    return $result;
  }

  /**
   * Cipher.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   \Symfony\Component\HttpFoundation\JsonResponse
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function cipher(): JsonResponse {
    $response = new JsonResponse(['pubkey' => $this->getCihper()]);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Get cipher.
   *
   * @return array
   *   Cipher text and metadata.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCihper(): array {
    $current_path = $this->currentPath->getPath();
    $path_array = explode('/', $current_path);

    $cipher_ids_string = $path_array[4];
    $cipher_data = [];

    if (empty($cipher_ids_string)) {
      return $cipher_data;
    }

    $proc_ids = $this->getCsvArgument($cipher_ids_string);

    foreach ($proc_ids as $proc_index => $proc_id) {
      $proc = $this->entityTypeManager->getStorage('proc')->load($proc_id);
      if (!$proc) {
        continue;
      }

      $cipher_data[$proc_index] = self::getCipherData($proc, self::getArmored($proc), $proc_id);

    }

    // Count access/download by returned cipher items:
    $this->trackCipherAccesses(count($cipher_data));

    return $cipher_data;
  }

  /**
   * Track cipher access/download operations for the current user.
   *
   * @param int $count
   *   Number of cipher items returned in this request.
   */
  private function trackCipherAccesses(int $count): void {
    // proc_reporting is an optional submodule; the service is NULL when absent.
    if ($this->reportingService === NULL) {
      return;
    }

    if ($count <= 0 || !$this->currentUser()->isAuthenticated()) {
      return;
    }

    try {
      $this->reportingService
        ->recordOperation(
          (int) $this->currentUser()->id(),
          (string) $this->currentUser()->getAccountName(),
          'cipher_text_download_count', $count
        );
    }
    catch (\Exception) {
      if ($this->logger) {
        $this->logger->error('Failed to track cipher access for user ID @uid: @error', [
          '@uid' => $this->currentUser()->id(),
          '@error' => $this->reportingService ? 'Exception thrown by reporting service' : 'Reporting service unavailable',
        ]);
      }
    }
  }

  /**
   * Get cipher data.
   *
   * @param object $proc
   *   The proc entity.
   * @param string $armored
   *   The armored text.
   * @param int $proc_id
   *   The proc ID.
   *
   * @return array
   *   Array of cipher data.
   */
  public function getCipherData(object $proc, string $armored, int $proc_id): array {
    $data = [
      'armored' => $armored,
      'source_file_name' => $proc->get('meta')->getValue()[0]['source_file_name'],
      'source_file_size' => $proc->get('meta')->getValue()[0]['source_file_size'],
      'cipher_cid' => $proc_id,
      'proc_owner_uid' => $proc->get('user_id')->getValue()[0]['target_id'],
      'proc_recipients' => $proc->get('field_recipients_set')->getValue(),
      'changed' => $proc->get('changed')->getValue()[0]['value'],
      'wished_recipients' => $proc->get('field_wished_recipients_set')?->getValue() ?? [],
    ];
    if (isset($proc->get('meta')->getValue()[0]['source_input_mode'])) {
      $data['source_input_mode'] = $proc->get('meta')->getValue()[0]['source_input_mode'];
    }
    return $data;
  }

  /**
   * Get armored text.
   *
   * @param object $proc
   *   The proc entity.
   *
   * @return string
   *   The armored text.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getArmored(object $proc): string {
    $armored = '';
    if (
      // If cipher_fid is key in armored field, proc is using stream.
      isset($proc->get('armored')->getValue()[0]['cipher_fid']) &&
      // If cipher_fid is not an array, there is one single file:
      !is_array($proc->get('armored')->getValue()[0]['cipher_fid'])
    ) {
      $storage = $this->entityTypeManager->getStorage('file');
      $file = $storage->load($proc->get('armored')
        ->getValue()[0]['cipher_fid']);
      $armored = file_get_contents($file->getFileUri());
    }
    // If 'cipher' is key at armored field:
    if (isset($proc->get('armored')->getValue()[0]['cipher'])) {
      // Database storage:
      $armored = $proc->get('armored')->getValue()[0]['cipher'];
    }
    // If cipher_fid key is an array, there are multiple files for the
    // storage of the cipher:
    if (
      isset($proc->get('armored')->getValue()[0]['cipher_fid']) &&
      is_array($proc->get('armored')->getValue()[0]['cipher_fid'])
    ) {
      // Concatenate the pieces of the cipher in a single variable:
      foreach ($proc->get('armored')->getValue()[0]['cipher_fid'] as $fid) {
        $armored = $armored . file_get_contents($this->entityTypeManager->getStorage('file')->load($fid)->getFileUri());
      }
    }

    return $armored;

  }

  /**
   * Store recipients in temp store and return a token.
   *
   * Accepts a JSON payload with a 'recipients' array of user IDs, stores them
   * in the current user's private temp store, and returns a unique token that
   * can be used to retrieve the recipients in the encrypt form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with the token.
   */
  public function storeRecipients(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return new JsonResponse(['error' => 'Invalid JSON payload.'], 400);
    }

    if (!isset($data['recipients']) || !is_array($data['recipients'])) {
      return new JsonResponse(['error' => 'Missing or invalid recipients array.'], 400);
    }

    // Validate and sanitize recipient IDs.
    $recipients = [];
    foreach ($data['recipients'] as $id) {
      if (is_numeric($id) && (int) $id > 0) {
        $recipients[] = (int) $id;
      }
    }

    if (empty($recipients)) {
      return new JsonResponse(['error' => 'No valid recipient IDs provided.'], 400);
    }

    $recipients = array_values(array_unique($recipients));

    // Generate a unique token.
    $token = bin2hex(random_bytes(16));

    // Store in private temp store (scoped to the current user session).
    $store = $this->tempStoreFactory->get('proc_recipients');
    $store->set($token, $recipients);

    $response = new JsonResponse(['token' => $token]);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Validate and prepare a text cipher for client-side decryption.
   *
   * Backs the Drupal.proc.api.decryptText() JS API. It performs all server-side
   * checks (the cipher must be an encrypted text/plain, the current user must be
   * a recipient, and the user's current key must be capable of decrypting it),
   * then returns the armored cipher together with the current user's own
   * (passphrase-encrypted) keyring so the browser can decrypt client-side.
   *
   * The actual decryption never happens server-side: the private key is only
   * ever unlocked in the browser with the user's passphrase.
   *
   * @param string $cipher_id
   *   The proc cipher entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Structured payload: on success {status:'ok', armored, changed, keyring,
   *   uid}; otherwise {status:'error', code, message, uid}.
   */
  public function decryptText(string $cipher_id): JsonResponse {
    $uid = (int) $this->currentUser()->id();

    $validated = $this->decryptTextValidate($cipher_id, $uid);
    if ($validated instanceof JsonResponse) {
      return $validated;
    }
    /** @var \Drupal\proc\Entity\Proc $proc */
    $proc = $validated['proc'];
    $keyring = $validated['keyring'];

    // Load the armored cipher text.
    try {
      $armored = $this->getArmored($proc);
    }
    catch (\Throwable $e) {
      $this->logError('Failed to read armored cipher for @id: @msg', ['@id' => $cipher_id, '@msg' => $e->getMessage()]);
      $armored = '';
    }
    if (!is_string($armored) || $armored === '') {
      return $this->decryptTextError('error', $this->t('Unable to load the encrypted content.'), $uid);
    }

    $pass = $this->procKeyManager->getPrivKeyMetadata(NULL)['proc_pass'] ?? '';

    return $this->jsonNoStore([
      'status' => 'ok',
      'code' => 'ok',
      'message' => '',
      'uid' => $uid,
      // Entity change time, returned for informational purposes only.
      'changed' => (int) ($proc->get('changed')->value ?? 0),
      'armored' => $armored,
      'keyring' => [
        'privkey' => $keyring['encrypted_private_key'],
        'pass' => $pass,
        'keyring_type' => $keyring['keyring_type'] ?? '',
      ],
    ]);
  }

  /**
   * Validate a decrypt-text request.
   *
   * Runs every server-side guard for the decryptText() endpoint: the cipher
   * must exist and be an encrypted text/plain item, the current user must be a
   * recipient, must hold a keyring, and that key must be cryptographically
   * capable of decrypting the content (its generation_timestamp must precede
   * the content's).
   *
   * @param string $cipher_id
   *   The proc cipher entity ID.
   * @param int $uid
   *   The current user ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|array
   *   A structured error JsonResponse on failure, or, on success, an array with
   *   keys 'proc' (the loaded Proc entity) and 'keyring' (the current user's
   *   keyring data as returned by ProcKeyManager::getKeys()).
   */
  private function decryptTextValidate(string $cipher_id, int $uid): JsonResponse|array {
    if (!is_numeric($cipher_id)) {
      return $this->decryptTextError('invalid', $this->t('Invalid content identifier.'), $uid);
    }

    try {
      $proc = $this->entityTypeManager->getStorage('proc')->load($cipher_id);
    }
    catch (\Exception $e) {
      $this->logError('Error loading proc @id: @msg', ['@id' => $cipher_id, '@msg' => $e->getMessage()]);
      return $this->decryptTextError('error', $this->t('Unable to load the requested content.'), $uid);
    }

    if (!$proc instanceof Proc || $proc->getType() !== 'cipher') {
      return $this->decryptTextError('not_found', $this->t('Protected content not found.'), $uid);
    }

    // Only encrypted plain-text content is supported by this API.
    $meta = $proc->getMeta()[0] ?? [];
    if (($meta['source_file_type'] ?? '') !== 'text/plain') {
      return $this->decryptTextError('not_text', $this->t('This content is not an encrypted text.'), $uid);
    }

    // The current user must be a recipient.
    if (!$this->isUserRecipient($proc, $uid)) {
      return $this->decryptTextError('no_access', $this->t('You are not a recipient of this content.'), $uid);
    }

    // The current user must have a keyring.
    try {
      $keyring = $this->procKeyManager->getKeys((string) $uid, 'user_id');
    }
    catch (\Exception $e) {
      $keyring = [];
    }
    if (empty($keyring['encrypted_private_key'])) {
      return $this->decryptTextError('no_keyring', $this->t('You do not have a Protected Content key.'), $uid);
    }

    // A key can decrypt the content only if it is older than the content:
    $content_gen_raw = $meta['generation_timestamp'] ?? NULL;
    $keyring_entity = $keyring['keyring_entity'] ?? NULL;
    $key_gen_raw = $keyring_entity
      ? ($keyring_entity->get('meta')->getValue()[0]['generation_timestamp'] ?? NULL)
      : NULL;
    if (is_numeric($content_gen_raw) && is_numeric($key_gen_raw) && (float) $key_gen_raw >= (float) $content_gen_raw) {
      return $this->decryptTextError(
        'key_incapable',
        $this->t('Your current key is newer than this content, so it cannot be decrypted with it. The content must be re-encrypted for your current key first.'),
        $uid
      );
    }

    return ['proc' => $proc, 'keyring' => $keyring];
  }

  /**
   * Build a structured error payload for decryptText().
   */
  private function decryptTextError(string $code, string $message, int $uid): JsonResponse {
    return $this->jsonNoStore([
      'status' => 'error',
      'code' => $code,
      'message' => $message,
      'uid' => $uid,
    ]);
  }

  /**
   * Whether the given user is a recipient of the given proc cipher entity.
   */
  private function isUserRecipient(Proc $proc, int $uid): bool {
    if ($uid <= 0) {
      return FALSE;
    }
    foreach ($proc->get('field_recipients_set')->getValue() as $recipient) {
      if ((int) ($recipient['target_id'] ?? 0) === $uid) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Build a private, non-cacheable JSON response.
   */
  private function jsonNoStore(array $data): JsonResponse {
    $response = new JsonResponse($data);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Log an error on the proc_reporting logger when available.
   */
  private function logError(string $message, array $context): void {
    if ($this->logger) {
      $this->logger->error($message, $context);
    }
  }

}
