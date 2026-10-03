<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Attribute\StorageClient;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities storage client based on a JSON:API.
 *
 * @StorageClient(
 *   id = "jsonapi",
 *   label = @Translation("JSON:API"),
 *   description = @Translation("Retrieves external entities from a (Drupal) JSON:API source.")
 * )
 */
#[StorageClient(
  id: 'jsonapi',
  label: new TranslatableMarkup('JSON:API'),
  description: new TranslatableMarkup('Retrieves external entities from a (Drupal) JSON:API source.'),
)]
class JsonApi extends RestClient {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Adjust default REST form.
    $form_override = [
      'endpoint' => [
        '#title' => $this->t('JSON:API URL'),
        '#description' => $this->t(
          'Should be something similar to "<code>http://www.server.org/jsonapi/content_type/bundle_name/</code>". Ex.: <code>http://www.server.org/jsonapi/node/page/</code>.'
        ),
        '#attributes' => [
          'placeholder' => $this->t("ex.: http://www.someserver.net/jsonapi/node/page/"),
        ],
      ],
      'endpoint_options' => [
        'cache' => [
          '#type' => 'hidden',
          '#default_value' => 1,
        ],
        'single' => [
          '#type' => 'hidden',
          '#default_value' => $this->configuration['endpoint'] ?? '',
        ],
        'count' => [
          '#type' => 'textfield',
          '#title' => $this->t('Estimated total number of entities'),
          '#required' => FALSE,
          '#description' => $this->t('Since the default JSON:API does not provide a mean to get the total number of available entities, you should provide a static value here that correspond to the estimated total number of available entities. This number could be higher than reality as it will only be used for paging computation.'),
          '#default_value' => $this->configuration['endpoint_options']['count'] ?? '',
        ],
        'count_mode' => [
          '#type' => 'hidden',
          '#default_value' => 'entities',
        ],
        'limit_qcount' => [
          '#title' => $this->t('Maximum number of query per second'),
          '#description' => $this->t('JSON:API does not handle query rate but it is still possible to limit query rate from this client side.'),
        ],
        'limit_qtime' => [
          '#type' => 'hidden',
          '#default_value' => '1',
        ],
      ],
      'response_format' => [
        '#type' => 'hidden',
        '#default_value' => 'xnttjson',
      ],
      'data_path' => [
        'list' => [
          '#type' => 'hidden',
          '#default_value' => '$.data.*',
        ],
        'single' => [
          '#type' => 'hidden',
          '#default_value' => '$.data.0',
        ],
        'keyed_by_id' => [
          '#type' => 'hidden',
          '#default_value' => FALSE,
        ],
        'count' => [
          '#description' => $this->t('This JSONPath will be used against the result. Only viable if the JSON:API has been extended. E.g. in Drupal by the <a href="https://www.drupal.org/project/jsonapi_extras">JSON:API Extras</a> module'),
          '#attributes' => [
            'placeholder' => $this->t('ex.: $.meta.count'),
          ],
        ],
      ],
      'pager' => [
        '#type' => NULL,
        // Drupal uses a 50 elements per page basis so we go for 1 query per
        // page. 50 is the JSON:API default limit anyway.
        'default_limit' => [
          '#type' => 'hidden',
          '#default_value' => static::DEFAULT_PAGE_LENGTH,
        ],
        // Setting 'type' will be added here later.
        'page_parameter' => [
          '#type' => 'hidden',
          '#default_value' => 'page[offset]',
        ],
        'page_parameter_type' => [
          '#type' => 'hidden',
          '#default_value' => 'startitem',
        ],
        'page_size_parameter' => [
          '#type' => 'hidden',
          '#default_value' => 'page[limit]',
        ],
        'page_size_parameter_type' => [
          '#type' => 'hidden',
          '#default_value' => 'pagesize',
        ],
        'page_start_one' => [
          '#type' => 'hidden',
          '#default_value' => FALSE,
        ],
        'always_query' => [
          '#type' => 'hidden',
          '#default_value' => FALSE,
        ],
      ],
      'api_key' => [
        '#type' => NULL,
        'type' => [
          '#type' => 'hidden',
          '#default_value' => 'none',
        ],
        'header_name' => [
          '#type' => 'hidden',
          '#default_value' => '',
        ],
        'key' => [
          '#type' => 'hidden',
          '#default_value' => '',
        ],
      ],
      'http' => [
        '#type' => NULL,
        'headers' => [
          '#type' => 'hidden',
          '#default_value' => 'Accept: application/vnd.api+json',
        ],
      ],
      'parameters' => [
        '#description' => $this->t('You may specify here additional URL query parameters that will be used to filter entities or include extra data for instance. You can find available parameters in the <a href="https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module">JSON:API documentation</a>.'),
        'list' => [
          '#type' => 'hidden',
          '#default_value' => $this->getParametersFormDefaultValue('list'),
        ],
        'list_param_mode' => [
          '#type' => 'hidden',
          '#default_value' => 'query',
        ],
        'single' => [
          '#title' => $this->t('Additional parameters'),
          '#attributes' => [
            'placeholder' => $this->t("ex.:\nfilter[field_name]|value\nfilter[field_other]|value\ninclude=field_comments.uid"),
          ],
        ],
        'single_param_mode' => [
          '#type' => 'hidden',
          '#default_value' => 'query',
        ],
      ],
      'filtering' => [
        '#type' => NULL,
        'drupal' => [
          '#type' => 'hidden',
          '#default_value' => FALSE,
        ],
        'basic' => [
          '#type' => 'hidden',
          '#default_value' => FALSE,
        ],
        'basic_fields' => [
          '#type' => 'hidden',
          '#default_value' => '',
        ],
        'list_support' => [
          '#type' => 'hidden',
          '#default_value' => 'none',
        ],
        'list_join' => [
          '#type' => 'hidden',
          '#default_value' => '',
        ],
      ],
    ];

