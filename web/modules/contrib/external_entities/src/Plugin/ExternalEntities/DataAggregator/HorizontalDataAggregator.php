<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataAggregator;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Attribute\DataAggregator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities data aggregator by groups.
 *
 * @DataAggregator(
 *   id = "horizontal",
 *   label = @Translation("Horizontal data aggregator"),
 *   description = @Translation("Cumulates entities from multiple data sources.")
 * )
 */
#[DataAggregator(
  id: 'horizontal',
  label: new TranslatableMarkup('Horizontal data aggregator'),
  description: new TranslatableMarkup('Cumulates entities from multiple data sources.'),
)]
class HorizontalDataAggregator extends GroupAggregator {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) :array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['multi_info'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'This data aggregator cumulates entities from multiple data sources into a global set.'
      ),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildStorageClientAggregationForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
  ) :array {
    $client_key = end($parents);
    $aggr_selector = ($form['#attributes']['id'] ??= uniqid('aggr', TRUE));
    $aggr_config = $this->getStorageSettings($form, $form_state);
    // Data aggregation settings.
    $storage_client_form = [
      'groups' => [
        '#type' => 'textfield',
        '#title' => $this->t('Idientifier prefix'),
        '#description' => $this->t('This prefix must be unique and is required to match the source storage client to use. While the prefix will be visible from the Drupal side, it might be automatically removed when querying the storage source if it is set to "virtual" (see below). If you check the "virtual" options, you can use any prefix you want but it is recommended to use a prefix that is related to your storage client to easily identify what source is used according to its identifier.'),
        '#default_value' => implode(';', (array) ($aggr_config[$client_key]['aggr']['groups'] ?? [])),
        // We enforce the use of prefixes, even virtual, to make sure storage
        // client data will not be aggregated vertically.
        '#required' => TRUE,
        '#attributes' => [
          'id' => $aggr_selector . '_groups',
        ],
      ],
      'group_prefix_strip' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Virtual prefix'),
        '#description' => $this->t('"Virtual prefix" means the prefix is not present on the storage source side but only visible from the Drupal side.'),
        '#default_value' => $aggr_config[$client_key]['aggr']['group_prefix_strip'] ?? FALSE,
      ],
    ];

    // We simplify UI and let R/W management be at the global external entity
    // level.
    $storage_client_form['mode'] = [
      '#type' => 'hidden',
      '#default_value' => static::STORAGE_CLIENT_MODE_READWRITE,
    ];

    return $storage_client_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStorageClientAggregationForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
    array &$group_prefixes = [],
  ) {
    // We still allow multiple prefixes while it is not displayed for clarity.
    $aggr_settings = $form_state->getValue($parents, []);
    $aggr_settings['groups'] = array_filter(
      preg_split('/\s*;\s*/', trim($aggr_settings['groups'] ?? '')),
      'strlen'
    );
    $aggr_settings['id_mapper'] = ['id' => '', 'config' => []];
    $aggr_settings['merge'] = static::STORAGE_CLIENT_MERGE_OVERRIDE;
    $aggr_settings['merge_as_member'] = '';
    $aggr_settings['merge_join'] = '';
    $form_state->setValue($parents, $aggr_settings);

    // Check prefixes...
    $groups_form_element = NestedArray::getValue($form, [...$parents, 'groups']);
    // Prefixes must appear only once.
    foreach ($aggr_settings['groups'] as $prefix) {
      if (!empty($prefix) && array_key_exists($prefix, $group_prefixes)) {
        $form_state->setError(
          $groups_form_element,
          $this->t(
            'The prefix %prefix appears more than once. In horinzontal data aggregation, each storage client prefix must be unique.',
            [
              '%prefix' => $prefix,
            ]
          )
        );
      }
      $group_prefixes[$prefix] = $prefix;
    }

    // Make sure no prefix is part of another one.
    foreach ($aggr_settings['groups'] as $group_prefix1) {
      if ('' == $group_prefix1) {
        continue 1;
      }
      foreach ($group_prefixes as $group_prefix2) {
        if ('' == $group_prefix2) {
          continue 1;
        }
        if (($group_prefix1 != $group_prefix2)) {
          if (strncmp($group_prefix2, $group_prefix1, strlen($group_prefix1)) === 0) {
            $form_state->setError(
              $groups_form_element,
              $this->t(
                'In external entity storage client "Multiple storages", the prefix %prefix1 is also a prefix for prefix %prefix2. Prefixes must be strictly distinct.',
                [
                  '%prefix1' => $group_prefix1,
                  '%prefix2' => $group_prefix2,
                ]
              )
            );
          }
          elseif (strncmp($group_prefix1, $group_prefix2, strlen($group_prefix2)) === 0) {
            $form_state->setError(
              $groups_form_element,
              $this->t(
                'In external entity storage client "Multiple storages", the prefix %prefix1 is also a prefix for prefix %prefix2. Prefixes must be strictly distinct.',
                [
                  '%prefix1' => $group_prefix2,
                  '%prefix2' => $group_prefix1,
                ]
              )
            );
          }
        }
      }
    }
  }

}
