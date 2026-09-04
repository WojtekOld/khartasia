<?php

namespace Drupal\proc\Traits;

use Drupal\Component\Utility\Html;

/**
 * Provides a trait for retrieving metadata message.
 */
trait ProcCsvTrait {

  /**
   * Helper function for preprocessing CSV arguments.
   *
   * @param string $csv_data
   *   CSV data sent as URL argument.
   *
   * @return array|null
   *   An array containing the unique entries of the CSV, or NULL if input is
   *   not set.
   */
  public function getCsvArgument(string $csv_data): ?array {
    if ($csv_data === '') {
      return NULL;
    }

    // Remove query string from the path if any.
    $csv_data = preg_replace('/\?.+/', '', $csv_data);
    $csv_data = Html::escape($csv_data);
    $csv_values = str_getcsv($csv_data, ',', '"', '');
    $csv_data = [];

    foreach ($csv_values as $csv_value) {
      if (is_numeric($csv_value)) {
        $csv_value = (int) $csv_value;
        $csv_data[] = $csv_value;
      }
    }

    if (!empty($csv_data)) {
      return array_values(array_unique($csv_data));
    }

    return NULL;
  }

}
