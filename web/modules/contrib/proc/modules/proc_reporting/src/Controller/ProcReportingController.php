<?php

declare(strict_types=1);

namespace Drupal\proc_reporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\proc_reporting\Service\ProcReportingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Admin controller for proc_reporting views and the decrypt-tracking endpoint.
 */
class ProcReportingController extends ControllerBase {

  /**
   * ProcReportingController constructor.
   *
   * @param \Drupal\proc_reporting\Service\ProcReportingService $reportingService
   *   The proc reporting service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    protected ProcReportingService $reportingService,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('proc_reporting.service'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Render the Update-Tasks Monitoring report.
   *
   * Shows the most recent hourly snapshots ordered by snapshot time (desc) then
   * task count (desc). Only users who had at least one pending task at snapshot
   * time are listed. Admins can run cron manually to force a new snapshot.
   *
   * @return array
   *   Drupal render array.
   */
  public function updateTasksReport(): array {
    $rows = [];

    foreach ($this->reportingService->getUpdateTaskSnapshots(500) as $row) {
      $rows[] = [
        [
          'data' => $row->uid,
          'class' => ['proc-reporting-uid'],
        ],
        $row->user_name,
        [
          'data' => $row->update_task_count,
          'class' => ['proc-reporting-count'],
        ],
        $this->dateFormatter->format((int) $row->generated_at, 'medium'),
      ];
    }

    return [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t(
          'Hourly snapshots of pending re-encryption tasks per user. Only users with at least one pending task are shown. The cron job runs at most once per hour; run cron manually to force an immediate snapshot.'
        ),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('User ID'),
          $this->t('User Name'),
          $this->t('Pending Update Tasks'),
          $this->t('Snapshot Generated At'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t(
          'No snapshots yet. The cron job runs hourly and only records users with pending tasks.'
        ),
        '#attributes' => ['class' => ['proc-reporting-table']],
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

  /**
   * Render the Daily Operations report with date / user-ID filter.
   *
   * The filter is a plain HTML GET form so no Drupal form token overhead is
   * added to the URL when filters are applied.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return array
   *   Drupal render array.
   */
  public function dailyOperationsReport(Request $request): array {
    $raw_date = trim((string) $request->query->get('date', ''));
    $date_filter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date) ? $raw_date : '';
    $uid_filter = max(0, (int) $request->query->get('uid', 0));

    $build['filter'] = [
      '#theme' => 'proc_reporting_daily_operations_filter',
      '#label_date'   => $this->t('Date'),
      '#label_uid'    => $this->t('User ID'),
      '#label_filter' => $this->t('Filter'),
      '#label_reset'  => $this->t('Reset'),
      '#date_filter'  => $date_filter,
      '#uid_filter'   => $uid_filter > 0 ? $uid_filter : '',
      '#reset_url'    => Url::fromRoute('proc_reporting.daily_operations')->toString(),
    ];

    $rows = [];
    foreach ($this->reportingService->getDailyOperations(50, $date_filter, $uid_filter) as $row) {
      // Convert stored YYYY-MM-DD to DD/MM/YYYY for display.
      $timestamp = strtotime($row->operation_date);
      $display_date = $timestamp !== FALSE
          ? $this->dateFormatter->format($timestamp, 'custom', 'd/m/Y')
          : $row->operation_date;

      $rows[] = [
        $display_date,
        $row->uid,
        $row->user_name,
        $row->encrypt_count,
        $row->cipher_text_download_count,
        $row->reencrypt_count,
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Date'),
        $this->t('User ID'),
        $this->t('User Name'),
        $this->t('Encrypts'),
        $this->t('Cipher accesses/downloads'),
        $this->t('Re-encrypts'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No operations recorded yet.'),
      '#attributes' => ['class' => ['proc-reporting-table']],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
