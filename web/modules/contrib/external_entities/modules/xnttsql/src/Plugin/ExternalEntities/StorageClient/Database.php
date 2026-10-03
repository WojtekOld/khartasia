<?php

namespace Drupal\xnttsql\Plugin\ExternalEntities\StorageClient;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Database as DrupalDatabase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\Sql\Condition;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\dbxschema\Database\DatabaseToolInterface;
use Drupal\dbxschema\Exception\ConnectionException;
use Drupal\dbxschema\Exception\DatabaseToolException;
use Drupal\external_entities\Attribute\StorageClient;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\QueryLanguageClientBase;
use Drupal\xnttsql\Exception\DatabaseExternalEntityException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * External entities storage client for external databases/schemas.
 *
 * @StorageClient(
 *   id = "sql",
 *   label = @Translation("SQL Database"),
 *   description = @Translation("Turns (external) SQL database records into entities using SQL queries.")
 * )
 */
#[StorageClient(
  id: 'sql',
  label: new TranslatableMarkup('SQL Database'),
  description: new TranslatableMarkup('Turns (external) SQL database records into entities using SQL queries.'),
)]
class Database extends QueryLanguageClientBase {

  /**
   * Default schema name identifier.
   */
  const DEFAULT_SCHEMA = '<default>';

  /**
   * Max length for mapping expressions.
   */
  public const MAPPING_FIELD_MAX_LENGTH = 2048;

  /**
   * Available cross connections.
   *
   * @var array
   */
  protected static $xConnections;

  /**
   * The (cross) connection to use.
   *
   * @var \Drupal\dbxschema\Database\CrossSchemaConnectionInterface
   */
  protected $xConnection;

  /**
   * Cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Database tool service.
   *
   * @var \Drupal\dbxschema\Database\DatabaseToolInterface
   */
  protected $databaseTool;

  /**
   * Filter mappings.
   *
   * @var array
   */
  protected $filterMappings;

  /**
   * Constructs a database external storage object.
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
   *   The entity field manager service.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\dbxschema\Database\DatabaseToolInterface $database_tool
   *   Database tool service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token_service,
    MessengerInterface $messenger,
    CacheBackendInterface $cache,
    EventDispatcherInterface $event_dispatcher,
    ?DatabaseToolInterface $database_tool,
  ) {
    // Services injection.
    $this->cache = $cache;
    $this->databaseTool = $database_tool;

    // Complete initialization.
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger,
      $entity_type_manager,
      $entity_field_manager,
      $token_service,
      $messenger,
      $event_dispatcher
    );
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
    $logger = $container->get('logger.factory')->get('external_entities');
    $messenger = $container->get('messenger');
    $db_tool = NULL;
    try {
      $db_tool = $container->get('dbxschema.tool');
    }
    catch (DatabaseToolException | PluginException $e) {
      // DatabaseToolException: Invalid argument passed to DBTool.
      // PluginException: Driver not found.
      $messenger->addError(
        t(
          'Failed to initialize database cross connection tool: @message',
          ['@message' => $e->getMessage()]
        )
      );
      $logger->error(
        'Failed to initialize database cross connection tool: '
        . $e->getMessage()
      );
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $logger,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $messenger,
      $container->get('cache.default'),
      $container->get('event_dispatcher'),
      $db_tool
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'connection'   => [
        // A Drupal setting.php database key.
        'dbkey' => '',
        // An array of schema name strings or an empty array for default schema.
        'schemas' => [],
      ],
      // Structure: array which keys are Drupal fields and values are SQL
      // mappings.
      'filter_mappings' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    // Update placeholders.
    $this->initConnection($configuration, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Filter mapping form.
    $form = $this->buildFilterMappingForm($form, $form_state);

    // Debug information.
    $form['debug'] = [
      '#type' => 'item',
      '#title' => $this->t('Debug SQL queries'),
      '#markup' => $this->t(
        'You can enable SQL query logging using external entity debug mode setting. Level 1 logs queries and levels above also log values.'
      ),
      '#weight' => 1000,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * The "debug" form element is a display-only item: make sure it is not
   * saved into the storage client configuration.
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $form_state->unsetValue('debug');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConnectionSettingForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {

    // Database and schemas selection.
    $form_state->setCached(FALSE);
    $connection_open = $form_state->get('connection_open') ??
      (
        !empty($this->configuration['connection']['schemas'][0])
        && ($this->configuration['connection']['schemas'][0] != static::DEFAULT_SCHEMA)
      );
    $form_state->set('connection_open', $connection_open);

    $form = parent::buildConnectionSettingForm($form, $form_state);
    $dbcon_html_id = ($form['connection']['#attributes']['id'] ??= uniqid('dc', TRUE));
    $form_override = [
      'connection' => [
        'dbkey' => [
          '#type' => 'textfield',
          '#title' => $this->t('Secondary database key name (optional)'),
          '#description' => $this->t(
            "Leave this field empty if you don't know how to use it or if you are
            not using another database than the Drupal one. Otherwise, you can use
            the database \"key\" used to specify another database in your Drupal site
            \"settings.php\" file."
          ),
          '#default_value' => $this->configuration['connection']['dbkey'],
        ],
        'description' => [
          '#type' => 'markup',
          '#markup' =>
          '<div>'
          . $this->t(
            "You may specify one or more schemas if you don't want to use
            just the default one."
          )
          . '</div>',
        ],
      ],
    ];
    $schema_count = $form_state->get('schema_count_' . $dbcon_html_id);
    if (empty($schema_count)) {
      $schema_count = count(
        $this->configuration['connection']['schemas'] ?? []
      ) + 1;
    }
    $form_state->set('schema_count_' . $dbcon_html_id, $schema_count);

    for ($i = 0; $i < $schema_count; ++$i) {
      $value = $this->configuration['connection']['schemas'][$i] ?? '';
      $form_override['connection']['schemas'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Schema name'),
        '#default_value' => $value,
      ];
    }
    // @todo Use a unique name for the button to manage ajax trigger.
    $form_override['connection']['add_schema'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a schema'),
      '#name' => 'addds_' . $dbcon_html_id,
      '#ajax' => [
        'callback' => [
          get_class($this),
          'buildAjaxParentSubForm',
        ],
        'wrapper' => $dbcon_html_id,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];

    // Merge forms.
    $form = $this->overrideForm(
      $form,
      $form_override,
      []
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildCrudlcForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildCrudlcForm($form, $form_state);

    // Get id provided by parent form. This id should remains between form
    // generations.
    $dbft_id =
      ($form['#attributes']['id'] ??= uniqid('sc', TRUE))
      . 'fm';

    $form_override = [
      'queries' => [
        'crud_description' => [
          '#markup' => $this->t(
            'CRUD (<b>C</b>reate <b>R</b>ead <b>U</b>pdate <b>D</b>elete) +
            List/Count SQL queries are used to manage database objects.<br/>
            Only 3 queries are required: READ, LIST and COUNT. Leaving others
            empty would just disable their actions.<br/>
            You should specify tables using the notation
            "<b>{#:<i>tablename</i>}</b>" where "#" is a schema index starting
            from 1 and corresponding to the schema specified above (just use "1"
            if you don\'t know). You may also specify Drupal tables if needed
            using either the regular Drupal notation "{tablename}" or
            {0:tablename}. You may also use placeholders in queries as defined
            above.'
          ),
        ],
        'create' => [
          0 => [
            '#description' => $this->t(
              'Ex. (PostgreSQL): INSERT INTO {1:foo} VALUES (:id_field, :other_field_name, :yet_another_field_name); SELECT currval(\'&lt;name_id_seq&gt;\') AS "id";'
            ),
          ],
        ],
        'create_help' => [
          '#type' => 'details',
          '#title' => $this->t('CREATE Help'),
          '#open' => FALSE,
          '#weight' => ($form['queries']['create']['#weight'] ?? 0) + 20,
          'details' => [
            '#type' => 'markup',
            '#markup' => $this->t(
              'Each query <b>must contain the fields</b> it suppors using
              placeholders ":&lt;field_machine_name&gt;".<br/> For instance, to
              insert a stock that has "name"="My Stock",
              "uniquename"="MYSTOCK001" and "type_name"="stock_types:my type"
              (using the format "cv.name:cvterm.name") fields mapped, use:<br/>
              <code>INSERT INTO {1:stock} (name, uniquename, type_id) SELECT
              :name, :uniquename, cvterm_id FROM {1:cvterm} cvt JOIN {1:cv} cv
              USING (cv_id) WHERE cvt.name = substr(:type_name,
              strpos(:type_name, \':\') + 1) AND cv.name = substr(:type_name, 1,
              strpos(:type_name, \':\') - 1) LIMIT 1;</code><br/>
              The <b>last query MUST return</b> the created item identifier
              value aliased as "id". For PostgreSQL, it would be the last
              sequence value:<br/>
              <code>SELECT currval(\'&lt;name_id_seq&gt;\') AS id;</code><br/>
              and for MySQL it would be:<br/>
              <code>SELECT last_insert_id() AS id;</code>'
            ),
          ],
        ],
        'read' => [
          0 => [
            '#description' => $this->t(
              'Ex.: SELECT id_field, other_field_name, field3 AS "yet_another_field_name" FROM {1:foo} WHERE id_field = :id;'
            ),
          ],
        ],
        'read_help' => [
          '#type' => 'details',
          '#title' => $this->t('READ Help'),
          '#open' => FALSE,
          '#weight' => ($form['queries']['read']['#weight'] ?? 0) + 20,
          'details' => [
            '#type' => 'markup',
            '#markup' => $this->t(
              'The query can be complex with joins and will return the wanted
              fields named using "AS" clause (otherwise, the table field names
              will be used). Returned field names starting with "<i>array_</i>"
              will be parsed as database arrays and will be turned into array of
              values (ie. mapping fields should have a cardinality greater than
              1) while field names starting with "<i>json_</i>" will be parsed
              as JSON data and returned as structured objects.
              The query <b>must contain a placeholder ":id"</b> refering to the
              object identifier which field is aliased as "id". The ":id"
              placeholders used in queries must be used as right operands with
              either "= :id" or "IN (:id)" operators.
              Fields names should be distinct, contain only alphanumeric and
              underscore characters and must not start with a number.<br/>
              Ex.: <code>SELECT s.stock_id AS "id", s.name AS "name",
              s.uniquename AS "uniquename", cv.name || \':\' || cvt.name AS
              "type_name" FROM {1:stock} s JOIN {1:cvterm} cvt ON cvt.cvterm_id
              = s.type_id JOIN {1:cv} cv ON cv.cv_id = cvt.cv_id WHERE
              s.stock_id = :id;</code>.<br/>
              When multiple queries are used, new field values are added to the
              entity and existing ones are replaced by the last queries.'
            ),
          ],
        ],
        'update' => [
          0 => [
            '#description' => $this->t(
              'Ex.: UPDATE {1:foo} SET id_field = :id_field, other_field_name = :other_field_name, field3 = :yet_another_field_name WHERE id_field = :id;'
            ),
          ],
        ],
        'update_help' => [
          '#type' => 'details',
          '#title' => $this->t('UPDATE Help'),
          '#open' => FALSE,
          '#weight' => ($form['queries']['update']['#weight'] ?? 0) + 20,
          'details' => [
            '#type' => 'markup',
            '#markup' => $this->t(
              'Multiple queries are allowed separated by ";". Each query <b>must
              contain the fields</b> it supports using placeholders
              ":&lt;field_machine_name&gt;".<br/>
              For instance, to update a stock that has "name", "uniquename" and
              "type_name" (using the format "cv.name:cvterm.name") fields mapped,
              use:<br/>
              <code>UPDATE {1:stock} SET name = :name, uniquename = :uniquename,
              type_id = (SELECT :name, :uniquename, cvterm_id FROM {1:cvterm}
              cvt JOIN {1:cv} cv USING (cv_id) WHERE s.stock_id = :stock_id AND
              cvt.name = substr(:type_name, strpos(:type_name, \':\') + 1) AND
              cv.name = substr(:type_name, 1, strpos(:type_name, \':\') - 1)
              LIMIT 1);</code>'
            ),
          ],
        ],
        'delete' => [
          0 => [
            '#description' => $this->t(
              'Ex.: DELETE FROM {1:foo} WHERE id_field = :id;'
            ),
          ],
        ],
        'delete_help' => [
          '#type' => 'details',
          '#title' => $this->t('DELETE Help'),
          '#open' => FALSE,
          '#weight' => ($form['queries']['delete']['#weight'] ?? 0) + 20,
          'details' => [
            '#type' => 'markup',
            '#markup' => $this->t(
              'Each query must contain the fields it needs using placeholders
              ":<field_machine_name>". There should be the identifier field
              followed by the "= :id" placeholder.<br/>
              For instance, to delete a given stock, use:<br/>
              <code>DELETE FROM {1:stock} WHERE stock_id = :id;</code>'
            ),
          ],
        ],
        'list' => [
          0 => [
            '#description' => $this->t(
              'Ex.: SELECT id_field, other_field_name, field3 AS "yet_another_field_name" FROM {1:foo} WHERE :filters'
            ),
          ],
        ],
        'list_help' => [
          '#type' => 'details',
          '#title' => $this->t('LIST Help'),
          '#open' => FALSE,
          '#weight' => ($form['queries']['list']['#weight'] ?? 0) + 20,
          'details' => [
            '#type' => 'markup',
            '#markup' => $this->t(
              'There must be only <b>one statement</b> for listing. The query
              <b>must return object IDs and names</b> and <b>must NOT include
              "ORDER BY", "LIMIT" and "OFFSET" clauses</b> which will be
              appended automatically to the query by the system. It should
              include a placeholder ":filters" as the last condition in a WHERE
              clause to indicate where filters could be added by the system in
              order to filter the list. Since the list query may be used without
              filters, the system should automatically remove the "WHERE"
              keyword if it is followed by nothing (otherwise consider using
              "... WHERE TRUE :filters ...").<br/>
              When you use aliased columns (ie. SELECT ... <u>AS "..."</u>) you
              must specify their mapping in the
              "<a href="#@filtermap_div_id">Filter mapping</a>"
              section below. For instance, if you used <code>SELECT ... field3
              AS "yet_another_field_name"...</code> in the READ query, you will
              have to add a mapping for the field "yet_another_field_name" that
              will contain the value "field3".',
              ['@filtermap_div_id' => $dbft_id]
            ),
          ],
        ],
        'count' => [
          0 => [
            '#description' => $this->t(
              'Ex.: SELECT COUNT(1) FROM {1:foo} WHERE TRUE :filters'
            ),
          ],
        ],
        'count_help' => [
          '#type' => 'details',
          '#title' => $this->t('COUNT Help'),
          '#open' => FALSE,
          '#weight' => ($form['queries']['count']['#weight'] ?? 0) + 20,
          'details' => [
            '#type' => 'markup',
            '#markup' => $this->t(
              'The COUNT query must follow the same restrictions as the LIST
              query, the only difference is that the query must return just one
              integer as "count".'
            ),
          ],
        ],
      ],
    ];

    // Merge forms.
    $form = $this->overrideForm(
      $form,
      $form_override,
      []
    );

    return $form;
  }

  /**
   * Builds filter mapping form.
   *
   * @param array $form
   *   The form that will be modified.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The filter mapping form.
   */
  public function buildFilterMappingForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    // Get id provided by parent form.
    $dbft_id =
      ($form['#attributes']['id'] ??= uniqid('sc', TRUE))
      . 'fm';

