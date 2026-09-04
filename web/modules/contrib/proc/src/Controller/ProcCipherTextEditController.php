<?php

namespace Drupal\proc\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\proc\ProcKeyManager;
use Drupal\proc\Service\CleanUpExpiredUpdateJobService;
use Drupal\proc\Service\ProcJsonFileService;
use Drupal\proc\Traits\ProcCsvTrait;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for handling entity edit requests.
 */
class ProcCipherTextEditController extends ControllerBase {

  use ProcCsvTrait;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The key manager.
   *
   * @var \Drupal\proc\ProcKeyManager
   */
  protected ProcKeyManager $keyManager;

  /**
   * The JSON file service.
   *
   * @var \Drupal\proc\Service\ProcJsonFileService
   */
  protected ProcJsonFileService $jsonFileService;

  /**
   * The clean up expired update job service.
   *
   * @var \Drupal\proc\Service\CleanUpExpiredUpdateJobService
   */
  protected CleanUpExpiredUpdateJobService $cleanUpExpiredUpdateJob;

  /**
   * ProcCipherTextEditController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   * @param \Drupal\proc\ProcKeyManager $key_manager
   *   The proc key manager.
   * @param \Drupal\proc\Service\ProcJsonFileService $json_file_service
   *   The JSON file service.
   * @param \Drupal\proc\Service\CleanUpExpiredUpdateJobService $clean_up_expired_update_job
   *   The cleanup expired update job service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $logger,
    ProcKeyManager $key_manager,
    ProcJsonFileService $json_file_service,
    CleanUpExpiredUpdateJobService $clean_up_expired_update_job,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->keyManager = $key_manager;
    $this->jsonFileService = $json_file_service;
    $this->cleanUpExpiredUpdateJob = $clean_up_expired_update_job;
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
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('proc'),
      $container->get('proc.key_manager'),
      $container->get('proc.proc_json_file_service'),
      $container->get('proc.clean_up_expired_update_job')
    );
  }

  /**
   * Handle the cipher text update.
   */
  public function editCipherText(Request $request): JsonResponse {

    $data = json_decode($request->getContent(), TRUE);

    // Ensure we received valid JSON.
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      $this->logger->warning('Invalid JSON payload received for entity edit.');
      throw new BadRequestHttpException('Invalid JSON payload.');
    }

