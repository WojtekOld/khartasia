<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataAggregator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\external_entities\Attribute\DataAggregator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities data aggregator by groups.
 *
 * @DataAggregator(
 *   id = "vertical",
 *   label = @Translation("Vertical data aggregator"),
 *   description = @Translation("Merges content of entities from multiple data sources into corresponding entities of a master source.")
 * )
 */
#[DataAggregator(
  id: 'vertical',
  label: new TranslatableMarkup('Vertical data aggregator'),
  description: new TranslatableMarkup('Merges content of entities from multiple data sources into corresponding entities of a master source.'),
)]
class VerticalDataAggregator extends GroupAggregator {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) :array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['multi_info'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'This data aggregator merges content of entities from multiple data sources into corresponding entities of a master source.'
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
    $aggr_id = ($form['#attributes']['id'] ??= uniqid('aggr', TRUE));
    $aggr_selector = $aggr_id . '_sc_' . $client_key;
    $aggr_config = $this->getStorageSettings($form, $form_state);
    // Data aggregation settings.
    // No groups.
    $storage_client_form = [
      'groups' => [
        '#type' => 'hidden',
        '#default_value' => '',
      ],
      'group_prefix_strip' => [
        '#type' => 'hidden',
        '#default_value' => FALSE,
      ],
    ];

    // The use of override or other identifier field can not work with
    // the first storage client as no field has been loaded for it.
    if ($client_key != array_key_first($aggr_config)) {
      $storage_client_form['merge_join'] = [
        '#type' => 'textfield',
        '#title' => $this->t(
          'Previous storage clients field name that provides the join (identifier) value for this storage client (join field name)'
        ),
        '#description' => $this->t(
          'Leave empty to use the default identifier. Otherwise, any (text/numeric) field provided by previous storage clients can be used here as identifier value for this storage client as well as a JSONPath expression.'
        ),
        '#default_value' => $aggr_config[$client_key]['aggr']['merge_join'] ?? NULL,
      ];

      // Build id field mapper config.
      $storage_client_form['id_mapper'] = $this->buildClientPropertyMappingForm(
        $form,
        $form_state,
        [...$parents, 'aggr', 'id_mapper'],
        $aggr_selector . '_pm',
        $aggr_config[$client_key]['aggr']['id_mapper'] ?? [],
      );
      $storage_client_form['id_mapper']['#title'] =
        $this->t('Storage source field name to use as identifier');
      $storage_client_form['id_mapper']['#description'] =
        $this->t('This is used to identify each source entry (despite the join value). It must be set if the source field uses a different identifier field name than previous source or if a different join field name is used (see after). If no join field name is set, this field will be used as the join field.');
      // Join field property mapper.
      $storage_client_form['join_mapper'] = $this->buildClientPropertyMappingForm(
        $form,
        $form_state,
        [...$parents, 'aggr', 'join_mapper'],
        $aggr_selector . '_pmjn',
        $aggr_config[$client_key]['aggr']['join_mapper'] ?? [],
      );
      $storage_client_form['join_mapper']['#title'] =
        $this->t('Storage source field name to use to join data');
      $storage_client_form['join_mapper']['#description'] =
        $this->t('Source field name that will hold the value used to join data with the given (or default) previous storage identifier field.');
      $storage_client_form['join_mapper']['#states'] = [
        'invisible' => [
          ':input[data-mapper-id="' . $aggr_selector . '_pmid_id"]' => ['value' => ''],
        ],
      ];
      if (!empty(
          $form_state->getValue([...$parents, 'aggr', 'join_mapper', 'id'])
          ?? $aggr_config[$client_key]['aggr']['join_mapper']['id']
          ?? NULL
        )
      ) {
        $storage_client_form['join_mapper']['merge_non_mappable_join'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Join field value not returned in result set'),
          '#description' => $this->t(
            'While a join field can be used to filter, it may not be returned by the storage source in the returned entity. Checking this box enables a mapping between filter values and matching entities at the cost of a source storage query for each filter value. It is recommended for performance reasons to leave this checkbox unchecked unless the field holding the join value is not returned by the source storage client.'
          ),
          '#default_value' => $aggr_config[$client_key]['aggr']['join_mapper']['merge_non_mappable_join'] ?? '',
          '#return_value' => TRUE,
        ];
      }
      // Drupal-side filtering on join-field.
      $storage_client_form['drupal_filtering'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow Drupal-side filtering (for field join)'),
        '#default_value' => $aggr_config[$client_key]['aggr']['drupal_filtering'] ?? NULL,
        '#states' => [
          'invisible' => [
            ':input[data-mapper-id="' . $aggr_selector . '_pmjn_id"]' => ['value' => ''],
          ],
        ],
      ];

      $default_merge = $aggr_config[$client_key]['aggr']['merge'] ?? static::STORAGE_CLIENT_MERGE_KEEP;
      $storage_client_form['merge'] = [
        '#type' => 'select',
        '#title' => $this->t('How to merge client data'),
        '#options' => [
          static::STORAGE_CLIENT_MERGE_KEEP => $this->t('Keep existing field values (no override)'),
          static::STORAGE_CLIENT_MERGE_OVERRIDE => $this->t('Override previous field values'),
          static::STORAGE_CLIENT_MERGE_OVERRIDE_EMPTY => $this->t('Override previous field values if empty'),
          static::STORAGE_CLIENT_MERGE_SUBOBJECT => $this->t('As a sub-object'),
        ],
        '#description' => $this->t(
          'If set, entity field values provided by this storage client will override existing values with the same field name provided by previous storage clients (except for the identifier and join fields).'
        ),
        '#default_value' => $default_merge,
        '#attributes' => [
          'id' => $aggr_selector . '_merge',
        ],
      ];
      // Add 'translation' option if it is still in use.
      if ('translation' === $default_merge) {
        @trigger_error(
          'The merge method "As a translation" is deprecated in external_entities:3.0.0-beta6 and it will be removed in external_entities:3.0.0. Use external entity translation field mapping overrides instead. See https://www.drupal.org/project/external_entities/issues/3506455',
          E_USER_DEPRECATED
        );
        $storage_client_form['merge']['#options']['translation'] = $this->t('As a translation');
      }

      $storage_client_form['merge_as_member'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Field name to store sub-object(s) in an array'),
        '#description' => $this->t(
          'Machine name of the field that will hold the array of sub-objects keyed by their identifiers.'
        ),
        '#default_value' => $aggr_config[$client_key]['aggr']['merge_as_member'] ?? '',
        '#states' => [
          'visible' => [
            ':input[id="' . $aggr_selector . '_merge"]' => ['value' => static::STORAGE_CLIENT_MERGE_SUBOBJECT],
          ],
        ],
      ];
      $storage_client_form['merge_as_member_translation'] = [
        '#type' => 'language_select',
        '#languages' => Language::STATE_ALL,
        '#title' => $this->t('Language'),
        '#description' => $this->t(
          'Select the language this source is used for. The data from the first source are used as values for the sites default language!'
        ),
        '#default_value' => str_replace('__external_entity_translation__', '', $aggr_config[$client_key]['aggr']['merge_as_member'] ?? ''),
        '#states' => [
          'visible' => [
            ':input[id="' . $aggr_selector . '_merge"]' => ['value' => 'translation'],
          ],
        ],
      ];
    }
    else {
      // First storage client.
      $storage_client_form['first_client'] = [
        '#type' => 'item',
        '#markup' => $this->t('No aggregation settings for the first storage client.'),
      ];
      $storage_client_form['id_mapper'] = [
        '#type' => 'container',
        'id' => [
          '#type' => 'hidden',
          '#default_value' => '',
        ],
      ];
    }

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
    $aggr_settings = $form_state->getValue($parents, []);
    $aggr_settings['groups'] = [];
    $aggr_settings['group_prefix_strip'] = FALSE;

    // If translation aggregation is enabled create a compound merge_as_member
    // value.
    if (($aggr_settings['merge'] ?? '') == 'translation') {
      $aggr_settings['merge_as_member'] =
        '__external_entity_translation__'
        . $aggr_settings['merge_as_member_translation']
        ?? '';
    }

    $form_state->setValue($parents, $aggr_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $aggr_config = $this->getConfiguration()['storage_clients'] ?? [];
    $first_key = array_key_first($aggr_config);
    $form_state->unsetValue(
      ['storage_clients', $first_key, 'aggr', 'first_client']
    );
    parent::submitConfigurationForm($form, $form_state);
  }

}