    $filters = $form_state->get('filter_mappings')
      ?? $this->getFilterMappings();
    if (empty($filters)) {
      // Try to generate list form LIST query.
      $filters = $this->guessListFilters();
    }

    $filter_count = $form_state->get('filter_count_' . $dbft_id);
    if (empty($filter_count)) {
      $filter_count =
        count($filters) + 1;
    }
    $form_state->set('filter_count_' . $dbft_id, $filter_count);

    $filters_open = $form_state->get('filters_open');
    $form_state->set('filters_open', $filters_open);

    $form['filter_mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter mapping'),
      '#open' => $filters_open ?? FALSE,
      '#description' => $this->t('This mapping associates an SQL column name to
        an SQL expression that can be used to match a given column value.
        Most of those mapping will be guessed by the system so you could leave
        those mapping empty. However in some cases, you may have to specify the
        mapping manually.<br/>
        For instance, if your LIST query looks like this:
        <code>SELECT ..., cv.name || \':\' || cvt.name AS "type_name", ... FROM ...</code>
        you will have to specify the mapping value
        "<code>cv.name || \':\' || cvt.name</code>" for the column "type_name".
        <br/>
        You may need to save your LIST query first and then come back to this
        page to see how columns are automatically mapped.
      '),
      '#weight' => 750,
      '#attributes' => ['id' => $dbft_id],
    ];

