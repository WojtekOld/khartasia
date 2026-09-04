<?php

namespace Drupal\proc\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\proc\ProcKeyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for handling entity edit requests.
 */
class ProcEntityEditController extends ControllerBase {

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
   * The proc module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $procSettings;

  /**
   * ProcEntityEditController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   * @param \Drupal\proc\ProcKeyManager $key_manager
   *   The proc key manager.
   * @param \Drupal\Core\Config\ImmutableConfig $proc_settings
   *   The proc module settings.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $logger,
    ProcKeyManager $key_manager,
    ImmutableConfig $proc_settings,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->keyManager = $key_manager;
    $this->procSettings = $proc_settings;
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
      $container->get('config.factory')->get('proc.settings'),
    );
  }

  /**
   * Handles the entity edit request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating the status of the operation.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function editEntity(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $entity_id = $data['entity_id'];
    $wished_recipients = explode(',', $data['field_wished_recipients_set']);

    $entity = $this->loadAndValidateEntity($entity_id, $wished_recipients);
    if (!$entity) {
      return new JsonResponse(['status' => 'error', 'message' => 'Entity not found.'], 404);
    }

    // The cryptographic age:
    $content_gen_raw = $entity->get('meta')->getValue()[0]['generation_timestamp'] ?? NULL;
    $content_generation_ts = is_numeric($content_gen_raw) ? (float) $content_gen_raw : NULL;

    $this->updateEntityWishedRecipients($entity, $wished_recipients);
    $recipient_ids = $this->extractRecipientIds($entity);

    if (!empty($recipient_ids)) {
      [$keyring_ids, $keyring_entities] = $this->getLatestKeyrings($recipient_ids);
      $this->processKeyringMetaUpdates($keyring_ids, $keyring_entities, $entity_id, $content_generation_ts);
    }

    return new JsonResponse(['status' => 'success', 'message' => 'Entity updated successfully.']);
  }

  /**
   * Loads and validates the entity.
   *
   * @param string $entity_id
   *   The entity ID.
   * @param array $wished_recipients
   *   An array of wished recipients.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded entity or NULL if not found or invalid.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadAndValidateEntity(string $entity_id, array $wished_recipients): ?EntityInterface {
    $entity = $this->entityTypeManager->getStorage('proc')->load($entity_id);
    return ($entity && !empty($wished_recipients)) ? $entity : NULL;
  }

  /**
   * Updates the entity with the wished recipients.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to update.
   * @param array $wished_recipients
   *   An array of wished recipients.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateEntityWishedRecipients(EntityInterface $entity, array $wished_recipients): void {
    $entity->set('field_wished_recipients_set', $wished_recipients);
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
  }

  /**
   * Extracts recipient IDs from the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to extract recipient IDs from.
   *
   * @return array
   *   An array of recipient IDs.
   */
  private function extractRecipientIds(EntityInterface $entity): array {
    $recipient_ids = [];
    foreach ($entity->get('field_recipients_set')->getValue() as $recipient) {
      if (isset($recipient['target_id'])) {
        $recipient_ids[] = $recipient['target_id'];
      }
    }
    return $recipient_ids;
  }

  /**
   * Retrieves the latest keyrings for the given recipient IDs.
   *
   * @param array $recipient_ids
   *   An array of recipient IDs.
   *
   * @return array
   *   An array containing the keyring IDs and their corresponding entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getLatestKeyrings(array $recipient_ids): array {
    $keyring_ids = [];
    $keyring_entities = [];
    foreach ($recipient_ids as $recipient_id) {
      $result = $this->keyManager->getKeys($recipient_id, 'user_id');
      $keyring_ids[] = $result['keyring_cid'];
      $keyring_entities[$result['keyring_cid']] = $result['keyring_entity'];
    }
    return [$keyring_ids, $keyring_entities];
  }

  /**
   * Processes the keyring meta updates.
   *
   * @param array $keyring_ids
   *   An array of keyring IDs.
   * @param array $keyring_entities
   *   An array of keyring entities.
   * @param string $entity_id
   *   The entity ID.
   * @param float|null $content_generation_ts
   *   The content's cryptographic generation_timestamp (from meta), used to
   *   skip recipients whose key is not older than the content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  private function processKeyringMetaUpdates(array $keyring_ids, array $keyring_entities, string $entity_id, ?float $content_generation_ts): void {
    foreach ($keyring_ids as $keyring_id) {
      $keyring = $keyring_entities[$keyring_id] ?? NULL;
      if ($keyring !== NULL) {
        $this->updateKeyringMetaData($keyring, $entity_id, $content_generation_ts);
      }
    }
  }

  /**
   * Create queue item for updating the keyring metadata.
   *
   * @param \Drupal\Core\Entity\EntityInterface $keyring
   *   The keyring entity.
   * @param string $entity_id
   *   The entity ID.
   * @param float|null $content_generation_ts
   *   The content's cryptographic generation_timestamp (from meta).
   *
   * @throws \Exception
   */
  private function updateKeyringMetaData(EntityInterface $keyring, string $entity_id, ?float $content_generation_ts): void {
    try {
      // Skip recipients who have lost access:
      $key_gen_raw = $keyring->get('meta')->getValue()[0]['generation_timestamp'] ?? NULL;
      if ($content_generation_ts !== NULL && is_numeric($key_gen_raw) && (float) $key_gen_raw >= $content_generation_ts) {
        if ((bool) $this->procSettings->get('proc-keyring-meta-update-log-enabled')) {
          $this->logger->info('Skipped enqueue: recipient keyring @keyring_id lost access to entity @entity_id (key not older than content).', [
            '@keyring_id' => $keyring->id(),
            '@entity_id' => $entity_id,
          ]);
        }
        return;
      }

      // Dedup: skip enqueue when this keyring already (and still) lists the
      // entity in its update_jobs.
      $meta = $keyring->get('meta')->getValue()[0] ?? [];
      $existing_jobs = $meta['update_jobs'] ?? [];
      if (is_array($existing_jobs) && in_array((int) $entity_id, array_map('intval', $existing_jobs), TRUE)) {
        if ((bool) $this->procSettings->get('proc-keyring-meta-update-log-enabled')) {
          $this->logger->info('Skipped enqueue: keyring @keyring_id already has entity @entity_id in update_jobs.', [
            '@keyring_id' => $keyring->id(),
            '@entity_id' => $entity_id,
          ]);
        }
        return;
      }

      // @phpstan-ignore-next-line
      $queue = \Drupal::queue('proc_update_keyring_meta');
      $queue->createItem([
        'keyring_id' => (int) $keyring->id(),
        'entity_id' => (int) $entity_id,
      ]);
      if ((bool) $this->procSettings->get('proc-keyring-meta-update-log-enabled')) {
        $this->logger->info('Enqueued keyring meta update for keyring @keyring_id / entity @entity_id.', [
          '@keyring_id' => $keyring->id(),
          '@entity_id' => $entity_id,
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to enqueue keyring meta update for @keyring_id: @error', [
        '@keyring_id' => $keyring->id(),
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
