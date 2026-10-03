<?php

namespace Drupal\proc\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\proc\ProcInterface;
use Drupal\proc\RecipientStorageInterface;
use Drupal\proc\RecipientStoragePluginManager;
use Psr\Log\LoggerInterface;

/**
 * Default implementation of the recipient storage broker.
 *
 * Resolves the RecipientStorage plugin bound to a cipher (via its "meta"
 * field) and delegates recipient reads and writes to it. Any cipher without a
 * valid binding is served by the standard database-backed plugin.
 */
class RecipientStorageBroker implements RecipientStorageBrokerInterface {

  /**
   * The recipient storage plugin manager.
   *
   * @var \Drupal\proc\RecipientStoragePluginManager
   */
  protected RecipientStoragePluginManager $pluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Statically cached plugin instances, keyed by plugin ID.
   *
   * @var \Drupal\proc\RecipientStorageInterface[]
   */
  protected array $instances = [];

  /**
   * Constructs a RecipientStorageBroker.
   *
   * @param \Drupal\proc\RecipientStoragePluginManager $plugin_manager
   *   The recipient storage plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    RecipientStoragePluginManager $plugin_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
  ) {
    $this->pluginManager = $plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function resolveForProc(ProcInterface $proc): RecipientStorageInterface {
    return $this->getInstance($this->getBoundPluginId($proc));
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function getRecipients(ProcInterface $proc): array {
    return $this->resolveForProc($proc)->getRecipients($proc);
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function getRecipientIds(ProcInterface $proc): array {
    return $this->resolveForProc($proc)->getRecipientIds($proc);
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function isRecipient(ProcInterface $proc, int $uid): bool {
    return $this->resolveForProc($proc)->isRecipient($proc, $uid);
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function setRecipients(ProcInterface $proc, array $recipients): void {
    $this->resolveForProc($proc)->setRecipients($proc, $recipients);
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function getWishedRecipients(ProcInterface $proc): array {
    return $this->resolveForProc($proc)->getWishedRecipients($proc);
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function getWishedRecipientIds(ProcInterface $proc): array {
    return $this->resolveForProc($proc)->getWishedRecipientIds($proc);
  }

  /**
   * {@inheritdoc}
   * @throws PluginException
   */
  public function setWishedRecipients(ProcInterface $proc, array $recipients): void {
    $this->resolveForProc($proc)->setWishedRecipients($proc, $recipients);
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientsForProcIds(array $proc_ids): array {
    if (empty($proc_ids)) {
      return [];
    }
    try {
      $procs = $this->entityTypeManager->getStorage('proc')->loadMultiple($proc_ids);
    }
    catch (PluginException $e) {
      $this->logger->error('RecipientStorageBroker: unable to load proc storage: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
    $result = [];
    foreach ($procs as $proc_id => $proc) {
      if ($proc instanceof ProcInterface) {
        $result[$proc_id] = $this->getRecipients($proc);
      }
    }
    return $result;
  }

  /**
   * Determines the recipient storage plugin ID bound to a cipher.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return string
   *   The bound plugin ID, or the default plugin ID when no valid binding is
   *   present.
   */
  protected function getBoundPluginId(ProcInterface $proc): string {
    $plugin_id = NULL;
    if ($proc->hasField('meta')) {
      $meta = $proc->get('meta')->getValue()[0] ?? [];
      if (is_array($meta) && !empty($meta[self::META_KEY])) {
        $plugin_id = (string) $meta[self::META_KEY];
      }
    }

    // Treat empty, "0" (the widget "- None -" option) or unknown plugin IDs as
    // the default, database-backed storage.
    if ($plugin_id === NULL || $plugin_id === '' || $plugin_id === '0') {
      return self::DEFAULT_PLUGIN_ID;
    }
    if (!$this->pluginManager->hasDefinition($plugin_id)) {
      $this->logger->warning('RecipientStorageBroker: cipher @id references unknown recipient storage plugin "@plugin"; falling back to the standard storage.', [
        '@id' => $proc->id() ?? 'new',
        '@plugin' => $plugin_id,
      ]);
      return self::DEFAULT_PLUGIN_ID;
    }
    return $plugin_id;
  }

  /**
   * Returns a (statically cached) plugin instance by ID.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return \Drupal\proc\RecipientStorageInterface
   *   The plugin instance. Falls back to the default plugin if instantiation
   *   of the requested plugin fails.
   * @throws PluginException
   */
  protected function getInstance(string $plugin_id): RecipientStorageInterface {
    if (isset($this->instances[$plugin_id])) {
      return $this->instances[$plugin_id];
    }
    try {
      /** @var \Drupal\proc\RecipientStorageInterface $instance */
      $instance = $this->pluginManager->createInstance($plugin_id);
    }
    catch (PluginException $e) {
      $this->logger->error('RecipientStorageBroker: could not instantiate recipient storage plugin "@plugin": @message. Falling back to the standard storage.', [
        '@plugin' => $plugin_id,
        '@message' => $e->getMessage(),
      ]);
      if ($plugin_id === self::DEFAULT_PLUGIN_ID) {
        // Should never happen: the default plugin is shipped by this module.
        throw $e;
      }
      return $this->getInstance(self::DEFAULT_PLUGIN_ID);
    }
    return $this->instances[$plugin_id] = $instance;
  }

}