    $i = 0;
    foreach ($filters as $alias => $expression) {
      $form['filter_mappings']['filters'][$i] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xntt-inline']],
      ];
      $form['filter_mappings']['filters'][$i]['alias'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alias/result column name ("AS")'),
        '#default_value' => $alias,
      ];
      // Try to guess default value if not set.
      if (empty($expression)) {
        $expression = $this->getFilterMapping($alias);
      }
      $form['filter_mappings']['filters'][$i]['expression'] = [
        '#type' => 'textfield',
        '#title' => $this->t('mapped using (value expression)'),
        '#default_value' => $expression,
        '#maxlength' => static::MAPPING_FIELD_MAX_LENGTH,
      ];
      ++$i;
    }
    while ($i < $filter_count) {
      $form['filter_mappings']['filters'][$i] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xntt-inline']],
      ];
      $form['filter_mappings']['filters'][$i]['alias'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alias/result column name ("AS")'),
        '#default_value' => '',
      ];
      $form['filter_mappings']['filters'][$i]['expression'] = [
        '#type' => 'textfield',
        '#title' => $this->t('mapped using (value expression)'),
        '#default_value' => '',
        '#maxlength' => static::MAPPING_FIELD_MAX_LENGTH,
      ];
      ++$i;
    }
    // @todo Use a unique name to manage ajax trigger.
    $form['filter_mappings']['add_filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a filter'),
      '#name' => 'addft_' . $dbft_id,
      '#ajax' => [
        'callback' => [
          get_class($this),
          'buildAjaxParentSubForm',
        ],
        'wrapper' => $dbft_id,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    return $form;
  }

  /**
   * Get SQL column name or expression used in LIST query.
   *
   * @return array
   *   An associative array of SQL column names with their associated SQL
   *   expression.
   */
  protected function guessListFilters() :array {
    $filters = [];
    $list_query = $this->getQueries(
      'list',
      ['caller' => 'guessListFilters']
    )[0] ?? '';

    // Extract SELECT clause.
    $select_regex =
      '/^\s*SELECT\s+(?:(?:ALL|DISTINCT|DISTINCTROW|HIGH_PRIORITY|STRAIGHT_JOIN|SQL_SMALL_RESULT|SQL_BIG_RESULT|SQL_BUFFER_RESULT|SQL_NO_CACHE|SQL_CALC_FOUND_ROWS)\s+)*(.+?)(?:FROM(?!.*\sFROM)|$)/is';
    if (preg_match($select_regex, $list_query, $match)) {
      $select = $match[1];
    }
    if (empty($select)) {
      return [];
    }
    // Regex to match column names or expressions and their corresponding
    // aliases.
    $regex = '/'
      // First the possibly qualified column name or expression we want
      // to capture.
      . '(?P<exp>'
      // It can be a possibly qualified and non-quoted column name possibly
      // with a cast.
      . '(?:\w+\.)?\w+(?:::\s*\w+(?:\(\s*\d+\s*\)|\[\])?)?'
      // Or it could also be quoted (both with the qualifier) possibly with
      // cast.
      . '|(?:BINARY\s+)?(?:"[^\"]+"\.)?"[^\"]+"(?:::\s*\w+(?:\(\s*\d+\s*\)|\[\])?)?'
      . '|(?:BINARY\s+)?(?:`[^`]+`\.)?`[^\`]+`(?:::\s*\w+(?:\(\s*\d+\s*\)|\[\])?)?'
      // Or it can be a function call like a CAST.
      . '|\w+\s*(?P<par1>\((?:(?>[^()]+)|(?&par1))*\))'
      // Or if it starts by an opening parenthesis, we want to get all through
      // the matching closing one (sub-SELECT clause for instance).
      . '|(?P<par2>\((?:(?>[^()]+)|(?&par2))*\))'
      // Then we want to match the alias name either quoted or not and
      // prefixed by 'AS' or not.
      . ')(?:\s+FILTER\s+(?P<par3>\((?:(?>[^()]+)|(?&par3))*\)))?(?:\s+AS|)(?:\s+"(?P<qalias>[^"]+)"|\s+(?P<alias>\S+)|)?\s*(?:,|$)/is';
    if (preg_match_all($regex, $select, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        // $column = ($match[2] ?? '') ?: ($match[3] ?? '') ?: $match[1];
        $column = ($match['alias'] ?? '') ?: ($match['qalias'] ?? '') ?: $match['exp'];
        $filters[] = [
          'alias' => $column,
          'expression' => $match['exp'],
        ];
      }
    }
    return $filters;
  }

  /**
   * {@inheritdoc}
   *
   * The validation expects 4 values from the $form_state:
   * - 'connection': it must be an aray like this: [
   *     'dbkey' => 'a database key or an empty string',
   *     'schemas' => [ an array of schema names as strings ],
   *   ]
   * - 'queries': it must be an array like this: [
   *     'create' => 'SQL query/ies or empty string',
   *     'read' =>
   *       'non-empty SQL query/ies with *... = :id* or *... IN (:id[])*',
   *     'update' => 'SQL query/ies or empty string',
   *     'delete' => 'SQL query/ies or empty string',
   *     'list' => 'single SQL query with *:filters*',
   *     'count' => 'single SQL query with *... AS "count"* and *:filters*',
   *   ]
   * - 'placeholder_settings': it must be an array like this: [
   *     [
   *       'placeholders'=> [
   *         'placeholder' => ':placeholder_name',
   *         // Either 'query' or 'constant' key but not both.
   *         'query' => 'SQL query to fetch the placeholder value',
   *         'constant' => 'A constant value to use',
   *       ],
   *       ...
   *     ],
   *   ]
   * - 'filter_mappings': it must be an array like this: [
   *     some_field_machine_name =>
   *       'the corresponding SQL expression to map that field',
   *     ...
   *   ]
   *
   * Note: there is no ::submitConfigurationForm because of the PluginForm
   * system. All is done here.
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    parent::validateConfigurationForm($form, $form_state);
    // Check for Ajax events.
    if ($trigger = $form_state->getTriggeringElement()) {
      $dbsc_id = ($form['connection']['#attributes']['id'] ??= uniqid('dc', TRUE));
      $dbft_id = ($form['#attributes']['id'] ??= uniqid('sc', TRUE)) . 'fm';

      if ('addds_' . $dbsc_id == $trigger['#name']) {
        $schema_count = $form_state->get('schema_count_' . $dbsc_id);
        $form_state->set('schema_count_' . $dbsc_id, $schema_count + 1);
        $form_state->set('connection_open', TRUE);
        $form_state->setRebuild(TRUE);
      }
      elseif ('addft_' . $dbft_id == $trigger['#name']) {
        $filter_count = $form_state->get('filter_count_' . $dbft_id);
        $form_state->set('filter_count_' . $dbft_id, $filter_count + 1);
        $form_state->set('filters_open', TRUE);
        $form_state->setRebuild(TRUE);
      }
    }

    // Validate connection settings.
    $schemas = array_filter(
      $form_state->getValue(['connection', 'schemas'], [])
    );
    $dbkey = $form_state->getValue(['connection', 'dbkey'], '');
    $form_state->setValue('connection', [
      'dbkey' => $dbkey,
      'schemas' => $schemas,
    ]);

    // Validate queries.
    // Check query structures (single/multitple), presence of ':id', etc.
    $queries =
      $form_state->getValue('queries', [])
      + $query_types = [
        'create' => [''],
        'read'   => [''],
        'update' => [''],
        'delete' => [''],
        'list'   => [''],
        'count'  => [''],
      ];
    if (empty($queries['read'][0])) {
      // Non-empty string to allow text below after but empty value for final
      // invalidation.
      $queries['read'][0] = '0';
    }
    // Try to auto-generate missing mandatory queries.
    if (empty($queries['list'][0])) {
      // Generate LIST from READ.
      $list = $queries['read'][0];
      if (1 === preg_match_all('/(^|\W)SELECT\W/is', $list)) {
        // Remove ":id" filter.
        $list = preg_replace(
          '/(?:AND\s+)?(?:[\w\.]+\s*(?:=|IN)\s*(?::id(?:\[\])?|\(:id(?:\[\])?\))|:id\s*(?:=|IN)\s*(?::?[\w\.]+|\(:?[\w\.]+\)))(?:\s|$)/is',
          ' ',
          $list
        );
        // If first condition, remove the extra "AND".
        $list = preg_replace('/WHERE\s+AND\s/is', 'WHERE ', $list);
        $list = rtrim($list, "; \n\r\t\v\x00");
        if (!preg_match('/\W:id\W/', $list)) {
          if (preg_match('/\WWHERE(?:\W|$)\s*(.*\w.*|)$/is', $list, $match)) {
            if (empty($match[1])) {
              $list .= ' TRUE :filters';
            }
            else {
              $list .= ' :filters';
            }
          }
          else {
            $list .= ' WHERE TRUE :filters';
          }
          $this->messenger->addWarning(
            $this->t(
              'The LIST query was empty. An auto-generated one has been set but might not work. You may have to fix it manually.'
            )
          );
          $this->logger->warning(
            'The LIST query was empty. An auto-generated one has been set but might not work. You may have to fix it manually.'
          );
          $queries['list'][0] = $list;
          $form_state->set('list', $list);
        }
      }
    }
    if (!empty($queries['list'][0]) && empty($queries['count'][0])) {
      $count = $queries['list'][0];
      $count = preg_replace('/^\s*SELECT\s.+?\sFROM\s/is', 'SELECT COUNT(1) AS "count" FROM ', $count);
      if ($count != $queries['list'][0]) {
        $this->messenger->addWarning(
          $this->t(
            'The COUNT query was empty. An auto-generated one has been set but might not work. You may have to fix it manually.'
          )
        );
        $this->logger->warning(
          'The COUNT query was empty. An auto-generated one has been set but might not work. You may have to fix it manually.'
        );
        $queries['count'][0] = $count;
        $form_state->set('count', $count);
      }
    }

    if (!preg_match('/(?:=|\WIN|\Win)\s*\(?:id(?:\W|$)/', $queries['read'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'read']);
      $form_state->setErrorByName('queries][read', 'The READ query must contain a "= :id" placeholder.');
    }
    if (empty($queries['read'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'read']);
      $form_state->setErrorByName('queries][read', 'The READ query is mandatory.');
    }
    if (empty($queries['list'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'list']);
      $form_state->setErrorByName('queries][list', 'The LIST query is mandatory (and could not be auto-generated from the READ query).');
    }
    if (empty($queries['count'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'count']);
      $form_state->setErrorByName('queries][count', 'The COUNT query is mandatory (and could not be auto-generated from the LIST query).');
    }
    if (preg_match('/\w.*;.*\w/', $queries['list'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'list']);
      $form_state->setErrorByName('queries][list', 'The LIST query must not contain more than one query.');
    }
    if (preg_match('/\w.*;.*\w/', $queries['count'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'count']);
      $form_state->setErrorByName('queries][count', 'The COUNT query must not contain more than one query.');
    }
    if (!preg_match('/\Wcount\s*\(|\s"?count"?\s/is', $queries['count'][0])) {
      // Get value to let FormState know the element we complain about.
      $form_state->getValue(['queries', 'count']);
      $form_state->setErrorByName('queries][count', 'The COUNT query must return a column named/aliased "count".');
    }
    // Check if the fields were filled to avoid displaying unecessary warnings.
    if (preg_match('/\w/', $queries['list'][0]) && !preg_match('/:filters(?:\W|$)/', $queries['list'][0])) {
      $this->messenger->addWarning(
        $this->t(
          'The LIST query does not contain a ":filters" placeholder. If the LIST query fails, add a ":filters" placeholder to fix it.'
        )
      );
      $this->logger->warning(
        'The LIST query does not contain a ":filters" placeholder. If the LIST query fails, add a ":filters" placeholder to fix it.'
      );
    }
    if (preg_match('/\w/', $queries['count'][0]) && !preg_match('/:filters(?:\W|$)/', $queries['count'][0])) {
      $this->messenger->addWarning(
        $this->t(
          'The COUNT query does not contain a ":filters" placeholder. If the LIST query fails, add a ":filters" placeholder to fix it.'
        )
      );
      $this->logger->warning(
        'The COUNT query does not contain a ":filters" placeholder. If the LIST query fails, add a ":filters" placeholder to fix it.'
      );
    }
    if (preg_match('/\w/', $queries['create'][0]) && !preg_match('/(^\s*|;\s*)SELECT\s(?!.*;\s*UPDATE\W).*$/is', $queries['create'][0])) {
      $this->messenger->addWarning(
        $this->t(
          'The CREATE query does not seem to return the created entity identifier. Verify the query.'
        )
      );
      $this->logger->warning(
        'The CREATE query does not seem to return the created entity identifier. Verify the query.'
      );
    }
    if (preg_match('/\w/', $queries['update'][0]) && !preg_match('/\W:id(?:\W|$)/', $queries['update'][0])) {
      $this->messenger->addWarning(
        $this->t(
          'The UPDATE query does not contain a ":id" placeholder. It may update more than the expected entity. Verify the query.'
        )
      );
      $this->logger->warning(
        'The UPDATE query does not contain a ":id" placeholder. It may update more than the expected entity. Verify the query.'
      );
    }
    if (preg_match('/\w/', $queries['delete'][0]) && !preg_match('/\W:id(?:\W|$)/', $queries['delete'][0])) {
      $this->messenger->addWarning(
        $this->t(
          'The DELETE query does not contain a ":id" placeholder. It may delete more than the expected entity. Verify the query.'
        )
      );
      $this->logger->warning(
        'The DELETE query does not contain a ":id" placeholder. It may delete more than the expected entity. Verify the query.'
      );
    }

    // Save queries.
    $form_state->setValue('queries', $queries);

    // Validate placeholders.
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

    // Make sure every ':placeholder' in queries has been defined.
    $valid_placeholders = [':id' => TRUE, ':id[]' => TRUE, ':filters' => TRUE];
    foreach ($placeholders as $index => $placeholder) {
      $valid_placeholders[$placeholders[$index]['placeholder']] = TRUE;
    }
    $missing_placeholders = [];
    $query_types = [
      'create' => 'CREATE query',
      'read'   => 'READ query',
      'update' => 'UPDATE query',
      'delete' => 'DELETE query',
      'list'   => 'LIST query',
      'count'  => 'COUNT query',
    ];
    foreach ($query_types as $query_type => $query_name) {
      if (preg_match_all('/[^\w:](:\w+(?:\[\])?)(?:\W|$)/', $queries[$query_type][0], $matches)) {
        foreach ($matches[1] as $match) {
          if (!array_key_exists($match, $valid_placeholders)) {
            $missing_placeholders[$match] = $query_type;
          }
        }
      }
    }
    if (!empty($missing_placeholders)) {
      $this->messenger->addWarning(
        $this->t(
          'The following placeholders are used in queries but are not defined: %placeholders',
          ['%placeholders' => implode(', ', array_keys($missing_placeholders))]
        )
      );
      $this->logger->warning('The following placeholders are used in queries but are not defined: ' . implode(', ', array_keys($missing_placeholders)));
    }

    // Filter mappings.
    $filter_mappings = [];
    $filter_mapping_keys = [];
    $fm_values = $form_state->getValue(['filter_mappings', 'filters'], []);
    // Ignore incomplete mappings.
    foreach ($fm_values as $fm_value) {
      if (!empty($fm_value['alias']) && !empty($fm_value['expression'])) {
        $filter_mappings[] = [
          'alias' => $fm_value['alias'],
          'expression' => $fm_value['expression'],
        ];
        $filter_mapping_keys[$fm_value['alias']] = $fm_value['expression'];
      }
    }
    // Set for config save (submit handler).
    $form_state->setValue('filter_mappings', $filter_mappings);
    // Set for form update (rebuild).
    $form_state->set('filter_mappings', $filter_mapping_keys);

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * Internal function to initialize connection.
   *
   * @param array $configuration
   *   A configuration array.
   */
  protected function initConnection(array $configuration) :void {
    // Get database key if one.
    $dbkey = $configuration['connection']['dbkey'] ?? '' ?: 'default';

    // Gets schema and default schema.
    $schemas = $configuration['connection']['schemas'] ?? [];
    if (empty($schemas)) {
      // Schema is empty, meaning that we will use default.
      // For PostgreSQL, default schema is the one used by Drupal, usualy
      // 'public' or something set by 'search_path' in database 'init_commands'.
      // It does not need to be set.
      // However, for MySQL/MariaDB the notion of 'schema' is in fact the
      // database name and it must be set.
      $database_info = DrupalDatabase::getConnectionInfo();
      if (($database_info['default']['driver'] === 'mysql')
          && !empty($database_info[$dbkey]['database'])
      ) {
        // In the case of MySQL, we can set default "schema" to current
        // Drupal database or used database key.
        $schemas = [$database_info[$dbkey]['database']];
      }
    }
    $default_schema = array_shift($schemas);
    if (!empty($default_schema) && !empty($this->databaseTool)) {
      $ckey = $dbkey . '#' . $default_schema . '#' . implode('#', $schemas);
      // Try to get connection from static connection pool.
      if (!empty(static::$xConnections[$ckey])) {
        $this->xConnection = static::$xConnections[$ckey];
        try {
          // Initializes placeholders replacement.
          $this->initPlaceholders();
        }
        catch (DatabaseExternalEntityException $e) {
          $this->messenger->addError(
            $this->t(
              'Database External Entity initialization failed: @message',
              ['@message' => $e->getMessage()]
            )
          );
          $this->logger->error(
            'Database External Entity initialization failed: '
            . $e->getMessage()
          );
        }
      }
      else {
        // Initializes connection, first schema and extra-schemas.
        try {
          if (empty($default_schema)
              || (strtolower($default_schema) == static::DEFAULT_SCHEMA)
          ) {
            $default_schema = '';
          }
          // Get an external schema connection.
          try {
            $connection = $this->databaseTool->getConnection($default_schema, $dbkey);
          }
          catch (ConnectionException $e) {
            throw new DatabaseExternalEntityException(
              'Failed to get a valid connection. ' . $e->getMessage()
            );
          }
          // Initialize connection.
          if (!$connection->schema()->schemaExists()) {
            throw new DatabaseExternalEntityException(
              'Invalid schema "'
              . $default_schema
              . '". The schema does not exist.'
            );
          }
          // Get related database tool (instead of default provided one).
          $db_tool = $this->databaseTool = $connection->getDatabaseTool();
          // Adds extra schemas.
          foreach ($schemas as $schema_name) {
            if (!$db_tool->schemaExists($schema_name)) {
              throw new DatabaseExternalEntityException(
                'Invalid schema "'
                . $schema_name
                . '". The schema does not exist.'
              );
            }
            $connection->addExtraSchema($schema_name);
          }
          static::$xConnections[$ckey] = $this->xConnection = $connection;

          // Initializes placeholders replacement.
          $this->initPlaceholders();
        }
        catch (DatabaseExternalEntityException | DatabaseToolException | PluginException $e) {
          $this->messenger->addError(
            $this->t(
              'Database External Entity initialization failed: @message',
              ['@message' => $e->getMessage()]
            )
          );
          $this->logger->error(
            'Database External Entity initialization failed: '
            . $e->getMessage()
          );
        }
      }
    }
  }

  /**
   * Expands a database array of a field value.
   *
   * Database driver used returns array field results as strings starting with
   * '{' and ending with '}' which values are separated by comas ",".
   *
   * @param string|array $sql_array
   *   A field value containing a database array.
   *
   * @return array
   *   The array of values.
   */
  protected function expandDatabaseArray(string|array $sql_array) :array {
    $new_array = [];
    if (is_string($sql_array)) {
      if ((0 < strlen($sql_array))
        && ('{' === $sql_array[0])
        && ('}' === substr($sql_array, -1))
      ) {
        // Valid (PostgreSQL) array syntax so far.
        $sql_array = substr($sql_array, 1, -1);
        $i = 0;
        $current_value = '';
        $current_set = &$new_array;
        $parent_set = [];
        while ($i < strlen($sql_array)) {
          if ('"' === $sql_array[$i]) {
            // Get quoted value.
            ++$i;
            $current_value = '';
            while (($i < strlen($sql_array)) && ('"' !== $sql_array[$i])) {
              $current_value .= $sql_array[$i++];
              if (($i < strlen($sql_array)) && ('\\' === $sql_array[$i - 1])) {
                // Escape character, skip next.
                $current_value .= $sql_array[$i++];
              }
            }
            ++$i;
            $current_set[] = stripslashes($current_value);
          }
          elseif ('{' === $sql_array[$i]) {
            // Start a sub-array.
            ++$i;
            $current_value = '';
            $parent_set[] = &$current_set;
            $current_set[] = [];
            $current_set = &$current_set[count($current_set) - 1];
          }
          elseif ('}' === $sql_array[$i]) {
            // End a sub-array.
            ++$i;
            $current_value = '';
            $current_set = &$parent_set[count($parent_set) - 1];
            array_pop($parent_set);
          }
          elseif (',' === $sql_array[$i]) {
            // Comma separation.
            $current_value = '';
            ++$i;
          }
          elseif (' ' === $sql_array[$i]) {
            // space.
            ++$i;
          }
          else {
            // Get non-quoted value.
            $current_value = '';
            while (($i < strlen($sql_array)) && (',' !== $sql_array[$i]) && ('}' !== $sql_array[$i])) {
              $current_value .= $sql_array[$i++];
            }
            // Special cases.
            if ('NULL' == $current_value) {
              $current_value = NULL;
            }
            elseif ('f' == $current_value) {
              $current_value = FALSE;
            }
            elseif ('t' == $current_value) {
              $current_value = TRUE;
            }
            // @todo Maybe floating point values need special handling as well.
            $current_set[] = $current_value;
          }
          // Skip spaces.
          while (($i < strlen($sql_array)) && (' ' === $sql_array[$i])) {
            ++$i;
          }
        }
      }
      else {
        // Turn invalid string into an array of one element.
        $new_array[] = $sql_array;
      }
    }
    elseif (is_array($sql_array)) {
      // Already an array, maybe the driver already handles arrays.
      $new_array = $sql_array;
    }

    return $new_array;
  }

  /**
   * Expands a database JSON of a field value.
   *
   * Expected $sql_json is just a string representation of a JSON structure.
   *
   * @param string $sql_json
   *   A string field value containing a JSON structure.
   * @param string $info
   *   Optional additionnal info to log in case of decoding error.
   *
   * @return array
   *   The JSON structure in PHP representation.
   */
  protected function expandDatabaseJson(
    string $sql_json,
    string $info = '',
  ) :array {
    // Return an empty array for an empty string.
    if (('' === $sql_json) || ('null' === strtolower($sql_json))) {
      return [];
    }
    $json = Json::decode($sql_json);
    if (NULL === $json) {
      if (!empty($this->externalEntityType)) {
        $info =
          ' for '
          . $this->externalEntityType->getDerivedEntityTypeId()
          . " ($info)";
      }
      $this->logger->warning(
        "Failed to parse JSON data $info:\n"
        . $sql_json
        . "\nError:\n"
        . json_last_error_msg()
      );
      // Fallback to raw value in an array.
      $json = [$sql_json];
    }
    return $json;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) :array {
    $data = [];

    if (empty($this->xConnection)) {
      // No connection available.
      $this->messenger->addError('Data loading failed: no connection available.');
      $this->logger->error('Data loading error: no connection available.');
      return $data;
    }

    $id_field = $this->getIdField();
    if (!isset($id_field) || ('' === $id_field)) {
      // No database table column identifier provided.
      $this->messenger->addError('Data loading failed: no database identifier field provided.');
      $this->logger->error('Data loading error: no database identifier field provided.');
      return $data;
    }

    try {
      // Replaces "=:id" or "IN(:id)" with "IN(:id[])".
      // Note: replacing !=:id, >=:id and <=:id is not supported.
      $queries = preg_replace(
        ['/(?<![!<>])=\s*:id(\W|$)/', '/(\W)IN\s*\(?:id(?:\[\])?\)?(\W|$)/is'],
        [' IN (:id[])\1', '\1IN (:id[])\2'],
        $this->getQueries('read', ['caller' => 'loadMultiple'])[0] ?? ''
      );
      // Make sure $queries does not contain any invalid ':id' left.
      if (preg_match('/\W:id(?:[^\[a-zA-Z_]|$)/', $queries)) {
        // There are still some ":id" left.
        $this->logger->error("Failed to replace all \":id\" with \"IN (:id[])\". Query: " . $queries);
      }
      $sql_queries = [];
      // @todo better parse ';' as it can be used in query strings.
      $sql_queries = explode(';', $queries);
      // Loop on queries.
      foreach ($sql_queries as $sql_query) {
        $sql_query = trim($sql_query);
        if (empty($sql_query)) {
          continue;
        }
        $qargs = [':id[]' => $ids] + $this->getPlaceholders($sql_query);
        if (1 == $this->getDebugLevel()) {
          $this->logger->debug(
            "READ SQL Query:\n@sql",
            ['@sql' => $sql_query]
          );
        }
        elseif ($this->getDebugLevel()) {
          $this->logger->debug(
            "READ SQL Query:\n@sql\n\nArguments:\n@args",
            ['@sql' => $sql_query, '@args' => $this->dumpArguments($qargs)]
          );
        }
        // Fetch corresponding data in database.
        $results = $this->xConnection->query(
          $sql_query,
          $qargs
        )->fetchAllAssoc($id_field, \PDO::FETCH_ASSOC);

        // Restructures PostgreSQL arrays.
        foreach ($results as $id => $element) {
          foreach ($element as $key => $value) {
            if (0 === strpos($key, 'array_')) {
              $data[$id][$key] = $this->expandDatabaseArray($value ?? '');
            }
            elseif (0 === strpos($key, 'json_')) {
              $info = "(id:$id, field:$key)";
              $data[$id][$key] = $this->expandDatabaseJson($value ?? '', $info);
            }
            else {
              $data[$id][$key] = $value;
            }
          }
        }
      }
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }
    catch (IntegrityConstraintViolationException $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }
    if (empty($data)) {
      $data = [];
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) :int {
    // Check if ID exists in base.
    $entity_data = $entity->toRawData();
    if ($entity->id()) {
      $updated_entity = $this->runQueries(
        [$this->getQueries('update', ['caller' => 'save'])[0] ?? ''],
        $entity_data
      );
      if (empty($updated_entity)) {
        $this->messenger->addError(
          'Failed to update entity ' . $entity->id()
        );
        $result = 0;
      }
      else {
        $result = SAVED_UPDATED;
      }
    }
    else {
      $new_entity = $this->runQueries(
        [$this->getQueries('create', ['caller' => 'save'])[0] ?? ''],
        $entity_data
      );

      if (empty($new_entity)) {
        $this->messenger->addError('Failed to create new entity.');
        $result = 0;
      }
      else {
        $result = SAVED_NEW;
        // The last "create" query is supposed to return the new entity
        // identifier in an 'id' column.
        if (!isset($new_entity['id'])) {
          $this->messenger->addWarning(
            'The creation query set does not return inserted identifier.'
          );
          $this->logger->warning(
            $this->t(
              'The creation query set does not return inserted identifier.'
            )
          );
          $entity->set('id', 0);
        }
        else {
          $entity->set('id', $new_entity['id']);
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) :int {
    $entity_data = $entity->toRawData();
    $success = $this->runQueries(
      [$this->getQueries('delete', ['caller' => 'delete'])[0] ?? 0],
      $entity_data
    );
    if (empty($success)) {
      $this->messenger->addError(
        'Failed to delete entity ' . $entity->id()
      );
    }
    return SAVED_DELETED;
  }

  /**
   * Returns the database column name used for the entity identifier field.
   *
   * @return string|null
   *   The database column name used for the entity identifier field or NULL if
   *   not available.
   */
  public function getIdField() :?string {
    // Get database identifier field for database queries.
    if (!empty($this->externalEntityType)) {
      try {
        $id_field_mapper = $this->externalEntityType->getFieldMapper('id');
        if (empty($id_field_mapper)) {
          throw new PluginNotFoundException(
            'Cannot process External Entity "'
            . $this->externalEntityType->getDerivedEntityTypeId()
            . '": no field mapper for identifier field.'
          );
        }
        $database_id_field = $id_field_mapper->getMappedSourceFieldName();
      }
      catch (PluginNotFoundException $e) {
        // The external entity type may have not been saved yet. Do nothing.
      }
    }
    return $database_id_field;
  }

  /**
   * {@inheritdoc}
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL,
  ) :array {
    if (empty($this->xConnection)) {
      // No connection available.
      $this->messenger->addError('Query failed: no connection available.');
      return [];
    }

    try {
      $start = $start ?? 0;
      $mapped_sorts = [];
      foreach ($sorts as $sort) {
        if (in_array(strtoupper($sort['direction']), ['ASC', 'DESC'])) {
          $mapped_sorts[] = $sort['field'] . ' ' . $sort['direction'];
        }
        else {
          $this->logger->warning(
            'Unsupported sort parameter: ' . print_r($sort, TRUE)
          );
        }
      }
      $order_clause = empty($mapped_sorts)
        ? ''
        : ' ORDER BY ' . implode(',', $mapped_sorts);
      [$filters, $fvalues] = $this->getSqlFiltersAndValues($parameters);
      $query = rtrim($this->getQueries(
        'list',
        ['caller' => 'querySource', 'filters' => $filters])[0] ?? '',
        ';'
      );
      $query .= $order_clause;
      $qargs = $this->getPlaceholders($query);
      $all_args = $fvalues + $qargs;
      if (1 == $this->getDebugLevel()) {
        $this->logger->debug(
          "LIST SQL Query:\n@sql",
          ['@sql' => $query]
        );
      }
      elseif ($this->getDebugLevel()) {
        $this->logger->debug(
          "LIST SQL Query:\n@sql\n\nArguments:\n@args",
          ['@sql' => $query, '@args' => $this->dumpArguments($all_args)]
        );
      }
      if (isset($length)) {
        $results = $this->xConnection->queryRange(
          $query,
          $start,
          $length,
          $all_args)
          ->fetchAll(\PDO::FETCH_ASSOC);
      }
      else {
        if ($start) {
          $this->logger->warning(
            "An offset value has been specified in the LIST SQL query without a length value. Offset will be ignored.\nQuery:\n@sql\n\nArguments:\n@args",
            ['@sql' => $query, '@args' => $this->dumpArguments($all_args)]
          );
        }
        $results = $this->xConnection->query(
          $query,
          $all_args
        )->fetchAll(\PDO::FETCH_ASSOC);
      }
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }
    catch (IntegrityConstraintViolationException $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }

    return $results ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ) :array {
    if (1 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "Database::transliterateDrupalFilters():\n@parameters",
        [
          '@parameters' => print_r($parameters, TRUE),
        ]
      );
    }
    $source_filters = [];
    $drupal_filters = [];
    foreach ($parameters as $parameter) {

      if (!empty($parameter['conjunction']) && !empty($parameter['conditions'])) {
        // Sub-condition.
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Database::transliterateDrupalFilters(): Handling sub-condition."
          );
        }

        $trans_param = $this->transliterateDrupalFilters(
          $parameter['conditions'],
          $context
        );
        // If at least one sub-condition requires post-filtering, all
        // sub-condition will require post-filtering.
        if (!empty($trans_param['drupal'])) {
          $drupal_filters[] = $parameter;
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug(
              "Database::transliterateDrupalFilters(): Sub-condition ("
              . $parameter['conjunction']
              . ") added to Drupal filters.",
            );
          }
        }
        else {
          $source_filters[] = [
            'conjunction' => $parameter['conjunction'],
            'conditions' => $trans_param['source'],
          ];
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug(
              "Database::transliterateDrupalFilters(): Sub-condition ("
              . $parameter['conjunction']
              . ") added to source filters.",
            );
          }
        }
        continue;
      }

      if (!isset($parameter['field'])) {
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Database::transliterateDrupalFilters(): Missing field name. Filter set to Drupal side."
          );
        }
        $drupal_filters[] = $parameter;
        continue;
      }

      $field_mapper = $this->externalEntityType->getFieldMapper($parameter['field']);
      $source_field = NULL;
      if ($field_mapper) {
        $source_field = $field_mapper->getMappedSourceFieldName();
      }
      if (!isset($source_field)) {
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Database::transliterateDrupalFilters(): Non-reversible field mapping ("
            . $parameter['field']
            . ") filtered on Drupal side."
          );
        }
        $drupal_filters[] = $parameter;
        continue;
      }

      $parameter['operator'] ??= '=';
      switch ($parameter['operator']) {
        case '=':
        case '<>':
        case '!=':
        case '>':
        case '>=':
        case '<':
        case '<=':
        case 'STARTS_WITH':
        case 'CONTAINS':
        case 'ENDS_WITH':
        case 'IN':
        case 'NOT IN':
        case 'IS NULL':
        case 'NOT EXISTS':
        case 'IS NOT NULL':
        case 'EXISTS':
        case 'BETWEEN':
        case 'NOT BETWEEN':
        case 'LIKE':
        case 'ILIKE':
        case 'NOT LIKE':
        case 'NOT ILIKE':
        case 'REGEXP':
        case 'NOT REGEXP':
          // These operators will be managed later by
          // self::getSqlFiltersAndValues().
          break;

        default:
          if (1 <= $this->getDebugLevel()) {
            $this->logger->debug(
              "Database::transliterateDrupalFilters(): Unsupported operator '"
              . $parameter['operator']
              . "' for field '"
              . $parameter['field']
              . "' will be filtered on Drupal side."
            );
          }
          $drupal_filters[] = $parameter;
          continue 2;
      }

      if (1 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "Database::transliterateDrupalFilters(): Regular operator '"
          . $parameter['operator']
          . "' for source field '"
          . $source_field
          . "' ("
          . $parameter['field']
          . ") will be translated to SQL and filtered on source side."
        );
      }
      $parameter['field'] = $source_field;
      $source_filters[] = $parameter;
    }

    return $this->transliterateDrupalFiltersAlter(
      ['source' => $source_filters, 'drupal' => $drupal_filters],
      $parameters,
      $context
    );
  }

  /**
   * Translates the string operators to SQL equivalents.
   *
   * @param array $parameters
   *   An array of condition arrays.
   * @param string $conjunction
   *   Logical conjuction to use to join conditions.
   *
   * @return array
   *   Returns an array of 2 values: the first one is a SQL condition statement
   *   and the second value is an array of replacement values for the
   *   conditions.
   *   The returned SQL condition statement is not preceeded nor ended by a
   *   logical conjunction and might be empty if there are no supported
   *   conditions.
   *
   * @note this only works for fields whose placeholder names directly match SQL
   * column names or have a correct filter mapping set.
   */
  public function getSqlFiltersAndValues(
    array $parameters,
    string $conjunction = 'AND',
  ) :array {
    static $param_placeholder_index = 1;

    $filters = '';
    $fvalues = [];
    if (empty($this->xConnection)) {
      // No connection available.
      $this->messenger->addError(
        $this->t(
          'Unable to translate Drupal operators to SQL: no connection available.'
        )
      );
      $this->logger->warning(
        'getSqlFiltersAndValues failed: no connection available.'
      );
      return [$filters, $fvalues];
    }

    $conditions = [];
    // Dummy table in order to get a SelectInterface object.
    $select = $this->xConnection->select('-DUMMY-');

    foreach ($parameters as &$parameter) {
      // Manage sub-conditions.
      if (!empty($parameter['conjunction'])
        && !empty($parameter['conditions'])
      ) {
        [$sub_filters, $sub_fvalues] = $this->getSqlFiltersAndValues(
          $parameter['conditions'],
          $parameter['conjunction']
        );
        if (!empty($sub_filters)) {
          $conditions[] = '(' . $sub_filters . ')';
          $fvalues += $sub_fvalues;
        }
        continue;
      }
      if (!empty($parameter['field'])) {
        // Replace source field name by its associated SQL column name or
        // expression in case of alias.
        $parameter['field'] = $this->getFilterMapping($parameter['field']);

        // Translate non-SQL conditions such as "CONTAINS".
        Condition::translateCondition($parameter, $select, FALSE);

        // Prepare values.
        $param_placeholder = ':xnttsqltparam' . $param_placeholder_index++;
        if (is_array($parameter['value'])) {
          $param_placeholder .= '[]';
        }
        $fvalues[$param_placeholder] = $parameter['value'];

        // Build SQL condition strings.
        if ($this->xConnection->databaseType() === 'pgsql') {
          // Deal with capitalization in PostgreSQL.
          if ($parameter['operator'] === 'LIKE') {
            $parameter['operator'] = 'ILIKE';
          }
          elseif ($parameter['operator'] === 'NOT LIKE') {
            $parameter['operator'] = 'NOT ILIKE';
          }
        }
        // Deal with 'IN'.
        if ($parameter['operator'] == 'IN') {
          $param_placeholder = '(' . $param_placeholder . ')';
        }
        $conditions[] = $parameter['field'] . ' ' . $parameter['operator'] . ' ' . $param_placeholder;
      }
    }

    $filters = empty($conditions) ? '' : implode(" $conjunction ", $conditions);

    // Reset $param_placeholder_index when it reaches a high value.
    if ($param_placeholder_index > 2147000000) {
      $param_placeholder_index = 1;
    }

    return [$filters, $fvalues];
  }

  /**
   * Return filter mappings.
   *
   * @return array
   *   Filter mapping. Keys are column names and values are corresponding SQL
   *   expressions.
   */
  protected function getFilterMappings() :array {
    if (empty($this->filterMappings)) {
      $this->filterMappings = [];
      foreach ($this->configuration['filter_mappings'] ?? [] as $filter_mapping) {
        if (!empty($filter_mapping['alias'])
          && !empty($filter_mapping['expression'])
        ) {
          $this->filterMappings[$filter_mapping['alias']] =
            $filter_mapping['expression'];
        }
      }
    }
    return $this->filterMappings;
  }

  /**
   * Get SQL column name or expression associated with a source field name.
   *
   * @param string $source_field
   *   Source field name.
   *
   * @return string
   *   SQL column name or expression.
   */
  protected function getFilterMapping(string $source_field) :string {
    // Get mapping from filter configuration.
    $field_mapping = $this->getFilterMappings()[$source_field] ?? '';

    if (empty($field_mapping)) {
      // Not set, check if the field name corresponds to an alias and not a real
      // column. Try to get it from the 'read' SQL query.
      // This regex is not perfect but may help in many cases.
      $regex = '/'
        // A column name should be preceeded by a 'SELECT' clause or a coma.
        . '(?:SELECT\s+(?:(?:ALL|DISTINCT|DISTINCTROW|HIGH_PRIORITY|STRAIGHT_JOIN|SQL_SMALL_RESULT|SQL_BIG_RESULT|SQL_BUFFER_RESULT|SQL_NO_CACHE|SQL_CALC_FOUND_ROWS)\s+)*|,\s*)'
        // Then comes the possibly qualified column name or expression we want
        // to capture.
        . '('
        // It can be a possibly qualified and non-quoted column name possibly
        // with a cast.
        . '(?:\w+\.)?\w+(?:::\s*\w+(?:\(\s*\d+\s*\)|\[\])?)?'
        // Or it could also be quoted (both with the qualifier) possibly with
        // cast.
        . '|(?:BINARY\s+)?(?:"[^\"]+"\.)?"[^\"]+"(?:::\s*\w+(?:\(\s*\d+\s*\)|\[\])?)?'
        . '|(?:BINARY\s+)?(?:`[^`]+`\.)?`[^\`]+`(?:::\s*\w+(?:\(\s*\d+\s*\)|\[\])?)?'
        // Or if it starts by an opening parenthesis, we want to get all through
        // the matching closing one (sub-SELECT clause for instance).
        . '|\((?:[^)(]+|(?R))*+\)'
        // Or it can be a function call like a CAST.
        . '|\w+\s*\((?:[^)(]+|(?R))*+\)'
        // Then we want to match the alias name we know either quoted or not and
        // prefixed by 'AS' or not.
        . ')\s+(?:AS\s+|)(?:"\Q'
        . $source_field
        . '\E"|\Q'
        . $source_field
        . '\E(?:\W|$))/is';
      $query =
        $this->getQueries('list', ['caller' => 'getFilterMapping'])[0]
        ?? '';
      if (preg_match($regex, $query, $match)) {
        // We got an expression, try to use it instead of the alias.
        $field_mapping = $match[1];
      }
      // If still empty, maybe there is no alias.
      if (empty($field_mapping)) {
        $field_mapping = $source_field;
      }
    }
    return $field_mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuerySource(array $parameters = []) :int {

    if (empty($this->xConnection)) {
      // No connection available.
      $this->messenger->addError('Count query failed: no connection available.');
      return 0;
    }

    $count = 0;
    try {
      [$filters, $fvalues] = $this->getSqlFiltersAndValues($parameters);
      $query = rtrim(
        $this->getQueries(
          'count',
          ['caller' => 'countQuerySource', 'filters' => $filters]
        )[0] ?? '',
        ';'
      );
      $qargs = $this->getPlaceholders($query);
      if (1 == $this->getDebugLevel()) {
        $this->logger->debug(
          "COUNT SQL Query:\n@sql",
          ['@sql' => $query]
        );
      }
      elseif ($this->getDebugLevel()) {
        $this->logger->debug(
          "COUNT SQL Query:\n@sql\n\nArguments:\n@args",
          ['@sql' => $query, '@args' => $this->dumpArguments($fvalues + $qargs)]
        );
      }
      $result = $this->xConnection
        ->query($query, $fvalues + $qargs)
        ->fetch();
      if (isset($result->count)) {
        $count = $result->count;
      }
      else {
        $this->messenger->addWarning(
          $this->t(
            'Unable to get entity count: the SQL query does not return a "count" column. Does the count column use a "count" alias (ie. \'SELECT count(1) as "count" ...\')?'
          )
        );
        $this->logger->error(
          'Unable to get entity count: the SQL query does not return a "count" column. Does the count column use a "count" alias (ie. \'SELECT count(1) as "count" ...\')?'
        );
        $count = 0;
      }
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }
    catch (IntegrityConstraintViolationException $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }

    if (!isset($count)) {
      $this->messenger->addWarning(
        $this->t('Unable to get entity count.')
      );
      $this->logger->error('Unable to get entity count.');
      $count = 0;
    }

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function runQueries(
    array $queries,
    array $entity_data = [],
    array $additional_replacements = [],
  ) :array {

    if (empty($this->xConnection)) {
      // No connection available.
      $this->messenger->addError('Failed to run queries: no connection available.');
      return [];
    }

    // We only support all queries in one string.
    $queries = $queries[0];
    // Start a new transaction.
    $transaction = $this->xConnection->startTransaction();
    $return = [];
    try {
      // @todo better parse ';' as it can be used in query strings.
      $sql_queries = explode(';', $queries);
      // Loop on queries.
      foreach ($sql_queries as $sql_query) {
        $sql_query = trim($sql_query);
        if (empty($sql_query)) {
          continue;
        }
        $values = [];
        // Get the list of field values needed.
        preg_match_all(
          '/\W:([a-z_]\w*(?:\[\])?)/i',
          $sql_query,
          $placeholders,
          PREG_PATTERN_ORDER
        );
        $placeholders = array_flip($placeholders[1]);
        foreach ($placeholders as $placeholder => $waste) {
          // Check for array placeholder and array values.
          if ('[]' == substr($placeholder, -2)) {
            $placeholder_name = substr($placeholder, 0, -2);
            if (array_key_exists($placeholder_name, $entity_data)) {
              if (is_array($entity_data[$placeholder_name])) {
                $values[":$placeholder"] = $entity_data[$placeholder_name];
              }
              else {
                $values[":$placeholder"] = [$entity_data[$placeholder_name]];
              }
            }
          }
          elseif (array_key_exists($placeholder, $entity_data)) {
            if (is_array($entity_data[$placeholder])) {
              $values[":$placeholder"] = current($entity_data[$placeholder]);
              $this->logger->warning('The entity field placeholder ":' . $placeholder . '" maps for an array of values while it does not end with "[]"');
            }
            else {
              $values[":$placeholder"] = $entity_data[$placeholder];
            }
          }
        }
        // Get placeholders.
        $qargs = $this->getPlaceholders($sql_query);
        if (1 == $this->getDebugLevel()) {
          $this->logger->debug(
            "SQL Query:\n@sql",
            ['@sql' => $sql_query]
          );
        }
        elseif ($this->getDebugLevel()) {
          $this->logger->debug(
            "SQL Query:\n@sql\n\nArguments:\n@args",
            ['@sql' => $sql_query, '@args' => $this->dumpArguments($values + $qargs)]
          );
        }
        $result = $this->xConnection->query(
          $sql_query,
          $values + $qargs
        )->fetch(\PDO::FETCH_ASSOC);

        if (is_array($result)) {
          foreach ($result as $key => $value) {
            if (0 === strpos($key, 'array_')) {
              // Removes leading '{' and trailing '}' and explode string on ','.
              $return[$key] = $this->expandDatabaseArray($value);
            }
            elseif (0 === strpos($key, 'json_')) {
              $return[$key] = $this->expandDatabaseJson($value);
            }
            else {
              $return[$key] = $value;
            }
          }
        }
      }
      // Make sure we return a non-empty array on success.
      if (empty($return)) {
        $return = [TRUE];
      }
    }
    catch (IntegrityConstraintViolationException $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->messenger->addError('Query failed: ' . $e->getMessage());
      $this->logger->error($e);
      $transaction->rollBack();
    }
    catch (\Throwable $e) {
      $this->messenger->addError('An unexpected error occurred: ' . $e->getMessage());
      $this->logger->error($e);
    }

    // Commit.
    unset($transaction);
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueries(string $query_type, array $context = []) :array {
    $queries = parent::getQueries($query_type, $context);

    if ((('list' == $query_type) || ('count' == $query_type))
      && empty($context['form'])
      && !empty($queries[0])
    ) {
      $query = $queries[0];
      // Check if we have filters.
      if (empty($context['filters'])) {
        // No filters, remove any empty 'WHERE' structure.
        $query = preg_replace('/\sWHERE\s+:filters\s*$/is', '', $query);
        // Or remove filters in a condition set.
        $query = preg_replace('/\s:filters\s+(?:AND|OR)\s/is', ' ', $query);
        // Or just remove any remaining filters.
        $query = preg_replace('/\s:filters(?:\s|$)/is', ' ', $query);
      }
      else {
        // Check if the query contains the ":filters" placeholder.
        if (FALSE === strpos($query, ':filters')) {
          // No ":filters", try to append.
          if (preg_match('/\sWHERE\s/is', $query)) {
            $query .= ' :filters';
          }
          else {
            $query .= ' WHERE :filters';
          }
        }
        if (preg_match('/(?:\sWHERE|\()\s+:filters(?:\s|$)/is', $query)) {
          // Filters are right after WHERE or opening '(', no junction.
          // Note: if there is something after, it's the under the
          // responsibility of the query provider to handle following condition
          // operator if needed.
          $query = str_replace(':filters', $context['filters'], $query);
        }
        else {
          // Filters are after other conditions, join with an 'AND'.
          $query = str_replace(':filters', ' AND ' . $context['filters'], $query);
        }
      }
      $queries[0] = $query;
    }

    return $queries;
  }

  /**
   * Initializes placeholder replacement.
   *
   * @return array
   *   Returns the list of placeholders keyed by external entity type, then by
   *   placeholder name with their associated values.
   */
  public function initPlaceholders() :array {

    // Get cache.
    $cid = 'xnttsql:placeholders';
    $placeholders = [];
    if ($cache = $this->cache->get($cid)) {
      $placeholders = $cache->data;
    }

    // Checks if the external entity type's mapping has already been processed.
    if ($this->externalEntityType) {
      $entity_type = $this->externalEntityType->getDerivedEntityTypeId();
    }
    else {
      $entity_type = '';
    }

    // Load placeholder replacement values.
    if ($this->xConnection) {
      $placeholders_issues = FALSE;
      $placeholders[$entity_type] = [];
      // Process the list of placeholders from config.
      foreach ($this->configuration['placeholders'] as $ph_def) {
        if (empty($ph_def) || empty($ph_def['placeholder'])) {
          continue;
        }
        // Skip placeholders not starting with ':'.
        if (!preg_match('/^:[a-z]\w*(?:\[\])?$/i', $ph_def['placeholder'])) {
          $placeholders_issues = TRUE;
          $this->logger->warning('Ignoring invalid placeholder name "' . $ph_def['placeholder'] . '"');
          continue;
        }
        // Check for query or constant values.
        if (!empty($ph_def['query'])) {
          // Process placeholder query.
          try {
            if ($this->getDebugLevel()) {
              $this->logger->debug(
                "Placeholder SQL Query:\n@sql",
                ['@sql' => $ph_def['query']]
              );
            }
            $results = $this->xConnection->query(
              $ph_def['query']
            )->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($results)) {
              // Save placeholder replacement value.
              if ('[]' === substr($ph_def['placeholder'], -2)) {
                $placeholders[$entity_type][$ph_def['placeholder']] = array_map(
                  function ($x) {
                    return $x[array_key_first($x)];
                  },
                  $results
                );
              }
              else {
                $placeholders[$entity_type][$ph_def['placeholder']] =
                  $results[0][array_key_first($results[0])];
              }
            }
            else {
              $this->messenger->addWarning(
                'Failed to get value(s) for placeholder "' . $ph_def['placeholder'] . '". Query "' . $ph_def['query'] . '" returned no value.'
              );
            }
          }
          catch (DatabaseExceptionWrapper $e) {
            $this->messenger->addWarning(
              'Failed to get value(s) for placeholder "' . $ph_def['placeholder'] . '" (database error). '
              . $e->getMessage()
            );
          }
          catch (IntegrityConstraintViolationException $e) {
            $this->messenger->addWarning(
              'Failed to get value(s) for placeholder "' . $ph_def['placeholder'] . '" (constraint error). '
              . $e->getMessage()
            );
          }
          catch (\Throwable $e) {
            $this->messenger->addWarning(
              'Failed to get value(s) for placeholder "' . $ph_def['placeholder'] . '". '
              . $e->getMessage()
            );
          }
        }
        elseif (isset($ph_def['constant'])) {
          // Save placeholder replacement value.
          if ('[]' === substr($ph_def['placeholder'], -2)) {
            if (is_array($ph_def['constant'])) {
              $placeholders[$entity_type][$ph_def['placeholder']] =
                $ph_def['constant'];
            }
            elseif (is_string($ph_def['constant'])) {
              $placeholders[$entity_type][$ph_def['placeholder']] =
                preg_split(
                  '/"\s*,\s*(?=(?:[^\"]*\"[^\"]*\")*[^\"]*$)"/',
                  $ph_def['constant']
                );
            }
            else {
              $placeholders[$entity_type][$ph_def['placeholder']] =
                [$ph_def['constant']];
            }
          }
          else {
            $placeholders[$entity_type][$ph_def['placeholder']] =
              $ph_def['constant'];
          }
        }
        else {
          $placeholders_issues = TRUE;
          $this->logger->warning('Ignoring invalid placeholder settings for placeholder "' . $ph_def['placeholder'] . '"');
        }
      }
      if ($placeholders_issues) {
        $this->messenger->addWarning(
          'A couple of placeholder names were invalid and ignored. Please check the external entity configuration and see logs for details.'
        );
      }
      $this->cache->set($cid, $placeholders);
    }

    return $this->placeholders = $placeholders;
  }

  /**
   * Sets the value of a placeholder.
   *
   * @param string $name
   *   Placeholder name including its ':' prefix (and possible trailing '[]').
   * @param mixed $value
   *   Value to use.
   *
   * @return self
   *   Returns current instance.
   */
  public function setPlaceholder(string $name, $value) :self {
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
   * Returns an array of placeholders for current entity type.
   *
   * @param ?string $query
   *   An SQL query containing placeholders of the form ':placeholdername'.
   *   If not empty, only placeholders matching the query will be returned.
   *   If empty, all placeholders are returned.
   *
   * @return array
   *   The array of placeholders as placeholder_name => placeholder_value.
   *   Note: placeholder names start with ':' and may end with '[]' for arrays
   *   and placeholder values may be strings or arrays.
   */
  public function getPlaceholders(?string $query = NULL) :array {
    $entity_type = '';
    if ($this->externalEntityType) {
      $entity_type = $this->externalEntityType->getDerivedEntityTypeId();
    }
    // Init Placeholders if missing and get them.
    $this->placeholders[$entity_type] = $this->placeholders[$entity_type] ?? [];

    $placeholders = [];
    if (!empty($query)) {
      foreach ($this->placeholders[$entity_type] as $placeholder => $ph_value) {
        if (preg_match('/\W\Q' . $placeholder . '\E(?:\W|$)/', $query)) {
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
   * Dumps an array of query arguments for debugging.
   *
   * @param array $arguments
   *   Arguments.
   *
   * @return string
   *   The string containing the dump.
   */
  public function dumpArguments(array $arguments) :string {
    $dump = '';
    foreach ($arguments as $name => $value) {
      if (is_scalar($value)) {
        $dump = "$name (" . gettype($value) . ") = $value\n";
      }
      elseif (is_array($value)) {
        if (!empty($value)) {
          $type = gettype(current($value));
        }
        else {
          $type = 'empty array';
        }
        $dump = "$name ({$type}[]) = " . print_r($value, TRUE) . "\n";
      }
      else {
        $dump = "$name (unsupported) = " . print_r($value, TRUE) . "\n";
      }
    }
    return $dump;
  }

}