    // Validate entity_id (required, positive integer).
    if (!array_key_exists('entity_id', $data) || filter_var($data['entity_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
      $this->logger->warning('Invalid or missing entity_id in payload.', ['payload_keys' => array_keys($data)]);
      throw new BadRequestHttpException('Invalid or missing entity_id.');
    }
    $entity_id = (int) $data['entity_id'];

    // Validate recipients (required, must be JSON string decoding to an array,
    // and must contain an entry for this entity_id):
    if (!array_key_exists('recipients', $data) || !is_string($data['recipients'])) {
      $this->logger->warning('Missing recipients in payload for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Missing recipients field.');
    }

    $recipients_decoded = json_decode($data['recipients'], TRUE);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($recipients_decoded)) {
      $this->logger->warning('Invalid recipients JSON for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Invalid recipients format.');
    }
    if (!array_key_exists($entity_id, $recipients_decoded) || !is_array($recipients_decoded[$entity_id])) {
      $this->logger->warning('Recipients do not contain an entry for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Recipients do not contain the entity_id entry.');
    }
    // Validate each recipient id is a positive integer.
    foreach ($recipients_decoded[$entity_id] as $rid) {
      if (filter_var($rid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
        $this->logger->warning('Invalid recipient id for entity @id.', ['@id' => $entity_id, 'recipient' => $rid]);
        throw new BadRequestHttpException('Invalid recipient id in recipients list.');
      }
    }
    $recipients = $recipients_decoded;

    // Validate cipher_text:
    if (!array_key_exists('cipher_text', $data) || !is_string($data['cipher_text'])) {
      $this->logger->warning('Missing or empty cipher_text for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Missing or empty cipher_text.');
    }
    $cipher_text = $data['cipher_text'];
    // Validate browser_fingerprint:
    if (!array_key_exists('browser_fingerprint', $data) || !is_string($data['browser_fingerprint']) || $data['browser_fingerprint'] === '') {
      $this->logger->warning('Missing or invalid browser_fingerprint for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Missing or invalid browser_fingerprint.');
    }
    // Validate generation_timestamp (required, numeric, non-negative). Accepts
    // integer or float string:
    if (!array_key_exists('generation_timestamp', $data) || !is_scalar($data['generation_timestamp']) || !is_numeric($data['generation_timestamp'])) {
      $this->logger->warning('Missing or invalid generation_timestamp for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Missing or invalid generation_timestamp.');
    }
    // Normalize to integer seconds (drop fractional part).
    $generation_timestamp = (int) floor((float) $data['generation_timestamp']);
    if ($generation_timestamp < 0) {
      $this->logger->warning('Negative generation_timestamp for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Invalid generation_timestamp.');
    }
    // Validate generation_timespan (required, numeric, non-negative).
    if (!array_key_exists('generation_timespan', $data) || !is_scalar($data['generation_timespan']) || !is_numeric($data['generation_timespan'])) {
      $this->logger->warning('Missing or invalid generation_timespan for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Missing or invalid generation_timespan.');
    }
    $generation_timespan = (int) floor((float) $data['generation_timespan']);
    if ($generation_timespan < 0) {
      $this->logger->warning('Negative generation_timespan for entity @id.', ['@id' => $entity_id]);
      throw new BadRequestHttpException('Invalid generation_timespan.');
    }

    try {
      $entity = $this->entityTypeManager->getStorage('proc')->load($entity_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log error:
      $this->logger->error($e->getMessage());
      return new JsonResponse(['status' => 'error', 'message' => 'Entity not found.'], 404);
    }
    // Check if entity is of type cipher:
    if (!$entity || $entity->getType() !== 'cipher') {
      return new JsonResponse(['status' => 'error', 'message' => 'Entity not of type cipher.'], 404);
    }

    $recipients_field_set = [];
    foreach ($recipients[$entity_id] as $recipient_id) {
      $recipients_field_set[] = ['target_id' => $recipient_id];
    }

    // Check if cipher text follows PGP opening format:
    if (!str_contains($cipher_text, '-----BEGIN PGP')) {
      return new JsonResponse(['status' => 'error', 'message' => 'Incorrect cipher text opening format.'], 404);
    }

    try {
      $files = $this->jsonFileService->saveJsonFiles($cipher_text);
    }
    catch (EntityStorageException | RandomException $e) {
      // Log error:
      $this->logger->error('Error saving JSON files for entity @entity_id: @error', [
        '@entity_id' => $entity_id,
        '@error' => $e->getMessage(),
      ]);
      return new JsonResponse(['status' => 'error', 'message' => 'Failed to save JSON files.'], 500);
    }

    $cipher_fid = $files['file_id'] ?? $files['json_fids'] ?? '';
    $cipher = !empty($cipher_fid) ? ['cipher_fid' => $cipher_fid] : '';

    $prev_recs = $entity->get('field_recipients_set')->getValue();
    $prev_rec_ids = [];
    foreach ($prev_recs as $prev_rec_id) {
      $prev_rec_ids[] = $prev_rec_id['target_id'];
    }
    $new_rec_ids = [];
    foreach ($recipients_field_set as $new_rec_id) {
      $new_rec_ids[] = $new_rec_id['target_id'];
    }

    $added = array_values(array_diff($new_rec_ids, $prev_rec_ids));
    $removed = array_values(array_diff($prev_rec_ids, $new_rec_ids));

    $metadata = $entity->getMeta();
    $metadata[0]['browser_fingerprint'] = htmlspecialchars($data['browser_fingerprint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $metadata[0]['generation_timestamp'] = htmlspecialchars($data['generation_timestamp'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $metadata[0]['generation_timespan'] = htmlspecialchars($data['generation_timespan'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $prev_changed = $entity->get('changed')->getValue()[0]['value'];

    $entity->set('armored', $cipher)
      ->set('field_recipients_set', $recipients_field_set)
      ->set('field_wished_recipients_set', [])
      ->set('meta', $metadata);

    $entity->save();

    try {
      $this->entityTypeManager
        ->getStorage('proc')
        ->resetCache([$entity->id()]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error('Error resetting cache for entity @entity_id: @error', [
        '@entity_id' => $entity->id(),
        '@error' => $e->getMessage(),
      ]);
    }

    $current_changed = $entity->get('changed')->getValue()[0]['value'];

    if ($prev_changed == $current_changed) {
      $this->logger->error('Entity @entity_id was not updated successfully. Previous changed timestamp: @prev_changed, current changed timestamp: @current_changed', [
        '@entity_id' => $entity->id(),
        '@prev_changed' => $prev_changed,
        '@current_changed' => $current_changed,
      ]);
      return new JsonResponse(['status' => 'error', 'message' => 'Entity update failed.'], 500);
    }

    $removed_message_string = $added_message_string = 'none';

    $added_count = $removed_count = 0;
    if ($added) {
      $added_count = count($added);
      $added_message_string = implode(",", $added) ?? 'none';
    }
    if ($removed) {
      $removed_count = count($removed);
      $removed_message_string = implode(",", $removed) ?? 'none';
    }

    $prev_rec = [];
    $prev_rec[$entity_id] = $prev_rec_ids;

    try {
      $this->cleanUpExpiredUpdateJob->cleanUpExpiredUpdateJobs($prev_rec, [$entity_id]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException | \Exception $e) {
      $this->logger->error('Error cleaning up expired update jobs for entity @entity_id: @error', [
        '@entity_id' => $entity_id,
        '@error' => $e->getMessage(),
      ]);
    }

    $response = [
      'status' => 'success',
      'message' => [
        'Entity ' . $entity_id . ' updated successfully.',
        'ID(s) of added recipient(s): ' . $added_message_string,
        'ID(s) of removed recipient(s): ' . $removed_message_string,
        // Balance of recipients:
        $added_count - $removed_count,
      ],
    ];
    return new JsonResponse($response);
  }

}
