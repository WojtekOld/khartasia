<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Abstract class for external entities storage client using query languages.
 */
abstract class QueryLanguageClientBase extends StorageClientBase implements QueryLanguageClientInterface {

  use PluginFormTrait;

  /**
   * Placeholder data.
   *
   * Keys are qualified placeholder names and values are replacement values.
   *
   * @var array
   */
  protected $placeholders;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Name of the query language.
   *
   * @var string
   */
  protected $qlName = 'QL';

  /**
   * Constructs a query language external storage object.
   *
   * This default constructor calls self::initConnection() to initialize
   * any connection things.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The config factory service.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token_service,
    MessengerInterface $messenger,
    EventDispatcherInterface $event_dispatcher,
  ) {
    // Services injection.
    $this->messenger = $messenger;
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger,
      $entity_type_manager,
      $entity_field_manager,
      $token_service,
      $event_dispatcher,
    );
    // Initialize connection.
    $this->initConnection($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory')->get('external_entities'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      // Queries can be either arrays of strings or complex query arrays.
      'queries' => [
        'create' => [],
        'read'   => [],
        'update' => [],
        'delete' => [],
        'list'   => [],
        'count'  => [],
      ],
      // Conection settings (parameters, credentials, etc.).
      'connection' => [],
      // The "placeholders" structure is an array of:
      // @code
      // [
      //   // The 'placeholder' name must be prefixed by a colon ":", start with
      //   // a letter, only contain alpha-numeric characters and underscores,
      //   // and if it stands for multiple values, it must be suffixed with
      //   // square brakets "[]".
      //   'placeholder' => <string placeholder name>,
      //   // Either 'query' OR 'constant' key but NOT BOTH.
      //   'query' => <a query string used to return corresponding value(s)>,
      //   'constant' => <a constant value>,
      // ]
      // @code
      //
      // Placeholders values are fetched during connection initialization.
      // Placeholders can be used in queries and will be replaced by their
      // respective values when queries are run. This avoids too complex queries
      // and simplifies query maintenance.
      'placeholders' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    // Update connection.
    $this->initConnection($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    // Make sure we got a correct base form.
    $form['#type'] ??= 'container';
    $form['#attributes']['id'] ??= uniqid('sc', TRUE);

    // Connection settings.
    $form = $this->buildConnectionSettingForm($form, $form_state);

    // Interface for placeholder queries.
    $form = $this->buildPlaceholdersForm($form, $form_state);

    // CRUD, list and count query form.
    $form = $this->buildCrudlcForm($form, $form_state);

    return $form;
  }

  /**
   * Build connection setting form.
   *
   * The open status of the connection section can be controlled through the
   * form state 'connection_open'.
   *
   * @param array $form
   *   An associative array containing the initial structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The connection setting form.
   */
  public function buildConnectionSettingForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $connection_open = $form_state->get('connection_open') ?? TRUE;
    $connection_html_id =
      ($form['#attributes']['id'] ??= uniqid('sc', TRUE))
      . 'cnx';
    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection settings'),
      '#open' => $connection_open,
      '#weight' => 10,
      '#attributes' => ['id' => $connection_html_id],
    ];
    return $form;
  }

  /**
   * Build placeholder settings form.
   *
   * The open status of the placeholder section can be controlled through the
   * form state 'placeholders_open'.
   * The form state 'placeholder_count_<$form['#attributes']['id']>_ph'
   * handles the number of placehodlers in the form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The placeholders form.
   */
  public function buildPlaceholdersForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $ph_id =
      ($form['#attributes']['id'] ??= uniqid('sc', TRUE))
      . '_ph';
    $placeholders_open = $form_state->get('placeholders_open') ?? FALSE;

    $form['placeholder_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Placeholders'),
      '#open' => $placeholders_open,
      '#attributes' => ['id' => $ph_id],
      '#weight' => 20,
      'description' => [
        '#type' => 'item',
        '#markup' =>
        '<div>'
        . $this->t(
          'You can define placeholders that will be replaced by cached values in
          your queries. Thoses values are fetched using the provided query
          when the external entity type is saved or when the cache needs to be
          updated. Therefore, you can use those placeholders in your queries below
          and avoid running subqueries or additional joins in your original query
          in order to get a given value that relies on static data.'
        )
        . '</div><div>'
        . $this->t('For each placeholder, you must specify the name of the placeholder to use in queries (including leading ":"). It must start with ":" followed by a letter, only contain alphanumeric characters and underscores and must only end by the suffix "[]" if the returned data is a list of values. You can leave it empty to remove a placeholder setting.')
        . '</div><div>'
        . $this->t('You must also specify a query to use to fetch the value(s). The query must return only one value unless the placeholder name ends by "[]" (eg.: ":multi_ids[]").')
        . '</div>',
      ],
    ];
    $placeholder_count = $form_state->get('placeholder_count_' . $ph_id);
    if (empty($placeholder_count)) {
      $placeholder_count = count($this->configuration['placeholders']) + 1;
    }
    $form_state->set('placeholder_count_' . $ph_id, $placeholder_count);

    for ($i = 0; $i < $placeholder_count; ++$i) {
      $value = $this->configuration['placeholders'][$i] ?? FALSE;
      // Try to get a sample value.
      $current_value = NULL;
      if (!empty($value['placeholder'])) {
        $current_value = $this->replacePlaceholders([$value['placeholder']])[0];
        // Check if it has been replaced.
        if ($current_value == $value['placeholder']) {
          $current_value = NULL;
        }
      }

      $form['placeholder_settings']['placeholders'][$i] = [
        '#type' => 'fieldset',
        'placeholder' => [
          '#type' => 'textfield',
          '#title' => $this->t('Placeholder'),
          '#title_display' => 'before',
          '#default_value' => ($value ? $value['placeholder'] : ''),
        ],
      ];
      if (isset($value['constant']) && empty($value['query'])) {
        // Use constant when set and no query.
        // If we need to disable editing on constants or use a hidden field
        // instead:
        // @code
        //   '#disabled' => TRUE,
        //   '#attributes'=> ['readonly' => 'readonly'],
        // @endcode
        // Or:
        // @code
        //   '#type' => 'hidden',
        // @endcode
        $form['placeholder_settings']['placeholders'][$i]['constant'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Constant value'),
          '#title_display' => 'before',
          '#default_value' => ($value ? $value['constant'] : ''),
          '#description' => !empty($current_value)
            ? $this->t(
              'Current value: @current_value',
              ['@current_value' => $current_value]
          )
            : '',
        ];
      }
      else {
        // Use a 'query' as default.
        $form['placeholder_settings']['placeholders'][$i]['query'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Query'),
          '#title_display' => 'before',
          '#default_value' => ($value ? $value['query'] : ''),
          '#description' => !empty($current_value)
            ? $this->t(
              'Current value: @current_value',
              ['@current_value' => $current_value]
          )
            : '',
        ];
      }
    }
    $form['placeholder_settings']['add_placeholder'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a placeholder'),
      '#name' => 'addph_' . $ph_id,
      '#ajax' => [
        'callback' => [
          get_class($this),
          'buildAjaxParentSubForm',
        ],
        'wrapper' => $ph_id,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    return $form;
  }

  /**
   * Build Cread-Read-Update-Delete-List-Count setting form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The Cread-Read-Update-Delete-List-Count setting form.
   */
  public function buildCrudlcForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    // We must keep "queries" as a tree structure event if we flatten it later
    // because we are in a subform and setting #tree to FALSE would make the
    // query values disapear when form_state will flatten the data as they would
    // appear in the parent form_state but not in this children sub-form_state
    // provided for validation.
    $query_html_id =
      ($form['#attributes']['id'] ??= uniqid('sc', TRUE))
      . 'qry';
    $form['queries'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CRUD + List/Count'),
      '#tree' => TRUE,
      '#weight' => 30,
      '#attributes' => ['id' => $query_html_id],
      'crud_description' => [
        '#type' => 'item',
        '#markup' => $this->t(
          'CRUD (<b>C</b>reate <b>R</b>ead <b>U</b>pdate <b>D</b>elete) +
          List/Count queries are used to manage objects.<br/>
          Only 3 queries are required: READ, LIST and COUNT. Leaving others
          empty would just disable their actions.<br/>
          You may use placeholders in queries as defined above.'
        ),
        '#weight' => 10,
        '#attributes' => ['id' => $query_html_id . 'desc'],
      ],
      'create' => [
        '#type' => 'container',
        '#weight' => 100,
        0 => [
          '#type' => 'textarea',
          '#title' => $this->t('CREATE: queries to create a new object'),
          '#required' => FALSE,
          '#default_value' => $this->getQueries(
            'create',
            ['form' => TRUE, 'caller' => 'buildCrudlcForm']
          )[0]
          ?? '',
          '#attributes' => ['id' => $query_html_id . 'create'],
        ],
      ],
      'read' => [
        '#type' => 'container',
        '#weight' => 200,
        0 => [
          '#type' => 'textarea',
          '#title' => $this->t('READ: queries to get a full object'),
          '#required' => TRUE,
          '#default_value' => $this->getQueries(
            'read',
            ['form' => TRUE, 'caller' => 'buildCrudlcForm']
          )[0]
          ?? '',
          '#attributes' => ['id' => $query_html_id . 'read'],
        ],
      ],
      'update' => [
        '#type' => 'container',
        '#weight' => 300,
        0 => [
          '#type' => 'textarea',
          '#title' => $this->t('UPDATE: queries to update an existing object'),
          '#required' => FALSE,
          '#default_value' => $this->getQueries(
            'update',
            ['form' => TRUE, 'caller' => 'buildCrudlcForm']
          )[0]
          ?? '',
          '#attributes' => ['id' => $query_html_id . 'update'],
        ],
      ],
      'delete' => [
        '#type' => 'container',
        '#weight' => 400,
        0 => [
          '#type' => 'textarea',
          '#title' => $this->t('DELETE: queries to delete an object'),
          '#required' => FALSE,
          '#default_value' => $this->getQueries(
            'delete',
            ['form' => TRUE, 'caller' => 'buildCrudlcForm']
          )[0]
          ?? '',
          '#attributes' => ['id' => $query_html_id . 'delete'],
        ],
      ],
      'list' => [
        '#type' => 'container',
        '#weight' => 500,
        0 => [
          '#type' => 'textarea',
          '#title' => $this->t('LIST: query to list objects'),
          '#required' => FALSE,
          '#default_value' => $this->getQueries(
            'list',
            ['form' => TRUE, 'caller' => 'buildCrudlcForm']
          )[0]
          ?? '',
          '#attributes' => ['id' => $query_html_id . 'list'],
        ],
      ],
      'count' => [
        '#type' => 'container',
        '#weight' => 600,
        0 => [
          '#type' => 'textarea',
          '#title' => $this->t('COUNT: query to count objects'),
          '#required' => FALSE,
          '#default_value' => $this->getQueries(
            'count',
            ['form' => TRUE, 'caller' => 'buildCrudlcForm']
          )[0]
          ?? '',
          '#attributes' => ['id' => $query_html_id . 'count'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * This method checks placeholders and sets the 'connection', 'placeholders'
   * and 'queries' values.
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Check for Ajax events.
    if ($trigger = $form_state->getTriggeringElement()) {
      $ph_id = ($form['#attributes']['id'] ??= uniqid('sc', TRUE)) . '_ph';
      if ('addph_' . $ph_id == $trigger['#name']) {
        $placeholder_count = $form_state->get('placeholder_count_' . $ph_id);
        $form_state->set('placeholder_count_' . $ph_id, $placeholder_count + 1);
        $form_state->set('placeholders_open', TRUE);
        $form_state->setRebuild(TRUE);
      }
    }

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * {@inheritdoc}
   *
   * This method removes extra config values ('placeholder_settings'), saves
   * configuration values and reinitializes placeholders.
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Restructure placeholders and only keep fully filled values.
    $placeholders = $form_state->getValue(
      'placeholder_settings',
      ['placeholders' => []]
    )['placeholders'];
    $placeholders = array_filter(
      $placeholders,
      function ($item) {
        return !empty($item['placeholder'])
          && (!empty($item['query']) || isset($item['constant']));
      }
    );
    // Add placeholders prefix if missing.
    foreach ($placeholders as $index => $placeholder) {
      $placeholders[$index]['placeholder'] = preg_replace(
        '#^:*(?=[a-zA-Z])#', ':',
        $placeholder['placeholder']
      );
    }
    // Save placeholders.
    $form_state->setValue('placeholders', $placeholders);

    // We don't need 'placeholder_settings' (redondancy).
    $form_state->unsetValue('placeholder_settings');

    // Set connection.
    $connection = $form_state->getValue('connection', []);
    $form_state->setValue('connection', $connection);

    // Set queries.
    $queries = $form_state->getValue('queries', []);
    $form_state->setValue('queries', $queries);

    $this->setConfiguration($form_state->getValues());

    // Reinit-placeholders.
    $this->initPlaceholders();
  }

  /**
   * Internal function to initialize internal connection.
   *
   * This method is used to open a connection to the external source and avoid
   * reconnecting everytime a query needs to be issued. It should also handle
   * authentication.
   * It should (only) be called by the class constructor and when the
   * configuration is changed (self::setConfiguration()).
   *
   * @param array $configuration
   *   A configuration array.
   */
  abstract protected function initConnection(array $configuration) :void;

  /**
   * Initializes placeholder replacement.
   *
   * @return array
   *   Returns the list of placeholders keyed by external entity type, then by
   *   placeholder name with their associated values.
   */
  abstract protected function initPlaceholders() :array;

  /**
   * {@inheritdoc}
   */
  public function setPlaceholderValue(string $name, $value) :self {
    $entity_type = '';
    if ($this->externalEntityType) {
      $entity_type = $this->externalEntityType->getDerivedEntityTypeId();
    }
    // Init Placeholders if missing and get them.
    $this->placeholders[$entity_type] = $this->placeholders[$entity_type] ?? [];
    $this->placeholders[$entity_type][$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaceholderValue(string $placeholder_name) :mixed {
    return $this->getPlaceholderValues($placeholder_name)[$placeholder_name]
      ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaceholderValues(?string $query = NULL) :array {
    $entity_type = '';
    if ($this->externalEntityType) {
      $entity_type = $this->externalEntityType->getDerivedEntityTypeId();
    }
    // Init Placeholders if missing and get them.
    $this->placeholders[$entity_type] = $this->placeholders[$entity_type] ?? [];

    $placeholders = [];
    if (!empty($query)) {
      foreach ($this->placeholders[$entity_type] as $placeholder => $ph_value) {
        if (preg_match('/(?:^|\W)\Q' . $placeholder . '\E(?:\W|$)/', $query)) {
          $placeholders[$placeholder] = $ph_value;
        }
      }
    }
    else {
      $placeholders = $this->placeholders[$entity_type];
    }

    return $placeholders;
  }

  /**
   * {@inheritdoc}
   */
  public function replacePlaceholders(
    array $queries,
    array $additional_replacements = [],
  ) :array {
    $placeholders = $additional_replacements + $this->getPlaceholderValues();
    array_walk(
      $placeholders,
      function (&$item, $key) {
        if (is_array($item)) {
          $item = implode(', ', $item);
        }
      }
    );
    // Reverse sort by key to replace longuest placeholders first and avoid
    // having a part of a placeholder replaced by another.
    // ex.: ':abc' with value 42 and ':abcdef' with value 806. If we replace
    // first ':abc', a placeholder ':abcdef' would be turned into "42def"
    // instead of "806".
    // We could use regex replacement, which might be less efficient but more
    // reliable.
    krsort($placeholders);

    array_walk_recursive(
      $queries,
      function ($query, $key) use ($placeholders) {
        str_replace(
          array_keys($placeholders),
          array_values($placeholders),
          $query
        );
      }
    );
    return $queries;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueries(string $query_type, array $context = []) :array {
    $queries = $this->configuration['queries'][$query_type] ?? [];
    if (is_string($queries)) {
      $queries = [$queries];
    }
    return $queries;
  }

}
