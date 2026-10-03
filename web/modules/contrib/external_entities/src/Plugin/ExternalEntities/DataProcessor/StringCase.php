<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles string alterations.
 *
 * @DataProcessor(
 *   id = "stringcase",
 *   label = @Translation("String alterations"),
 *   description = @Translation("Change character case, special characters, prefix and suffix.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'stringcase',
  label: new TranslatableMarkup('String alterations'),
  description: new TranslatableMarkup('Change character case, special characters, prefix and suffix.'),
)]
class StringCase extends DataProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'format' => '',
      'non_word' => '',
      'encode' => '',
      'prefix' => '',
      'suffix' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['non_word'] = [
      '#type' => 'radios',
      '#title' => $this->t('Non-word character handling'),
      '#options' => [
        '' => $this->t('Unchanged'),
        'remove' => $this->t('Remove'),
        'underscore' => $this->t('Replace by underscores'),
        'escaped' => $this->t('Escaped on source using slashes'),
        'to_escape' => $this->t('To escape using slashes (not escaped on source)'),
        // We may add 'to_shell_escape' option here if needed one day. It is
        // already implemented in the code after, but it would add a confusing
        // new option.
        'url_encoded' => $this->t('URL encoded on source'),
        'to_url_encode' => $this->t('To URL encode (not encoded on source)'),
        'with_html_entities' => $this->t('Source contains HTML entities'),
        'to_html_entities' => $this->t('Encode special characters using HTML entities'),
      ],
      '#default_value' => $config['non_word'] ?? '',
    ];

    $form['format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Case altering'),
      '#options' => [
        '' => $this->t('Unchanged'),
        'upper' => $this->t('Full upper-case'),
        'lower' => $this->t('Full lower-case'),
        'title' => $this->t('Full title-case conversion (upper-case first letter of all words)'),
        'lower_camel_case' => $this->t('To lower camelCase'),
        'upper_camel_case' => $this->t('To upper CamelCase'),
        'snake_case' => $this->t('To snake_case'),
      ],
      '#default_value' => $config['format'] ?? '',
    ];

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $config['prefix'] ?? '',
    ];

    $form['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $config['suffix'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processData(
    array $raw_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array {
    $config = $this->getConfiguration();
    $data = [];
    foreach ($raw_data as $entry) {
      if (!isset($entry)) {
        $data[] = NULL;
        continue;
      }

      // Check non-word character handling.
      if (!empty($config['non_word'])) {
        switch ($config['non_word'] ?? '') {
          case 'remove':
            $entry = preg_replace('#[\W_]+#', '', $entry);
            break;

          case 'underscore':
            $entry = trim(preg_replace('#[\W_]+#', '_', $entry), '_');
            break;

          case 'escaped':
            // $entry = stripcslashes($entry);
            $entry = stripcslashes($entry);
            break;

          case 'to_escape':
            $entry = addcslashes($entry, "\0..\37\\\177..\377\\");
            break;

          case 'to_shell_escape':
            $entry = escapeshellcmd($entry);
            break;

          case 'url_encoded':
            $entry = rawurldecode($entry);
            break;

          case 'to_url_encode':
            $entry = rawurlencode($entry);
            break;

          case 'with_html_entities':
            $entry = html_entity_decode($entry);
            break;

          case 'to_html_entities':
            $entry = htmlentities($entry);
            break;

          default:
            break;
        }
      }

      switch ($config['format'] ?? '') {
        case 'upper':
          $entry = mb_convert_case($entry, MB_CASE_UPPER);
          break;

        case 'lower':
          $entry = mb_convert_case($entry, MB_CASE_LOWER);
          break;

        case 'title':
          $entry = mb_convert_case($entry, MB_CASE_TITLE);
          break;

        case 'lower_camel_case':
          $entry = preg_replace(
            '#[\W_]+#',
            '',
            mb_convert_case($entry, MB_CASE_TITLE)
          );
          if (!empty($entry)) {
            $entry[0] = mb_convert_case($entry[0], MB_CASE_LOWER);
          }
          break;

        case 'upper_camel_case':
          $entry = preg_replace(
            '#[\W_]+#',
            '',
            mb_convert_case($entry, MB_CASE_TITLE)
          );
          break;

        case 'snake_case':
          $entry = preg_replace(
            '#\W+#',
            '_',
            $entry
          );
          $entry = strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1_$2', $entry)
          );
          $entry = trim(preg_replace('/_+/', '_', $entry), '_');
          break;

        default:
          break;
      }

      $data[] = ($config['prefix'] ?? '') . $entry . ($config['suffix'] ?? '');
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseDataProcessing(
    array $data,
    array $original_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array|null {
    $config = $this->getConfiguration();
    if ((!empty($config['non_word'])
          && in_array($config['non_word'], ['remove', 'underscore']))
        || !empty($config['format'])) {
      return NULL;
    }

    $reversed = [];
    foreach ($data as $entry) {
      if (!empty($config['prefix'])
        && str_starts_with($entry, $config['prefix'])
      ) {
        $entry = substr($entry, strlen($config['prefix']));
      }
      if (!empty($config['suffix'])
        && str_ends_with($entry, $config['suffix'])
      ) {
        $entry = substr($entry, 0, -strlen($config['suffix']));
      }

      if (!empty($config['non_word'])) {
        switch ($config['non_word']) {
          case 'escaped':
            $entry = addcslashes($entry, "\0..\37\\\177..\377\\");
            break;

          case 'to_escape':
          case 'to_shell_escape':
            $entry = stripcslashes($entry);
            break;

          case 'url_encoded':
            $entry = rawurlencode($entry);
            break;

          case 'to_url_encode':
            $entry = rawurldecode($entry);
            break;

          case 'with_html_entities':
            $entry = htmlentities($entry);
            break;

          case 'to_html_entities':
            $entry = html_entity_decode($entry);
            break;

          case 'remove':
          case 'underscore':
          default:
            return NULL;
        }
      }
      $reversed[] = $entry;
    }

    return $reversed;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    $config = $this->getConfiguration();
    $reversible_non_word = empty($config['non_word'])
      || !in_array($config['non_word'], ['remove', 'underscore']);
    return $reversible_non_word && empty($config['format']);
  }

}
