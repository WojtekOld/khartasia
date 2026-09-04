<?php

declare(strict_types=1);

namespace Drupal\security_review\Plugin\SecurityCheck;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\security_review\Attribute\SecurityCheck;
use Drupal\security_review\CheckResult;
use Drupal\security_review\SecurityCheckBase;

/**
 * Checks the last time cron has run.
 */
#[SecurityCheck(
  id: 'last_cron_run',
  title: new TranslatableMarkup('Last Cron Run'),
  description: new TranslatableMarkup('Checks the last time cron has run.'),
  namespace: new TranslatableMarkup('Security Review'),
  success_message: new TranslatableMarkup('Cron has ran within the last 3 days.'),
  failure_message: new TranslatableMarkup('Cron has not ran within the last 3 days.'),
  help: [
    new TranslatableMarkup('A properly configured cron job executes, initiates, or manages a variety of tasks.'),
  ]
)]
class LastCronRun extends SecurityCheckBase {

  /**
   * {@inheritdoc}
   */
  public function doRun(bool $cli = FALSE): void {
    $result = CheckResult::SUCCESS;
    $last_run = TRUE;
    $cron_last = $this->state->get('system.cron_last');
    if ($cron_last <= strtotime('-3 day')) {
      $result = CheckResult::FAIL;
      $last_run = FALSE;
    }

    $this->createResult($result, ['last_run' => $last_run]);
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
    if (isset($findings['last_run']) && !$findings['last_run']) {
      $paragraphs[] = $this->t("Cron has not ran in over 3 days.");
    }

    if ($returnString) {
      $output .= implode("", $paragraphs);
    }
    else {
      $output[] = [
        '#theme' => 'check_evaluation',
        '#finding_items' => $paragraphs,
      ];
    }

    return $output;
  }

}
