<?php

declare(strict_types=1);

namespace Drupal\proc_reporting\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service for proc_reporting: records daily operations and generates snapshots.
 */
class ProcReportingService {

  /**
   * Columns allowed for the daily-operations counter.
   */
  private const ALLOWED_COLUMNS = ['encrypt_count', 'cipher_text_download_count', 'reencrypt_count'];

  /**
   * ProcReportingService constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $logger,
    protected StateInterface $state,
    protected TimeInterface $time,
  ) {}

  /**
   * Record a single operation for a user in today's daily-operations row.
   *
   * If no row exists for (uid, today) it is created, otherwise the relevant
   * counter column is incremented by 1.
   *
   * @param int $uid
   *   The Drupal user ID.
   * @param string $userName
   *   The user's account name.
   * @param string $column
   *   Counter to increment: 'encrypt_count', 'cipher_text_download_count', or
   *   'reencrypt_count'.
   * @param int $increment
   *   Amount to increment by.
   *
   * @throws \Exception
   */
  public function recordOperation(int $uid, string $userName, string $column, int $increment = 1): void {
    if ($uid <= 0) {
      return;
    }
    if ($increment <= 0) {
      return;
    }
    if (!in_array($column, self::ALLOWED_COLUMNS, TRUE)) {
      $this->logger->get('proc_reporting')->warning(
        'recordOperation called with invalid column "@col".',
        ['@col' => $column]
      );
      return;
    }

    $now = $this->time->getRequestTime();
    $date = date('Y-m-d', $now);

    // Try to find an existing row for this user + date.
    $existing_id = $this->database
      ->select('proc_reporting_daily_operations', 'p')
      ->fields('p', ['id'])
      ->condition('uid', $uid)
      ->condition('operation_date', $date)
      ->execute()
      ->fetchField();

    if (!$existing_id) {
      $fields = [
        'operation_date' => $date,
        'uid' => $uid,
        'user_name' => $userName,
        'encrypt_count' => 0,
        'cipher_text_download_count' => 0,
        'reencrypt_count' => 0,
        'changed' => $now,
      ];
      // Set the target column to the current increment for the first row.
      $fields[$column] = $increment;
      $this->database
        ->insert('proc_reporting_daily_operations')
        ->fields($fields)
        ->execute();
      return;
    }
    // Increment the counter.
    $this->database
      ->update('proc_reporting_daily_operations')
      ->expression($column, "{$column} + {$increment}")
      ->fields(['user_name' => $userName, 'changed' => $now])
      ->condition('id', $existing_id)
      ->execute();
  }

  /**
   * Generate a snapshot of pending update-task counts for all active users.
   *
   * Queries the proc table directly (same technique as
   * CleanUpExpiredUpdateJobService) to retrieve the latest keyring per user
   * and count its update_jobs. Only users with at least one pending task are
   * written to the snapshot table. The state key
   * 'proc_reporting.last_snapshot_run' is updated at the end so that
   * hook_cron can throttle to one run per hour.
   *
   * @param int $timestamp
   *   Unix timestamp to use for the generated_at column of every row created
   *   during this snapshot run.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function snapshotUpdateTasks(int $timestamp): void {
    $keyrings_query = $this->database->select('proc', 'p')
      ->fields('p', ['user_id'])
      ->condition('p.type', 'cipher', '!=')
      ->condition('p.status', 1)
      ->groupBy('p.user_id');
    $keyrings_query->addExpression('MAX(p.id)', 'max_id');

    /** @var \stdClass[] $user_keyrings */
    $user_keyrings = $keyrings_query->execute()->fetchAll();

    if (empty($user_keyrings)) {
      $this->state->set('proc_reporting.last_snapshot_run', $timestamp);
      return;
    }

    $keyring_ids = array_map(static fn(\stdClass $r) => $r->max_id, $user_keyrings);

    /** @var array<int,string> $metas  [keyring_id => serialized_meta] */
    $metas = $this->database->select('proc', 'p')
      ->fields('p', ['id', 'meta'])
      ->condition('p.id', $keyring_ids, 'IN')
      ->execute()
      ->fetchAllKeyed();

    $uid_to_max_id = [];
    foreach ($user_keyrings as $row) {
      $uid_to_max_id[(int) $row->user_id] = (int) $row->max_id;
    }

    $user_ids = array_keys($uid_to_max_id);
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($user_ids);

    $snapshot_count = 0;

    foreach ($uid_to_max_id as $uid => $max_id) {
      $meta_raw = $metas[$max_id] ?? NULL;
      if (empty($meta_raw)) {
        continue;
      }

      $meta = unserialize($meta_raw, ['allowed_classes' => FALSE]);
      if (!is_array($meta)) {
        continue;
      }

      $update_jobs = $meta['update_jobs'] ?? [];
      $count = is_array($update_jobs) ? count($update_jobs) : 0;

      // Skip users with no pending tasks – no need to store a zero row.
      if ($count <= 0) {
        continue;
      }

      /** @var \Drupal\user\UserInterface|null $user */
      $user = $users[$uid] ?? NULL;
      $user_name = $user ? $user->getAccountName() : "uid:{$uid}";

      $this->database->insert('proc_reporting_update_tasks_snapshot')
        ->fields([
          'uid' => $uid,
          'user_name' => $user_name,
          'update_task_count' => $count,
          'generated_at' => $timestamp,
        ])
        ->execute();

      $snapshot_count++;
    }

    $this->state->set('proc_reporting.last_snapshot_run', $timestamp);

    $this->logger->get('proc_reporting')->info(
      'Update-tasks snapshot done at @ts: @count user(s) with pending tasks recorded.',
      [
        '@ts' => date('Y-m-d H:i:s', $timestamp),
        '@count' => $snapshot_count,
      ]
    );
  }

  /**
   * Return a paginated result set of update-task snapshots (newest first).
   *
   * The result is already passed through Drupal's PagerSelectExtender so that
   * the caller can render a standard #type => pager element.
   *
   * @param int $page_size
   *   Rows per page.
   *
   * @return \Traversable
   *   Database result set.
   */
  public function getUpdateTaskSnapshots(int $page_size = 50): \Traversable {
    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
    $query = $this->database->select('proc_reporting_update_tasks_snapshot', 'p')
      ->fields('p')
      ->orderBy('generated_at', 'DESC')
      ->orderBy('update_task_count', 'DESC')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($page_size);

    return $query->execute();
  }

  /**
   * Return a paginated result set of daily operations (newest first).
   *
   * @param int $page_size
   *   Rows per page.
   * @param string $date_filter
   *   Optional date in YYYY-MM-DD format. Empty string = no filter.
   * @param int $uid_filter
   *   Optional user ID filter. 0 = no filter.
   *
   * @return \Traversable
   *   Database result set.
   */
  public function getDailyOperations(
    int $page_size = 50,
    string $date_filter = '',
    int $uid_filter = 0,
  ): \Traversable {
    $query = $this->database->select('proc_reporting_daily_operations', 'p')
      ->fields('p')
      ->orderBy('operation_date', 'DESC')
      ->orderBy('uid', 'ASC');

    if ($date_filter !== '') {
      $query->condition('operation_date', $date_filter);
    }
    if ($uid_filter > 0) {
      $query->condition('uid', $uid_filter);
    }

    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $paged */
    $paged = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($page_size);

    return $paged->execute();
  }

}
