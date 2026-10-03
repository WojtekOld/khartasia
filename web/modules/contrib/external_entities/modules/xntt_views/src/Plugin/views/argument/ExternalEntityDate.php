<?php

namespace Drupal\xntt_views\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\Date;

/**
 * Date argument handler for external entity timestamp fields.
 *
 * The core "date" argument extends Formula and builds an SQL date-format
 * expression, which cannot be mapped to an external entity query condition.
 * This handler keeps all the UI features of the core date argument (current
 * date default argument, title formatting, ...) but filters with a plain
 * timestamp range covering the given day instead of an SQL formula.
 *
 * @see https://www.drupal.org/project/external_entities/issues/3590257
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("xntt_date")
 */
#[ViewsArgument('xntt_date')]
class ExternalEntityDate extends Date {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    try {
      // Interpret the argument as a day in the current timezone.
      $start = new \DateTime($this->argument, new \DateTimeZone(date_default_timezone_get()));
    }
    catch (\Exception $e) {
      // Invalid date argument: fail closed (no result) like the SQL date
      // formula would, instead of silently ignoring the filter.
      $this->query->addWhere(0, $this->realField, PHP_INT_MAX, '>');
      return;
    }
    $end = (clone $start)->modify('+1 day');
    $this->query->addWhere(0, $this->realField, $start->getTimestamp(), '>=');
    $this->query->addWhere(0, $this->realField, $end->getTimestamp(), '<');
  }

}
