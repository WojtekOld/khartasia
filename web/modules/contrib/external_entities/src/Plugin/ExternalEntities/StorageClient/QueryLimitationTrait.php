<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

/**
 * Provides a trait for the storage client query rate limitation.
 */
trait QueryLimitationTrait {

  /**
   * Sleep a certain amount of time if needed.
   *
   * This method just waits the necessary amount of time before returning to let
   * the system query the endpoint. If the waiting time is longer than PHP
   * setting 'max_execution_time', it will not wait more so it may be possible
   * the endpoint got more queries than allowed in the given amount of time.
   * Also note that this method is far from perfect and can suffer from race
   * condition: two parallel calls may lead to one request time not being noted.
   */
  protected function ensureQueryLimits() {
    $qcount = $this->configuration['endpoint_options']['limit_qcount'] ?? 0;
    if (empty($qcount)) {
      return;
    }
    $qsec = $this->configuration['endpoint_options']['limit_qtime'] ?? 0;
    if (empty($qsec)) {
      return;
    }

    $connection = \Drupal::service('database');

    $endpoint = $this->getEndpointUrl();
    $endpoint_hash = md5($endpoint);

    $wait_limit = ini_get('max_execution_time') ?: 30;
    $wait_time = 0;
    while (TRUE) {
      $qtime = time();
      // Get query count for endpoint.
      $qtimes = $connection
        ->select('xntt_rest_queries', 'q')
        ->fields('q', ['qtimes'])
        ->condition('q.ephash', $endpoint_hash)
        ->execute()
        ->fetchAssoc()
        ?: ['qtimes' => ''];
      $queries = explode(',', $qtimes['qtimes']);

      // Cleanup old queries.
      $oldest = $qtime - $qsec;
      $need_update = FALSE;
      while (!empty($queries) && ($queries[0] < $oldest)) {
        array_shift($queries);
        $need_update = TRUE;
      }

      // Update db.
      if ($need_update) {
        $value = [
          'endpoint' => $endpoint,
          'ephash' => $endpoint_hash,
          'qtimes' => implode(',', $queries),
          'xntt_type' => $this->externalEntityType->id(),
          'username' => \Drupal::currentUser()->getAccountName(),
        ];
        $connection
          ->upsert('xntt_rest_queries')
          ->fields(['endpoint', 'ephash', 'qtimes', 'xntt_type', 'username'])
          ->key('ephash')
          ->values($value)
          ->execute();
      }

      ++$wait_time;
      // Check query count in the amount of time (and max wait time).
      if ((count($queries) < $qcount) || ($wait_time > $wait_limit)) {
        break;
      }
      // Wait between checks.
      usleep(static::WAIT_CHECK_QLIMIT);
    }

    // Add current query time.
    $queries[] = time();
    $value = [
      'endpoint' => $endpoint,
      'ephash' => $endpoint_hash,
      'qtimes' => implode(',', $queries),
      'xntt_type' => $this->externalEntityType->id(),
      'username' => \Drupal::currentUser()->getAccountName(),
    ];
    $connection
      ->upsert('xntt_rest_queries')
      ->fields(['endpoint', 'ephash', 'qtimes', 'xntt_type', 'username'])
      ->key('ephash')
      ->values($value)
      ->execute();
  }

}
