<?php

declare(strict_types=1);

namespace Drupal\security_review\Plugin\SecurityCheck;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\security_review\Attribute\SecurityCheck;
use Drupal\security_review\CheckResult;
use Drupal\security_review\SecurityCheckBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks for abundant query errors.
 */
#[SecurityCheck(
  id: 'query_errors',
  title: new TranslatableMarkup('Query errors'),
  description: new TranslatableMarkup('Checks for abundant query errors.'),
  namespace: new TranslatableMarkup('Security Review'),
  success_message: new TranslatableMarkup('No query errors from same IP found.'),
  failure_message: new TranslatableMarkup('Query errors from the same IP. These may be a SQL injection attack or an attempt at information disclosure.'),
  info_message: new TranslatableMarkup('Query errors - Dblog module not installed.'),
  help: [
    new TranslatableMarkup('Database errors triggered from the same IP may be an artifact of a malicious user attempting to probe the system for weaknesses like SQL injection or information disclosure.'),
  ]
)]
class QueryErrors extends SecurityCheckBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(bool $cli = FALSE): void {
    $result = CheckResult::SUCCESS;
    $findings = [];

    // If dblog is not enabled, return with hidden INFO.
    if (!$this->moduleHandler->moduleExists('dblog')) {
      $result = CheckResult::INFO;
    }
    else {

      $last_result = $this->lastResult();

      // Prepare the query.
      $query = $this->database->select('watchdog', 'w');
      $query->fields('w', [
        'severity',
        'type',
        'timestamp',
        'message',
        'variables',
        'hostname',
      ]);
      $query->condition('type', 'php')->condition('severity', RfcLogLevel::ERROR);

      if (isset($last_result['time'])) {
        $query->condition('timestamp', $last_result['time'], '>=');
      }

      // Execute the query.
      $db_result = $query->execute();

      // Count the number of query errors per IP.
      $entries = [];
      foreach ($db_result as $row) {
        // Get the message.
        if ($row->variables === 'N;') {
          $message = $row->message;
        }
        else {
          if (\is_array($row->variables)) {
            $message = $row->message . unserialize($row->variables, ['allowed_classes' => FALSE]);
          }
          else {
            $message = $row->message . $row->variables;
          }
        }

        // Get the IP.
        $ip = $row->hostname;

        // Search for query errors.
        $message_contains_sql = str_contains($message, 'SQL');
        $message_contains_select = str_contains($message, 'SELECT');
        if ($message_contains_sql && $message_contains_select) {
          $entry_for_ip = &$entries[$ip];

          if (!isset($entry_for_ip)) {
            $entry_for_ip = 0;
          }
          $entry_for_ip++;
        }
      }

      // Filter the IPs with more than 10 query errors.
      if (!empty($entries)) {
        foreach ($entries as $ip => $count) {
          if ($count > 10) {
            $findings[] = $ip;
          }
        }
      }
    }

    if (!empty($findings)) {
      $result = CheckResult::FAIL;
    }

    $this->createResult($result, $findings);
  }

  /**
   * {@inheritdoc}
   */
  public function getDetails(array $findings, array $hushed = [], bool $returnString = FALSE): array|string {
    $output = $returnString ? '' : [];

    if (empty($findings)) {
      return $output;
    }

    $paragraphs = [];
    $paragraphs[] = $this->t('The following IPs were observed with an abundance of query errors.');

    if ($returnString) {
      $output .= implode("", $paragraphs);
      $output .= implode("", $findings);
    }
    else {
      $output[] = [
        '#theme' => 'check_evaluation',
        '#additional_paragraphs' => $paragraphs,
        '#finding_items' => $findings,
      ];
    }

    return $output;
  }

}
