<?php

namespace Drupal\bibcite_ris\Encoder;

use LibRIS\RISReader;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * RIS format encoder.
 */
class RISEncoder implements EncoderInterface, DecoderInterface {

  /**
   * The format that this encoder supports.
   *
   * @var string
   */
  protected static $format = 'ris';

  /**
   * The field mapping.
   *
   * Keys are RIS-defined keys, like "AU" or "DA". Values are NULL, empty
   * string, or bibcite_entity field names, like "author" or "bibcite_date".
   *
   * @var array
   */
  protected array $fields;

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format): bool {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   */
  public function decode($data, $format, array $context = []): mixed {
    /*
     * Workaround for weird behavior of "LibRIS" library.
     *
     * Replace LF line ends by CRLF.
     */
    $data = str_replace("\n", "\r\n", $data);

    $config = \Drupal::config('bibcite_entity.mapping.' . $format);
    $this->fields = $config->get('fields');
    $ris = new RISReader();
    $ris->parseString($data);
    $records = $ris->getRecords();

    // Workaround for weird behavior of "LibRIS" library.
    foreach ($records as &$record) {
      foreach ($record as $key => $value) {
        if (is_array($value) && count($value) == 1) {
          $record[$key] = reset($value);
        }
      }
      // Additional pages parsing.
      $pages_string = '';
      if (array_key_exists('SP', $record) || array_key_exists('EP', $record)) {
        if (array_key_exists('SP', $record)) {
          $record['SP'] = (array) $record['SP'];
        }
        if (array_key_exists('EP', $record)) {
          $record['EP'] = (array) $record['EP'];
        }
        $max_sp = array_key_exists('SP', $record) ? max($record['SP']) : NULL;
        $max_ep = array_key_exists('EP', $record) ? max($record['EP']) : 0;
        if ($max_sp && $max_sp > $max_ep) {
          $pages_string .= $max_sp . '+';
          array_splice($record['SP'], array_search($max_sp, $record['SP']), 1);
        }
        while ($max_ep) {
          $pages_string = $max_ep . ', ' . $pages_string;
          array_splice($record['EP'], array_search($max_ep, $record['EP']), 1);
          $max_ep = count($record['EP']) > 0 ? max($record['EP']) : NULL;
          if ($record['SP'] != NULL) {
              $max_sp = array_key_exists('SP', $record) ? max($record['SP']) : NULL;
          }
          else {
              $max_sp = NULL;
          }
          if ($max_sp && (!$max_ep || $max_sp > $max_ep)) {
            $pages_string = $max_sp . '-' . $pages_string;
            array_splice($record['SP'], array_search($max_sp, $record['SP']), 1);
          }
        }
        $record['SP'] = $pages_string;
        $record['EP'] = $pages_string;
      }

      // Publication date: bibcite_entity expects mm/yyyy format.
      if (isset($record['DA'])) {
        $record['DA'] = date('m/Y', \strtotime($record['DA']));
      }

      // Some keys have aliases, or in RIS parlance, "synonyms". See
      // https://en.wikipedia.org/wiki/RIS_(file_format)#Example_multi-record_format
      // Handling follows "prefer last match" logic,
      // so a record with both B1 and TI, for example, will use TI.
      $equivalent_keys = [
        // User fields to custom.
        'bibcite_custom1' => ['U1', 'C1'],
        'bibcite_custom2' => ['U2', 'C2'],
        'bibcite_custom3' => ['U3', 'C3'],
        'bibcite_custom4' => ['U4', 'C4'],
        'bibcite_custom5' => ['U5', 'C5'],
        // Issue.
        'bibcite_issue' => ['CP', 'IS'],
        // Secondary notes or abstract.
        'bibcite_abst_e' => ['AB', 'N2'],
        // Year of publication.
        'bibcite_year' => ['Y1', 'PY'],
        // Titles.
        'title' => ['BT', 'CT', 'T1', 'TI'],
        // Short title.
        'bibcite_short_title' => ['J1', 'J2', 'JO', 'ST'],
        // Secondary title.
        'bibcite_secondary_title' => ['JA', 'JF', 'T2'],
      ];
      foreach ($equivalent_keys as $field_name => $aliases) {
        $this->handleAliases($record, $field_name, $aliases);
      }
      // Aliases for contributors. All matches are added as separate records.
      $equivalent_contributors = [
        'author' => ['A1', 'AU'],
        'editor' => ['A2', 'ED'],
      ];
      foreach ($equivalent_contributors as $field_name => $aliases) {
        $this->handleAliases($record, $field_name, $aliases, TRUE);
      }
    }

    if (count($records) === 0) {
      $format_definition = \Drupal::service('plugin.manager.bibcite_format')->getDefinition($format);
      $format_label = $format_definition['label'];
      throw new UnexpectedValueException("Incorrect '{$format_label}' format or empty set.");
    }

    return $records;
  }

  /**
   * Handle groups of equivalent RIS keys.
   *
   * Set $record[$target] where $target is one of the alias keys and unset all
   * the others.
   *
   * @param array $record
   *   An array of values, keyed by RIS keys, passed by reference.
   * @param string $field_name
   *   The name of a bibcite_entity field.
   * @param string[] $aliases
   *   An indexed array of equivalent RIS keys, all mapping to $field_name.
   * @param bool $merge
   *   Optional, defaults to FALSE. If TRUE, then merge the values of equivalent
   *   keys. Use this option for multiple-valued keys such as 'AU'. If FALSE,
   *   then use the last alias that maps to $field_name in $this->fields and has
   *   a non-empty value.
   */
  protected function handleAliases(array &$record, string $field_name, array $aliases, bool $merge = FALSE): void {
    // An array with the aliases as keys.
    $filter = array_fill_keys($aliases, '');
    // Find the last alias that maps to $field_name. This is the one that will
    // be imported.
    $candidates = array_filter(
      $this->fields,
      fn(?string $field): bool => ($field ?? '') === $field_name,
    );
    $target = array_key_last(array_intersect_key($candidates, $filter));
    if ($target === NULL) {
      switch ($field_name) {
        // Author and Editor are special, since they are set even if no key maps
        // to them. These are contributor keys.
        case 'author':
          $target = 'AU';
          break;

        case 'editor':
          $target = 'ED';
          break;

        default:
          return;
      }
    }

    if ($merge) {
      // Some values may be NULL or scalar, so cast to array.
      $values = array_values(array_map(
        fn(mixed $value): array => (array) $value,
        array_intersect_key($record, $filter),
      ));
      $merged_values = array_unique(array_merge(...$values));
      if (count($merged_values) > 0) {
        $record[$target] = count($merged_values) === 1
          ? current($merged_values)
          : $merged_values;
      }
    }
    else {
      $values = array_filter(array_replace(
        $filter,
        array_intersect_key($record, $filter),
      ));
      if ($values) {
        $record[$target] = array_pop($values);
      }
    }

    foreach (array_diff($aliases, [$target]) as $key) {
      unset($record[$key]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding(string $format): bool {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   */
  public function encode(mixed $data, string $format, array $context = []): string {
    if (isset($data['TY'])) {
      $data = [$data];
    }

    $data = array_map(function ($raw) {
      return $this->buildEntry($raw);
    }, $data);

    return implode("\n", $data);
  }

  /**
   * Build RIS entry string.
   *
   * @param array $data
   *   Array of RIS values.
   *
   * @return string
   *   Formatted RIS string.
   */
  protected function buildEntry(array $data) {
    $entry = NULL;
    // For not duplicating pages parse.
    $pages_parsed = FALSE;
    foreach ($data as $key => $value) {
      switch ($key) {
        // Pages found.
        case "SP":
        case "EP":
          if (!$pages_parsed) {
            $pages_parsed = TRUE;
            $exp = explode(',', trim($value));
            foreach ($exp as $page) {
              // Interval of pages.
              if (strpos(trim($page), '-') !== FALSE) {
                $interval = explode('-', trim($page));
                $entry .= $this->buildLine('SP', $interval[0]);
                $entry .= $this->buildLine('EP', $interval[count(@$interval) - 1]);
              } else {
                // From here to the end.
                if ($page[strlen(trim($page)) - 1] === '+') {
                  $entry .= $this->buildLine('SP', trim($page, ' +'));
                } else {
                  // Single page.
                  $entry .= $this->buildLine('EP', trim($page));
                }
              }
            }
          }
          break;

        // Not pages.
        default:
          if (is_array($value)) {
            $entry .= $this->buildMultiLine($key, $value);
          } else {
            $entry .= $this->buildLine($key, $value);
          }
          break;
      }
    }

    $entry .= $this->buildEnd();

    return $entry;
  }

  /**
   * Build multi line entry.
   *
   * @param string $key
   *   Line key.
   * @param array $value
   *   Array of multi line values.
   *
   * @return string
   *   Multi line entry.
   */
  protected function buildMultiLine($key, array $value) {
    $lines = '';

    foreach ($value as $item) {
      $lines .= $this->buildLine($key, $item);
    }

    return $lines;
  }

  /**
   * Build entry line.
   *
   * @param string $key
   *   Line key.
   * @param string $value
   *   Line value.
   *
   * @return string
   *   Entry line.
   */
  protected function buildLine($key, $value) {
    return $key . ' - ' . $value . "\n";
  }

  /**
   * Build the end of RIS entry.
   *
   * @return string
   *   End line for the RIS entry.
   */
  protected function buildEnd() {
    return $this->buildLine('ER', '');
  }

}
