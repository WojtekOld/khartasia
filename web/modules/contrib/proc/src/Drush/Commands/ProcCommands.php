<?php

namespace Drupal\proc\Drush\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\proc\Traits\ProcCsvTrait;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\proc\ProcKeyManager;
use Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface;
use Drupal\proc\Service\UnreferencedCipherProcServiceInterface;

/**
 * Commands for managing protected content.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class ProcCommands extends DrushCommands implements ProcCommandsInterface {

  use ProcCsvTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The key manager.
   *
   * @var \Drupal\proc\ProcKeyManager
   */
  protected ProcKeyManager $keyManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Service for unreferenced cipher proc lookups.
   *
   * @var \Drupal\proc\Service\UnreferencedCipherProcServiceInterface
   */
  protected UnreferencedCipherProcServiceInterface $unreferencedCipherProcService;

  /**
   * Service to remove a proc update job from all keyrings.
   *
   * @var \Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface
   */
  protected RemoveUpdateJobForAllKeysServiceInterface $removeUpdateJobForAllKeysService;

  /**
   * Constructs a ProcCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\proc\ProcKeyManager $keyManager
   *   The proc key manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\proc\Service\UnreferencedCipherProcServiceInterface $unreferencedCipherProcService
   *   Service for fetching unreferenced cipher proc IDs.
   * @param \Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface $removeUpdateJobForAllKeysService
   *   Service for removing a proc update job from all keyrings.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ProcKeyManager $keyManager,
    Connection $database,
    UnreferencedCipherProcServiceInterface $unreferencedCipherProcService,
    RemoveUpdateJobForAllKeysServiceInterface $removeUpdateJobForAllKeysService,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->keyManager = $keyManager;
    $this->database = $database;
    $this->unreferencedCipherProcService = $unreferencedCipherProcService;
    $this->removeUpdateJobForAllKeysService = $removeUpdateJobForAllKeysService;
    parent::__construct();
  }

  /**
   * Create a new instance of this command.
   */
  public static function create(ContainerInterface $container): ProcCommands {
    return new ProcCommands(
      $container->get('entity_type.manager'),
      $container->get('proc.key_manager'),
      $container->get('database'),
      $container->get('proc.unreferenced_cipher_proc_service'),
      $container->get('proc.remove_update_job_for_all_keys_service')
    );
  }

  /**
   * Remove contents of wished set of recipients field in a given proc.
   *
   * @command proc:remove-wished
   * @aliases prw
   * @usage proc:remove-wished --proc_id 123
   *     Remove contents of wished set of recipients field in a given proc.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  #[CLI\Command(name: 'proc:remove-wished', aliases: ['prw'])]
  #[CLI\Usage(name: 'proc:remove-wished', description: 'Remove contents of wished set of recipients field in a given proc cipher text entity, if any.')]
  public function removeWishedRecipients(array $options = ['proc_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;

    if (empty($proc_id)) {
      $this->logger()->error('No proc_id provided.');
      return;
    }

    $entity = NULL;

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if ($storage) {
        $entity = $storage->load($proc_id);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Try next candidate type.
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }
    if (!$entity->hasField('field_wished_recipients_set')) {
      $this->logger()->warning("Entity {$proc_id} does not have field `field_wished_recipients_set`.");
      return;
    }
    if (empty($entity->get('field_wished_recipients_set')->getValue())) {
      $this->logger()->error("No wished recipients to remove for proc {$proc_id}.");
      return;
    }
    // Reset the field to an empty state.
    $entity->set('field_wished_recipients_set', []);
    try {
      $entity->save();
      $this->logger()->success("Reset field_wished_recipients_set for proc {$proc_id}.");
    }
    catch (EntityStorageException $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Get update jobs from given user ID.
   *
   * @command proc:get-update-jobs
   * @aliases guj
   * @usage proc:get-update-jobs --user_id 123
   *     Get update jobs from given user ID.
   */
  #[CLI\Command(name: 'proc:get-update-jobs', aliases: ['guj'])]
  #[CLI\Usage(name: 'guj', description: 'Get update jobs, if any, from given user ID.')]
  public function getUpdateJobsFromUserId(array $options = ['user_id' => NULL]): void {
    $user_id = $options['user_id'] ?? NULL;

    if (empty($user_id)) {
      $this->logger()->error('No user ID provided.');
      return;
    }

    try {
      $keyring = $this->keyManager->getKeys($user_id, 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Keyring for user ID {$user_id} not found.");
      return;
    }
    $update_jobs = $keyring['keyring_entity']->get('meta')->getValue()[0]['update_jobs'] ?? '';
    if (empty($update_jobs)) {
      $this->logger()->info('No update jobs found.');
      return;
    }
    sort($update_jobs);
    $update_jobs_string = implode("\n", $update_jobs);
    $this->logger()->success("Update job(s) for user ID {$user_id}: \n {$update_jobs_string}");

  }

  /**
   * Get update workers from given proc ID.
   *
   * @command proc:get-update-workers
   * @aliases guw
   * @usage proc:get-update-jobs --proc_id 123
   *     Get update workers from given proc ID.
   */
  #[CLI\Command(name: 'proc:get-update-workers', aliases: ['guw'])]
  #[CLI\Usage(name: 'guw', description: 'Get update workers, if any, from given proc ID.')]
  public function getUpdateWorkers(array $options = ['proc_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;

    if (empty($proc_id)) {
      $this->logger()->error('No proc ID provided.');
      return;
    }

    $entity = NULL;

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if ($storage) {
        $entity = $storage->load($proc_id);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Try next candidate type.
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }
    // Check among the recipients if any is marked as update worker, by having
    // in the update_jobs list in the metadata of the keyring the proc ID.
    $recipients = $entity->get('field_recipients_set')->getValue();
    $update_workers = [];
    foreach ($recipients as $recipient) {
      $recipient_id = $recipient['target_id'] ?? NULL;
      if (empty($recipient_id)) {
        continue;
      }
      try {
        $keyring = $this->keyManager->getKeys($recipient_id, 'user_id');
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger()
          ->error("Keyring for recipient ID {$recipient_id} not found.");
        continue;
      }
      $update_jobs = $keyring['keyring_entity']->get('meta')
        ->getValue()[0]['update_jobs'] ?? [];
      if (in_array($proc_id, $update_jobs, TRUE)) {
        $update_workers[] = $recipient_id;
      }
    }
    if (empty($update_workers)) {
      $this->logger()->info('No update workers found.');
      return;
    }
    // Sort the update workers list:
    sort($update_workers);

    $update_workers_str = implode("\n", $update_workers);
    $this->logger()
      ->success("Update workers for proc ID {$proc_id}: {$update_workers_str}");
  }

  /**
   * Remove given recipient from given proc by their IDs.
   *
   * @command proc:remove-recipient
   * @aliases prr
   * @usage proc:remove-recipient --proc_id 123 --user_id 456
   *     Remove given recipient from given proc by their IDs.
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  #[CLI\Command(name: 'proc:remove-recipient', aliases: ['prr'])]
  #[CLI\Usage(name: 'prr', description: 'Remove given recipient from given proc by their IDs.')]
  public function removeRecipient(array $options = ['proc_id' => NULL, 'user_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;
    $user_id = $options['user_id'] ?? NULL;

    if (empty($proc_id) || empty($user_id)) {
      $this->logger()->error('Both proc_id and user_id must be provided.');
      return;
    }

    // Load proc entity.
    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if (!$storage) {
        $this->logger()->error("Proc storage not available.");
        return;
      }
      $proc = $storage->load($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    if (!$proc) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    if (!$proc->hasField('field_recipients_set')) {
      $this->logger()->warning("Entity {$proc_id} does not have field `field_recipients_set`.");
      return;
    }

    $recipients = $proc->get('field_recipients_set')->getValue() ?? [];
    if (empty($recipients)) {
      $this->logger()->info("No recipients present on proc {$proc_id}.");
    }

    // Filter out the recipient to remove.
    $new_recipients = array_values(array_filter($recipients, function ($item) use ($user_id) {
      $target = $item['target_id'] ?? NULL;
      return $target === NULL || (string) $target !== (string) $user_id;
    }));

    if (count($new_recipients) === count($recipients)) {
      $this->logger()->info("Recipient {$user_id} not found on proc {$proc_id}.");
      return;
    }
    try {
      $proc->set('field_recipients_set', $new_recipients);
      $proc->save();
      $this->logger()->success("Removed recipient {$user_id} from proc {$proc_id}.");
    }
    catch (EntityStorageException $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Remove update jobs, if any, from given user ID.
   *
   * If proc_id is given, remove only that occurrence.
   *
   * @command proc:remove-update-jobs
   * @aliases ruj
   * @usage proc:remove-update-jobs --user_id 123 --proc_id 456
   *     Remove update jobs, if any, from given user ID.
   */
  #[CLI\Command(name: 'proc:remove-update-jobs', aliases: ['ruj'])]
  #[CLI\Usage(name: 'guj', description: 'Remove update jobs, if any, from given user ID. If proc_id is given, remove only that occurrence.')]
  public function removeUpdateJobsFromUserId(array $options = ['user_id' => NULL, 'proc_id' => NULL]): void {
    $user_id = $options['user_id'] ?? NULL;
    $proc_id = $options['proc_id'] ?? NULL;

    if (empty($user_id)) {
      $this->logger()->error('No user ID provided.');
      return;
    }

    try {
      $keyring = $this->keyManager->getKeys($user_id, 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Keyring for user ID {$user_id} not found.");
      return;
    }

    $keyring_entity = $keyring['keyring_entity'] ?? NULL;
    if (empty($keyring_entity) || !$keyring_entity->hasField('meta')) {
      $this->logger()->warning("Keyring entity for user ID {$user_id} has no meta field.");
      return;
    }

    $meta_values = $keyring_entity->get('meta')->getValue();
    $meta_item = $meta_values[0] ?? [];
    $current_update_jobs = $meta_item['update_jobs'] ?? [];

    if (empty($current_update_jobs)) {
      $this->logger()->info("No update jobs to remove for user ID {$user_id}.");
      return;
    }

    // If a specific proc_id was provided, remove only that occurrence.
    if (!empty($proc_id)) {
      $filtered = array_values(array_filter($current_update_jobs, function ($value) use ($proc_id) {
        return (string) $value !== (string) $proc_id;
      }));

      if (count($filtered) === count($current_update_jobs)) {
        $this->logger()->info("Proc ID {$proc_id} not found among update jobs for user ID {$user_id}.");
        return;
      }

      $meta_item['update_jobs'] = $filtered;
      $meta_values[0] = $meta_item;

      try {
        $keyring_entity->set('meta', $meta_values);
        $keyring_entity->save();
        $this->logger()->success("Removed proc ID {$proc_id} from update jobs for user ID {$user_id}.");
      }
      catch (EntityStorageException | \Exception $e) {
        $this->logger()->error($e->getMessage());
      }

      return;
    }

    $meta_item['update_jobs'] = [];
    $meta_values[0] = $meta_item;

    try {
      $keyring_entity->set('meta', $meta_values);
      $keyring_entity->save();
      $this->logger()->success("Removed update jobs for user ID {$user_id}.");
    }
    catch (EntityStorageException | \Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Add update job for user by given user and proc IDs.
   *
   * @command proc:add-update-job
   * @aliases auj
   * @usage proc:add-update-job --user_id 123 --proc_id 456
   *     Add update job for user by given user and proc IDs
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  #[CLI\Command(name: 'proc:add-update-job', aliases: ['auj'])]
  #[CLI\Usage(name: 'auj', description: 'Add update job for user by given user and proc IDs')]
  public function addUpdateJob(array $options = ['user_id' => NULL, 'proc_id' => NULL]): void {
    $user_id = $options['user_id'] ?? NULL;
    $proc_id = $options['proc_id'] ?? NULL;

    if (empty($user_id) || empty($proc_id)) {
      $this->logger()->error('Both user_id and proc_id must be provided.');
      return;
    }

    try {
      $keyring = $this->keyManager->getKeys($user_id, 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Keyring for user ID {$user_id} not found.");
      return;
    }

    $keyring_entity = $keyring['keyring_entity'] ?? NULL;
    if (empty($keyring_entity) || !$keyring_entity->hasField('meta')) {
      $this->logger()->warning("Keyring entity for user ID {$user_id} has no meta field.");
      return;
    }

    $meta_values = $keyring_entity->get('meta')->getValue();
    $meta_item = $meta_values[0] ?? [];
    $current_update_jobs = $meta_item['update_jobs'] ?? [];

    if (!is_array($current_update_jobs)) {
      $current_update_jobs = (array) $current_update_jobs;
    }

    // Avoid adding duplicates (string comparison to be robust).
    foreach ($current_update_jobs as $existing) {
      if ((string) $existing === (string) $proc_id) {
        $this->logger()->info("Proc ID {$proc_id} is already registered as an update job for user {$user_id}.");
        return;
      }
    }

    $current_update_jobs[] = $proc_id;
    $meta_item['update_jobs'] = $current_update_jobs;
    $meta_values[0] = $meta_item;

    try {
      $keyring_entity->set('meta', $meta_values);
      $keyring_entity->save();
      $this->logger()->success("Added proc ID {$proc_id} to update jobs for user ID {$user_id}.");
    }
    catch (EntityStorageException | \Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Remove all occurrences of given update job.
   *
   * @command proc:remove-all-jobs
   * @aliases raj
   * @usage proc:remove-all-jobs --proc_id 123
   *     Remove all occurrences of given update job
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  #[CLI\Command(name: 'proc:remove-all-jobs', aliases: ['raj'])]
  #[CLI\Usage(name: 'raj', description: 'Remove all occurrences of given update job.')]
  public function removeUpdateJobForAllKeys(array $options = ['proc_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;

    if (empty($proc_id)) {
      $this->logger()->error('No proc_id provided.');
      return;
    }

    try {
      $result = $this->removeUpdateJobForAllKeysService
        ->removeUpdateJobForAllKeys($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error('Unable to load user storage to iterate keys.');
      return;
    }

    $checked = $result['checked'] ?? 0;
    $modified = $result['modified'] ?? 0;

    if ($checked === 0) {
      $this->logger()->info('No keyring entities found to process.');
      return;
    }

    $this->logger()->success("Processed {$checked} keyring(s). Removed proc ID {$proc_id} from {$modified} keyring(s).");
  }

  /**
   * Get IDs of procs where given user is a recipient.
   *
   * @command proc:get-procs-user-is-recipient
   * @aliases gpur
   * @usage proc:get-procs-user-is-recipient --user_id 123
   *     Get IDs of procs where given user is a recipient.
   */
  #[CLI\Command(name: 'proc:get-procs-user-is-recipient', aliases: ['gpur'])]
  #[CLI\Usage(name: 'gpur', description: 'Get IDs of procs where given user is a recipient.')]
  public function getProcsUserIsRecipient(array $options = ['user_id' => NULL]): void {
    $user_id = $options['user_id'] ?? NULL;

    if (empty($user_id)) {
      $this->logger()->error('No user ID provided.');
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if (!$storage) {
        $this->logger()->error("Proc storage not available.");
        return;
      }
      $query = $storage->getQuery();
      $query->accessCheck(TRUE);
      $query->condition('field_recipients_set.target_id', $user_id);
      $proc_ids = $query->execute();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Unable to query proc entities.");
      return;
    }

    if (empty($proc_ids)) {
      $this->logger()->info("User ID {$user_id} is not a recipient of any proc.");
      return;
    }

    sort($proc_ids);
    $proc_ids_str = implode("\n", $proc_ids);
    $this->logger()->success("User ID {$user_id} is a recipient of the following proc IDs:\n{$proc_ids_str}");

  }

  /**
   * Convert a CSV list of user IDs into a CSV list of the labels of their keys.
   *
   * @command proc:user-keys-labels
   * @aliases pql
   * @usage proc:user-keys-labels --user_ids 1,2,3
   *   Return a CSV list of key labels for the given CSV user IDs.
   */
  #[CLI\Command(name: 'proc:user-keys-labels', aliases: ['pql'])]
  #[CLI\Usage(name: 'proc:user-keys-labels', description: 'Given a CSV list of user IDs, return a CSV list of the labels of their keyrings.')]
  public function userIdsToKeysLabels(array $options = ['user_ids' => NULL]): void {
    $user_ids_csv = $options['user_ids'] ?? NULL;
    if (empty($user_ids_csv)) {
      $this->logger()->error('No user IDs CSV provided.');
      return;
    }
    $ids = $this->getCsvArgument($user_ids_csv);
    if (empty($ids)) {
      $this->logger()->error('No valid user IDs parsed from input.');
      return;
    }

    $labels = [];
    foreach ($ids as $id) {
      try {
        $keyring = $this->keyManager->getKeys($id);
        if (!$keyring) {
          $this->logger()->warning("Keyring for user ID {$id} not found.");
          continue;
        }
        $label = (string) $this->keyManager->getKeys($id)['label'] ?? '';
        if (empty($label)) {
          $this->logger()->warning("Keyring for user ID {$id} has no label.");
          continue;
        }
        $labels[] = $label;
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger()->warning("Keyring for user ID {$id} not found.");
      }
    }

    $labels_csv = implode(',', $labels);
    $this->logger()->success($labels_csv);
  }

  /**
   * Return a CSV list of the referenced host entity IDs.
   *
   * @command proc:procs-host-ids
   * @aliases phis
   * @usage proc:procs-host-ids --proc_ids 10,11,12 --host_field field_host --host_entity_type node
   *   Return a CSV list of host entity IDs referencing the given proc IDs.
   */
  #[CLI\Command(name: 'proc:procs-host-ids', aliases: ['phis'])]
  #[CLI\Usage(
    name: 'proc:procs-host-ids',
    description: 'Given a CSV list of proc IDs, a host entity type and field machine name, return a list of host entity IDs.'
  )]
  public function procIdsToHostIds(
    array $options = [
      'proc_ids' => NULL,
      'host_fields' => NULL,
      'host_entity_type' => NULL,
    ],
  ): void {
    $proc_ids_csv = $options['proc_ids'] ?? NULL;
    $host_fields = str_getcsv($options['host_fields'] ?? '', ',', '"', '');
    $host_entity_type = $options['host_entity_type'] ?? NULL;

    if (empty($proc_ids_csv) || empty($options['host_fields']) || empty($host_entity_type)) {
      $this->logger()->error('proc_ids, host_fields and host_entity_type must be provided.');
      return;
    }

    $ids = $this->getCsvArgument($proc_ids_csv);
    if (empty($ids)) {
      $this->logger()->error('No valid proc IDs parsed from input.');
      return;
    }

    // Query host entities referencing the given proc IDs.
    try {
      $storage = $this->entityTypeManager->getStorage($host_entity_type);
      if (!$storage) {
        $this->logger()->error("Storage for entity type {$host_entity_type} not available.");
        return;
      }
      $host_entity_ids = [];
      foreach ($host_fields as $host_field) {
        $query = $storage->getQuery();
        $query->accessCheck(TRUE);
        $query->condition($host_field, $ids, 'IN');
        $result_ids = $query->execute();
        if (!empty($result_ids)) {
          $host_entity_ids = array_merge($host_entity_ids, $result_ids);
        }
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Unable to query entities of type {$host_entity_type}.");
      return;
    }

    if (empty($host_entity_ids)) {
      $this->logger()->info('No host entity IDs found for the provided procs.');
      return;
    }
    // Preserve order but remove duplicates.
    $host_ids = array_values(array_unique($host_entity_ids));
    sort($host_ids);
    $this->logger()->success(implode(',', $host_ids));
  }

  /**
   * Given a CSV list of proc IDs, return a CSV list of their labels.
   *
   * @command proc:procs-labels
   * @aliases plb
   * @usage proc:procs-labels --proc_ids 10,11,12
   *   Return a CSV list of labels for the given proc IDs.
   */
  #[CLI\Command(name: 'proc:procs-labels', aliases: ['plb'])]
  #[CLI\Usage(name: 'proc:procs-labels', description: 'Given a CSV list of proc IDs, return a CSV list of their labels.')]
  public function procIdsToLabels(array $options = ['proc_ids' => NULL]): void {
    $proc_ids_csv = $options['proc_ids'] ?? NULL;
    if (empty($proc_ids_csv)) {
      $this->logger()->error('No proc IDs CSV provided.');
      return;
    }

    $ids = $this->getCsvArgument($proc_ids_csv);
    if (empty($ids)) {
      $this->logger()->error('No valid proc IDs parsed from input.');
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if (!$storage) {
        $this->logger()->error('Proc storage not available.');
        return;
      }
    }
    catch (\Exception $e) {
      $this->logger()->error('Unable to access proc storage: ' . $e->getMessage());
      return;
    }

    $labels = [];
    foreach ($ids as $id) {
      try {
        $entity = $storage->load($id);
      }
      catch (\Exception $e) {
        $this->logger()->warning("Failed loading proc {$id}: " . $e->getMessage());
        $labels[] = '';
        continue;
      }

      if (empty($entity)) {
        $this->logger()->warning("Proc entity with ID {$id} not found.");
        $labels[] = '';
        continue;
      }

      // Use the entity label; fall back to an empty string if not available.
      $label = method_exists($entity, 'label') ? (string) $entity->label() : (string) $entity->id();
      $labels[] = $label;
    }

    $labels_csv = implode(',', $labels);
    $this->logger()->success($labels_csv);
  }

  /**
   * Find cipher proc entities missing required meta fields.
   *
   * Checks the 'meta' field of all proc entities of type 'cipher' and returns
   * the proc IDs where any of the following keys are missing or empty:
   * - generation_timestamp
   * - source_file_name
   * - source_file_size.
   *
   * New options:
   *   --start_limit <id>
   *   --end_limit <id>
   *
   * @command proc:find-cipher-missing-meta
   * @aliases pcm
   * @usage proc:find-cipher-missing-meta
   *   Return a CSV list cipher proc entities missing required meta fields.
   */
  #[CLI\Command(name: 'proc:find-cipher-missing-meta', aliases: ['pcm'])]
  #[CLI\Usage(name: 'proc:find-cipher-missing-meta', description: 'Return proc IDs of cipher entities missing key meta entries.')]
  public function findCipherMissingMeta(array $options = ['start_limit' => NULL, 'end_limit' => NULL]): void {
    $required_keys = [
      'generation_timestamp',
      'source_file_name',
      'source_file_size',
    ];

    // Accept optional range limits.
    $start_limit = $options['start_limit'] ?? NULL;
    $end_limit = $options['end_limit'] ?? NULL;

    if ($start_limit !== NULL && $start_limit !== '' && !is_numeric($start_limit)) {
      $this->logger()->error('start_limit must be an integer.');
      return;
    }
    if ($end_limit !== NULL && $end_limit !== '' && !is_numeric($end_limit)) {
      $this->logger()->error('end_limit must be an integer.');
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if (!$storage) {
        $this->logger()->error('Proc storage not available.');
        return;
      }
      $query = $storage->getQuery();
      $query->condition('type', 'cipher');

      // Apply optional ID range conditions.
      if ($start_limit !== NULL && $start_limit !== '') {
        $query->condition('id', (int) $start_limit, '>=');
      }
      if ($end_limit !== NULL && $end_limit !== '') {
        $query->condition('id', (int) $end_limit, '<=');
      }

      $query->accessCheck(TRUE);
      $proc_ids = $query->execute();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error('Unable to query proc entities of type cipher.');
      return;
    }
    catch (\Exception $e) {
      $this->logger()->error('Unexpected error while querying proc entities: ' . $e->getMessage());
      return;
    }

    if (empty($proc_ids)) {
      $this->logger()->info('No cipher proc entities found.');
      return;
    }

    $offending = [];
    $batch_ids = array_values($proc_ids);

    // Load in one call; adjust if memory becomes an issue.
    $entities = $storage->loadMultiple($batch_ids);

    foreach ($entities as $entity) {
      // If no meta field or no values, treat as missing.
      if (!$entity->hasField('meta')) {
        $offending[] = (string) $entity->id();
        continue;
      }

      $meta_values = $entity->get('meta')->getValue();

      $meta_item = $meta_values[0] ?? [];
      $missing_any = FALSE;

      foreach ($required_keys as $key) {
        if (!array_key_exists($key, $meta_item) || empty($meta_item[$key])) {
          $missing_any = TRUE;
          break;
        }
      }

      if ($missing_any) {
        $offending[] = (string) $entity->id();
      }
    }

    if (empty($offending)) {
      $this->logger()->info('All cipher entities have the required meta entries.');
      return;
    }

    // Return CSV of offending proc IDs.
    $this->logger()->success(implode(',', $offending));
  }

  /**
   * Count cipher proc entities that have values in field_wished_recipients_set.
   *
   * @command proc:count-cipher-wished-recipients
   * @aliases pcwr
   * @usage proc:count-cipher-wished-recipients
   *   Count cipher proc entities with non-empty field_wished_recipients_set.
   */
  #[CLI\Command(name: 'proc:count-cipher-wished-recipients', aliases: ['pcwr'])]
  #[CLI\Usage(name: 'proc:count-cipher-wished-recipients', description: 'Count cipher proc entities with non-empty field_wished_recipients_set.')]
  public function countCipherWithWishedRecipients(): void {
    $count = 0;
    try {
      $query = $this->database->select('proc__field_wished_recipients_set', 'pfrs');
      $query->addExpression('COUNT(DISTINCT pfrs.entity_id)', 'count');
      $query->condition('pfrs.entity_id', NULL, 'IS NOT NULL');
      $result = $query->execute()->fetchField();
      if ($result !== FALSE) {
        $count = (int) $result;
      }
    }
    catch (\Exception $e) {
      $this->logger()->error('Error querying proc entities: ' . $e->getMessage());
      return;
    }

    $this->logger()->success("Found {$count} cipher proc entity(ies) with non-empty field_wished_recipients_set.");
  }

  /**
   * Get cipher proc IDs not referenced by proc fields.
   *
   * Options:
   *   --count_only
   *     Return only the total number of IDs instead of the CSV list.
   *   --orphan-minimal-age=<integer>
   *     Return only unreferenced proc entities older than the specified number
   *     of seconds (based on the 'created' timestamp).
   *
   * @command proc:get-unreferenced-cipher-procs
   * @aliases gucp
   * @usage proc:get-unreferenced-cipher-procs
   *   Return a CSV list of cipher proc IDs not referenced by
   *   any field of type proc_entity_reference_field.
   * @usage proc:get-unreferenced-cipher-procs --count_only
   *   Return only the number of unreferenced cipher proc IDs.
   * @usage proc:get-unreferenced-cipher-procs --orphan-minimal-age=86400
   *   Return unreferenced cipher proc IDs older than 86400 seconds (1 day).
   * @usage proc:get-unreferenced-cipher-procs --orphan-minimal-age=86400 --count_only
   *   Return the count of unreferenced cipher proc IDs older than 86400
   * seconds.
   */
  #[CLI\Command(name: 'proc:get-unreferenced-cipher-procs', aliases: ['gucp'])]
  #[CLI\Usage(name: 'proc:get-unreferenced-cipher-procs', description: 'Return IDs of cipher proc entities not referenced by fields of type proc_entity_reference_field.')]
  public function getUnreferencedCipherProcIds(array $options = ['count_only' => FALSE, 'orphan-minimal-age' => NULL]): void {
    $count_only = $options['count_only'] ?? FALSE;
    $orphan_minimal_age = $options['orphan-minimal-age'] ?? NULL;

    // Validate and parse count_only option.
    if (!is_bool($count_only)) {
      if (is_string($count_only) || is_numeric($count_only)) {
        $normalized = filter_var($count_only, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === NULL) {
          $this->logger()->error('count_only must be a boolean-like value (0/1, true/false, yes/no).');
          return;
        }
        $count_only = $normalized;
      }
      else {
        $this->logger()->error('count_only must be a boolean-like value (0/1, true/false, yes/no).');
        return;
      }
    }

    // Validate orphan_minimal_age option.
    if ($orphan_minimal_age !== NULL) {
      if (!is_numeric($orphan_minimal_age) || (int) $orphan_minimal_age < 0) {
        $this->logger()->error('orphan-minimal-age must be a non-negative integer representing seconds.');
        return;
      }
      $orphan_minimal_age = (int) $orphan_minimal_age;
    }

    try {
      $result = $this->unreferencedCipherProcService
        ->getUnreferencedCipherProcIds($count_only, $orphan_minimal_age);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error('Unable to retrieve unreferenced cipher proc entities.');
      return;
    }

    if ($count_only) {
      $this->logger()->success((string) $result);
      return;
    }

    $unreferenced_proc_ids = $result;
    if (empty($unreferenced_proc_ids)) {
      $age_suffix = $orphan_minimal_age !== NULL ? " older than {$orphan_minimal_age} seconds" : '';
      $this->logger()->info("All cipher proc entities{$age_suffix} are referenced by fields of type proc_entity_reference_field.");
      return;
    }

    $this->logger()->success(implode(',', $unreferenced_proc_ids));
  }

  /**
   * Add given user as a recipient to given proc by their IDs.
   *
   * @command proc:add-recipient
   * @aliases par
   * @usage proc:add-recipient --proc_id 123 --user_id 456
   *     Add given user as a recipient to given proc by their IDs.
   */
  #[CLI\Command(name: 'proc:add-recipient', aliases: ['par'])]
  #[CLI\Usage(name: 'par', description: 'Add given user as a recipient to given proc by their IDs.')]
  public function addRecipient(array $options = ['proc_id' => NULL, 'user_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;
    $user_id = $options['user_id'] ?? NULL;

    if (empty($proc_id) || empty($user_id)) {
      $this->logger()->error('Both proc_id and user_id must be provided.');
      return;
    }

    // Load proc entity.
    try {
      $proc_storage = $this->entityTypeManager->getStorage('proc');
      if (!$proc_storage) {
        $this->logger()->error("Proc storage not available.");
        return;
      }
      $proc = $proc_storage->load($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    if (empty($proc)) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    // Ensure proc is of type 'cipher'.
    $bundle = $proc->get('type')->value ?? NULL;
    if ((string) $bundle !== 'cipher') {
      $this->logger()->error("Proc {$proc_id} is not of type 'cipher'.");
      return;
    }

    if (!$proc->hasField('field_recipients_set')) {
      $this->logger()->warning("Entity {$proc_id} does not have field `field_recipients_set`.");
      return;
    }

    // Ensure user exists.
    try {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $user = $user_storage ? $user_storage->load($user_id) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("User storage not available or user {$user_id} not found.");
      return;
    }
    if (empty($user)) {
      $this->logger()->error("User with ID {$user_id} not found.");
      return;
    }

    $recipients = $proc->get('field_recipients_set')->getValue() ?? [];

    // Check for existing entry.
    foreach ($recipients as $entry) {
      $target = $entry['target_id'] ?? NULL;
      if ($target !== NULL && (string) $target === (string) $user_id) {
        $this->logger()->info("User {$user_id} is already a recipient on proc {$proc_id}.");
        return;
      }
    }

    // Append new recipient.
    $recipients[] = ['target_id' => $user_id];

    try {
      $proc->set('field_recipients_set', $recipients);
      $proc->save();
      $this->logger()->success("Added user {$user_id} as recipient to proc {$proc_id}.");
    }
    catch (EntityStorageException | \Exception $e) {
      $this->logger()->error("Failed to add recipient: " . $e->getMessage());
    }
  }

  /**
   * Add given user as a wished recipient to given proc by their IDs.
   *
   * @command proc:add-wished-recipient
   * @aliases pwr
   * @usage proc:add-wished-recipient --proc_id 123 --user_id 456
   *     Add given user as a wished recipient to given proc by their IDs.
   */
  #[CLI\Command(name: 'proc:add-wished-recipient', aliases: ['pwr'])]
  #[CLI\Usage(name: 'pwr', description: 'Add given user as a wished recipient to given proc by their IDs.')]
  public function addWishedRecipient(array $options = ['proc_id' => NULL, 'user_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;
    $user_id = $options['user_id'] ?? NULL;

    if (empty($proc_id) || empty($user_id)) {
      $this->logger()->error('Both proc_id and user_id must be provided.');
      return;
    }

    // Load proc entity.
    try {
      $proc_storage = $this->entityTypeManager->getStorage('proc');
      if (!$proc_storage) {
        $this->logger()->error("Proc storage not available.");
        return;
      }
      $proc = $proc_storage->load($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    if (empty($proc)) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    // Ensure proc is of type 'cipher'.
    $bundle = $proc->get('type')->value ?? NULL;
    if ((string) $bundle !== 'cipher') {
      $this->logger()->error("Proc {$proc_id} is not of type 'cipher'.");
      return;
    }

    if (!$proc->hasField('field_wished_recipients_set')) {
      $this->logger()->warning("Entity {$proc_id} does not have field `field_wished_recipients_set`.");
      return;
    }

    // Ensure user exists.
    try {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $user = $user_storage ? $user_storage->load($user_id) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("User storage not available or user {$user_id} not found.");
      return;
    }
    if (empty($user)) {
      $this->logger()->error("User with ID {$user_id} not found.");
      return;
    }

    $wished = $proc->get('field_wished_recipients_set')->getValue() ?? [];

    // Check for existing entry.
    foreach ($wished as $entry) {
      $target = $entry['target_id'] ?? NULL;
      if ($target !== NULL && (string) $target === (string) $user_id) {
        $this->logger()->info("User {$user_id} is already a wished recipient on proc {$proc_id}.");
        return;
      }
    }

    // Append new wished recipient.
    $wished[] = ['target_id' => $user_id];

    try {
      $proc->set('field_wished_recipients_set', $wished);
      $proc->save();
      $this->logger()->success("Added user {$user_id} as wished recipient to proc {$proc_id}.");
    }
    catch (EntityStorageException | \Exception $e) {
      $this->logger()->error("Failed to add wished recipient: " . $e->getMessage());
    }
  }

  /**
   * Scan update jobs by user and detect invalid update job IDs.
   *
   * An invalid update job ID is one that does not exist as a proc cipher
   * entity. Outputs CSV with columns: user_id, key_id, invalid_update_job_id.
   *
   * @command proc:find-invalid-update-jobs
   * @aliases fiuj
   * @usage proc:find-invalid-update-jobs
   *   Scan all users and output CSV of invalid update job IDs.
   */
  #[CLI\Command(name: 'proc:find-invalid-update-jobs', aliases: ['fiuj'])]
  #[CLI\Usage(name: 'proc:find-invalid-update-jobs', description: 'Scan update jobs by user and output CSV of those referencing non-existent cipher entities.')]
  public function findInvalidUpdateJobs(): void {

    try {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $proc_storage = $this->entityTypeManager->getStorage('proc');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error('Unable to load required entity storage.');
      return;
    }

    $users = $user_storage->loadMultiple();
    $rows = [];

    foreach ($users as $user) {
      $uid = $user->id();
      try {
        $keyring = $this->keyManager->getKeys($uid, 'user_id');
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        continue;
      }

      if (empty($keyring['keyring_entity'])) {
        continue;
      }

      $key_id = $keyring['keyring_cid'] ?? '';
      $update_jobs = $keyring['keyring_entity']->get('meta')->getValue()[0]['update_jobs'] ?? [];

      if (empty($update_jobs) || !is_array($update_jobs)) {
        continue;
      }

      foreach ($update_jobs as $job_id) {
        $entity = $proc_storage->load($job_id);
        if (!$entity || $entity->get('type')->value !== 'cipher') {
          $rows[] = "{$uid},{$key_id},{$job_id}";
        }
      }
    }

    if (empty($rows)) {
      $this->logger()->info('No invalid update jobs found.');
      return;
    }

    $csv = "user_id,key_id,invalid_update_job_id\n" . implode("\n", $rows);
    $this->output()->writeln($csv);
  }

  /**
   * Get values of field_wished_recipients_set for a given proc ID.
   *
   * @command proc:get-wished-recipients
   * @aliases gwr
   * @usage proc:get-wished-recipients --proc_id 123
   *   Return the raw values of the field_wished_recipients_set for the given
   *   proc.
   */
  #[CLI\Command(name: 'proc:get-wished-recipients', aliases: ['gwr'])]
  #[CLI\Usage(name: 'gwr', description: 'Get values of field_wished_recipients_set for a given proc ID.')]
  public function getWishedRecipients(array $options = ['proc_id' => NULL]): void {
    $proc_id = $options['proc_id'] ?? NULL;

    if (empty($proc_id)) {
      $this->logger()->error('No proc_id provided.');
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
      if (!$storage) {
        $this->logger()->error("Proc storage not available.");
        return;
      }
      $proc = $storage->load($proc_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    if (empty($proc)) {
      $this->logger()->error("Proc entity with ID {$proc_id} not found.");
      return;
    }

    if (!$proc->hasField('field_wished_recipients_set')) {
      $this->logger()->warning("Entity {$proc_id} does not have field `field_wished_recipients_set`.");
      return;
    }

    $values = $proc->get('field_wished_recipients_set')->getValue() ?? [];

    if (empty($values)) {
      $this->logger()->info("No values present in field_wished_recipients_set for proc {$proc_id}.");
      return;
    }
    $values_id = [];
    foreach ($values as $value) {
      $values_id[] = $value['target_id'];
    }

    // Present the raw field values as JSON for readability.
    $this->logger()->success("Values for field_wished_recipients_set on proc {$proc_id}: " . implode(', ', $values_id));
  }

}