    // Merge forms.
    $form = $this->overrideForm(
      $form,
      $form_override,
      [
        '#type' => 'hidden',
      ]
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Report list endpoint to single entity endpoint.
    $endpoint = $form_state->getValue('endpoint');
    $form_state->setValue(['endpoint_options', 'single'], $endpoint);
    $parameters = $form_state->getValue('parameters');
    if (preg_match('#(?:^|\n)include(?:\||=)([^\n]+)#', $parameters['single'])) {
      // We have "include". Remove any from the "list" and disable static cache.
      $parameters['list'] = preg_replace(
        '#(?<=^|\n)(?:include)(?:\||=)[^\n]*(?:\n|$)#',
        '',
        $parameters['single']
      );
      $form_state->setValue(['endpoint_options', 'cache'], FALSE);
    }
    else {
      // Same parameters for both single and list if no "include".
      $parameters['list'] = $parameters['single'];
    }
    // Remove any "{id}" parameter from list.
    $parameters['list'] = preg_replace(
      '#(?<=^|\n)([^|=]+)(?:\||=)[^\n]*\{id\}[^\n]*(?:\n|$)#',
      '',
      $parameters['list']
    );
    $form_state->setValue('parameters', $parameters);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @see https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module/filtering
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ) :array {
    if (1 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "JsonApi::transliterateDrupalFilters():\n@parameters",
        [
          '@parameters' => print_r($parameters, TRUE),
        ]
      );
    }
    $trans_filters = [];
    // Use a do-while structure trick to break and avoid many "if".
    do {
      // Skip empty filters.
      if (empty($parameters)) {
        $trans_filters = ['source' => [], 'drupal' => []];
        break;
      }

      // If no external entity is available, no way to map fields.
      if (empty($this->externalEntityType)) {
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug("No external entity type available to map fields and Drupal-side filtering allowed.");
        }
        break;
      }

      $source_filters = [];
      $drupal_filters = [];
      foreach ($parameters as $parameter) {
        if (!isset($parameter['field']) || !isset($parameter['value'])) {
          $drupal_filters[] = $parameter;
          continue;
        }
        $field_mapper = $this->externalEntityType->getFieldMapper($parameter['field']);
        if (empty($field_mapper)) {
          $drupal_filters[] = $parameter;
          continue;
        }
        $source_field = $field_mapper->getMappedSourceFieldName();

        if (!isset($source_field)) {
          $drupal_filters[] = $parameter;
          continue;
        }

        // We are probably dealing with a filter condition.
        // The structure the filter needs to be the following:
        // @example ?filter[recent][condition][path]=changed&filter[recent][condition][operator]=>&filter[recent][condition][value]=1712670000.
        $source_field = str_replace('attributes.', '', $source_field);
        $randomizer = new Random();
        $random_name = $randomizer->name();
        $source_filters[] = [
          'field' => 'filter[' . $random_name . '][condition][path]',
          'value' => $source_field,
          'operator' => '=',
        ];
        $source_filters[] = [
          'field' => 'filter[' . $random_name . '][condition][operator]',
          'value' => $parameter['operator'] ?? '=',
          'operator' => '=',
        ];
        $source_filters[] = [
          'field' => 'filter[' . $random_name . '][condition][value]',
          'value' => $parameter['value'],
          'operator' => '=',
        ];
      }
      // Drupal filtering support removed here because it would fetch all
      // records.
      $drupal_filters = [];

      if (1 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "transliteration done. Returning:\n@filters",
          [
            '@filters' => print_r(
              ['source' => $source_filters, 'drupal' => $drupal_filters],
              TRUE
            ),
          ]
        );
      }
      $trans_filters = [
        'source' => $source_filters,
        'drupal' => $drupal_filters,
      ];
    } while (FALSE);

    return $this->transliterateDrupalFiltersAlter(
      $trans_filters,
      $parameters,
      $context
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load(string|int $id) :array|null {
    // Check static cache.
    if (!empty(static::$cachedData[$this->configuration['endpoint']][$id])) {
      return static::$cachedData[$this->configuration['endpoint']][$id];
    }

    $result = parent::load($id);

    static::$cachedData[$this->configuration['endpoint']][$id] = $result;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSingleQueryParameters(int|string|null $id): array {
    $query_parameters = parent::getSingleQueryParameters($id);
    if ($id !== NULL && !in_array($id, $query_parameters, TRUE)) {
      // Add missing id filter.
      $id_field = str_replace('attributes.', '', $this->getSourceIdFieldName() ?? 'id');
      $query_parameters["filter[$id_field]"] = $id;
    }
    return $query_parameters;
  }

}
