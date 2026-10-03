<?php

namespace Drupal\xntt_views\Plugin\views\query;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\external_entities\Entity\Query\External\ExternalQueryInterface;
use Drupal\views\Attribute\ViewsQuery;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Views query class for External Entities.
 *
 * @ViewsQuery(
 *   id = "xntt_query",
 *   title = @Translation("External Entities Query"),
 *   help = @Translation("The query will be generated and run on external entities.")
 * )
 */
#[ViewsQuery(
  id: 'xntt_query',
  title: new TranslatableMarkup('External Entities Query'),
  help: new TranslatableMarkup('The query will be generated and run on external entities.'),
)]
class ExternalEntityViewsQuery extends QueryPluginBase {

  /**
   * The External Entity type ID.
   *
   * @var string
   */
  protected $xnttTypeId;

  /**
   * The external entity query object.
   *
   * @var \Drupal\external_entities\Entity\Query\External\Query
   */
  protected $query;

  /**
   * An array of sections of the WHERE query.
   *
   * Each section is in itself an array of pieces and a flag as to whether or
   * not it should be AND or OR.
   *
   * @var array
   */
  protected $where = [];

  /**
   * Array of "order by" clauses.
   *
   * @var array
   */
  public $orderby = [];

  /**
   * Associative array of field names.
   *
   * @var array
   */
  public $fields = [];

  /**
   * Views data corresponding to the current external entity type.
   *
   * @var array
   */
  public $viewsData = [];

  /**
   * Reference mappings registered by xntt_reference relationship handlers.
   *
   * Keyed by the relationship machine id in the view; each value holds the
   * source entity reference field name, the target external entity type id
   * and the "required" flag.
   *
   * @var array[]
   *
   * @see \Drupal\xntt_views\Plugin\views\relationship\ExternalEntityReference
   */
  protected array $relationshipMappings = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Counters for unique placeholder names, keyed by placeholder base.
   *
   * @var array
   */
  protected $placeholders = [];

  /**
   * The logger channel of the module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('xntt_views'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->viewsData = Views::viewsData()->get($view->storage->get('base_table'));
    $this->xnttTypeId =
      $this->viewsData['table']['base']['xntt']
      ?? preg_replace('/^xntt_views_/', '', $view->storage->get('base_table'));

    $xntt_storage = $this->entityTypeManager->getStorage($this->xnttTypeId);
    $this->query = $xntt_storage->getQuery()->accessCheck(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function ensureTable($table, $relationship = NULL) {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $alias = '', $params = []) {
    $this->fields[$field] = [
      'field' => $field,
      'table' => '',
      'alias' => $field,
    ];
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    if ('formula' === $operator) {
      // Formula-based core handlers pass an SQL snippet as field and the
      // placeholder values as value. Try to convert simple snippets to a
      // regular condition instead of corrupting the query with a bogus
      // field name.
      $this->addWhereExpression($group, $field, is_array($value) ? $value : []);
      return;
    }
    $field = ltrim($field, '.');
    $entity_field = $this->viewsData[$field]['entity field'] ?? $field;
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }
    if (str_ends_with($entity_field, '.%delta')) {
      // Delta filter does not work as a count but as an index number.
      // The user interface let think it works as a count so we need to
      // decrease the value by one to get an equivalent index value to
      // filter on.
      --$value;
    }
    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }
    // Note: operators are adapted later by ExternalEntitiesSubscriber.
    $this->where[$group]['conditions'][] = [
      'field' => $entity_field,
      'value' => $value,
      'operator' => $operator,
    ];
  }

  /**
   * Generates a unique placeholder name.
   *
   * This method is not part of the views query plugin API but core views
   * handlers assume its availability on every query plugin as it is provided
   * by the default SQL implementation: HandlerBase::placeholder() relies on
   * it, which makes core argument handlers (contextual filters) fatal on
   * query plugins not providing it.
   *
   * @param string $base
   *   The base name for the placeholder.
   *
   * @return string
   *   A unique placeholder name (e.g. ":views", ":views1", ...).
   *
   * @see \Drupal\views\Plugin\views\query\Sql::placeholder()
   * @see \Drupal\views\Plugin\views\HandlerBase::placeholder()
   * @see https://www.drupal.org/project/external_entities/issues/3590257
   */
  public function placeholder($base = 'views') {
    if (!isset($this->placeholders[$base])) {
      $this->placeholders[$base] = 0;
      return ':' . $base;
    }
    return ':' . $base . ++$this->placeholders[$base];
  }

  /**
   * Adds a condition expressed as an SQL snippet to the query.
   *
   * This method is not part of the views query plugin API but core views
   * handlers assume its availability on every query plugin as it is provided
   * by the default SQL implementation. Core argument handlers (contextual
   * filters) call it with simple snippets such as "alias.field = :views" or
   * "alias.field IN(:views[])", optionally followed by
   * " OR alias.field IS NULL" when their "Exclude" option is enabled.
   *
   * Since external entity queries are not SQL-based, this implementation
   * parses these known simple snippet forms and converts them to regular
   * field conditions. Unsupported (complex) SQL expressions are skipped with
   * a warning instead of triggering a fatal error.
   *
   * @param int $group
   *   The WHERE group to add the condition to.
   * @param string $snippet
   *   The SQL snippet.
   * @param array $args
   *   An associative array of placeholder name => value.
   *
   * @see \Drupal\views\Plugin\views\query\Sql::addWhereExpression()
   * @see https://www.drupal.org/project/external_entities/issues/3590257
   */
  public function addWhereExpression($group, $snippet, $args = []) {
    $expression = trim($snippet);

    // Detect the " OR <field> IS NULL" suffix appended by core argument
    // handlers when their "Exclude" option is enabled.
    $null_field = NULL;
    if (preg_match('/^(.*?)\s+OR\s+([\w.%]+)\s+IS\s+NULL$/i', $expression, $matches)) {
      $expression = trim($matches[1]);
      $null_field = $matches[2];
    }

    if (preg_match('/^([\w.%]+)\s*(=|!=|<>|<=|>=|<|>|(?:NOT\s+)?LIKE|(?:NOT\s+)?IN)\s*\(?\s*(:[\w\[\]]+)\s*\)?$/i', $expression, $matches)
      && array_key_exists($matches[3], $args)
    ) {
      $field = $matches[1];
      $operator = strtoupper(preg_replace('/\s+/', ' ', $matches[2]));
      if ('<>' === $operator) {
        $operator = '!=';
      }
      $value = $args[$matches[3]];
      if (NULL === $null_field) {
        $this->addWhere($group, $field, $value, $operator);
      }
      else {
        // "<condition> OR <field> IS NULL" needs a dedicated OR group.
        $or_group = $this->setWhereGroup('OR');
        $this->addWhere($or_group, $field, $value, $operator);
        $this->addWhere($or_group, $null_field, NULL, 'IS NULL');
      }
      return;
    }

    // The SQL expression is too complex to be mapped to an external entity
    // query condition. Skip it with a warning rather than failing the whole
    // view.
    $this->logger->warning(
      'The SQL expression %snippet of the view %view_name could not be converted into an external entity query condition and has been ignored.',
      [
        '%snippet' => $snippet,
        '%view_name' => $this->view?->id() ?? 'n/a',
      ]
    );
    $this->messenger()->addWarning(
      $this->t(
        'A condition of the view %view_name is not supported by external entity queries and has been ignored. Check the logs for details.',
        ['%view_name' => $this->view?->id() ?? 'n/a']
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', $params = []) {
    $entity_field = $this->viewsData[$field]['entity field'] ?? $field;
    $this->orderby[] = [
      'field' => $entity_field ?? '' ?: $alias ?? '',
      'direction' => strtoupper($order),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(ViewExecutable $view) {
    $this->view = $view;
    // Initialize the pager and let it modify the query to add limits. This has
    // to be done even for aborted queries since it might otherwise lead to a
    // fatal error when Views tries to access $view->pager.
    $view->initPager();
    $view->pager->query();
  }

  /**
   * Registers an external entity reference to resolve as a relationship.
   *
   * Called by the xntt_reference relationship handler instead of adding an
   * SQL join (referenced external entities live in a remote source, not in
   * a database table).
   *
   * @param string $relationship_id
   *   The machine id of the relationship in the view.
   * @param string $field_name
   *   The entity reference field name on the base external entity.
   * @param string $target_type_id
   *   The referenced (derived) external entity type id.
   * @param bool $required
   *   When TRUE, rows without any resolvable referenced entity are removed
   *   from the result (INNER JOIN semantics); otherwise they are kept
   *   (LEFT JOIN semantics).
   */
  public function addRelationshipMapping(string $relationship_id, string $field_name, string $target_type_id, bool $required = FALSE): void {
    $this->relationshipMappings[$relationship_id] = [
      'field' => $field_name,
      'target_type' => $target_type_id,
      'required' => $required,
    ];
  }

  /**
   * Resolves registered relationships on the current result page.
   *
   * For each registered relationship, the referenced external entities of
   * the whole page are loaded in one batch storage call and exposed on the
   * result rows via $row->_relationship_entities, the standard Views
   * mechanism used by HandlerBase::getEntity(). Multi-valued references
   * multiply rows like an SQL join would.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   */
  protected function attachRelationshipEntities(ViewExecutable $view): void {
    if (empty($this->relationshipMappings) || empty($view->result)) {
      return;
    }

    foreach ($this->relationshipMappings as $relationship_id => $mapping) {
      // Collect the referenced ids of the whole page, per row.
      $row_target_ids = [];
      $all_ids = [];
      foreach ($view->result as $row_index => $row) {
        $entity = $row->_entity ?? NULL;
        if (!$entity || !$entity->hasField($mapping['field'])) {
          continue;
        }
        $ids = array_column($entity->get($mapping['field'])->getValue(), 'target_id');
        $ids = array_values(array_filter($ids, static function ($id) {
          return $id !== NULL && $id !== '';
        }));
        $row_target_ids[$row_index] = $ids;
        $all_ids = array_merge($all_ids, $ids);
      }

      // One batch load per page per relationship.
      $related_entities = [];
      if (!empty($all_ids)) {
        $related_entities = $this->entityTypeManager
          ->getStorage($mapping['target_type'])
          ->loadMultiple(array_unique($all_ids));
      }

      $new_result = [];
      foreach ($view->result as $row_index => $row) {
        $row_entities = [];
        foreach ($row_target_ids[$row_index] ?? [] as $id) {
          if (isset($related_entities[$id])) {
            $row_entities[] = $related_entities[$id];
          }
        }

        if (empty($row_entities)) {
          if (empty($mapping['required'])) {
            $new_result[] = $row;
          }
          continue;
        }

        // First referenced entity on the original row, additional ones on
        // clones — mirroring SQL join row multiplication.
        $row->_relationship_entities[$relationship_id] = array_shift($row_entities);
        $new_result[] = $row;
        foreach ($row_entities as $row_entity) {
          $clone = clone $row;
          $clone->_relationship_entities = $row->_relationship_entities;
          $clone->_relationship_entities[$relationship_id] = $row_entity;
          $new_result[] = $clone;
        }
      }

      foreach ($new_result as $new_index => $row) {
        $row->index = $new_index;
      }
      $view->result = $new_result;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadEntities(&$results) {
    $ids = [];
    foreach ($results as $index => $result) {
      if (isset($result->xntt_entity_id) && $result->xntt_entity_id !== '') {
        $ids[$index] = $result->xntt_entity_id;
      }
    }

    if (empty($ids)) {
      return;
    }

    $entities = $this->entityTypeManager
      ->getStorage($this->xnttTypeId)
      ->loadMultiple(array_unique(array_values($ids)));

    foreach ($ids as $index => $id) {
      if (isset($entities[$id])) {
        $results[$index]->_entity = $entities[$id];
      }
      else {
        // Remove rows whose entities could not be reloaded, e.g. because
        // the external source no longer provides them. This prevents empty
        // rows from appearing in the rendered view.
        unset($results[$index]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    // Manage conditions.
    // We are not supporting "$this->having".
    if (isset($this->where)) {
      if ((1 == count($this->where))
          && ('AND' == reset($this->where)['type'] ?? 'AND')
      ) {
        // Case of a single AND group.
        $where = reset($this->where);
        foreach ($where['conditions'] as $condition) {
          $this->query->condition(
            $condition['field'],
            $condition['value'],
            $condition['operator']
          );
        }
      }
      else {
        // Manage complex conditions.
        foreach ($this->where as $where) {
          if ('OR' == $where['type'] ?? 'AND') {
            $group = $this->query->orConditionGroup();
          }
          else {
            $group = $this->query->andConditionGroup();
          }
          foreach ($where['conditions'] as $condition) {
            $group->condition(
              $condition['field'],
              $condition['value'],
              $condition['operator']
            );
          }
          $this->query->condition($group);
        }
      }
    }

    $xntt_storage = $this->entityTypeManager->getStorage($this->xnttTypeId);
    $view->pager->preExecute($this->query);
    // Views passes sometimes NULL and sometimes the integer 0 for "All" in a
    // pager. If set to 0 items, a string "0" is passed. Therefore, we unset
    // the limit if an empty value OTHER than a string "0" was passed.
    if (!$this->limit && $this->limit !== '0') {
      $this->limit = NULL;
    }
    if (isset($this->offset)) {
      $this->query->range($this->offset, $this->limit);
    }

    foreach ($this->orderby ?? [] as $order) {
      $this->query->sort($order['field'], $order['direction']);
    }

    $start = microtime(TRUE);
    $count_query = clone $this->query;
    $xntts = $this->query->execute();
    if (($this->query instanceof ExternalQueryInterface)
        && !empty($unhandled_filters = $this->query->getUnhandledFilters())
    ) {
      // Warn about unhandled filters.
      $this->logger->warning(
        'Some filters could not be handled by the external entity query for the view %view_name: %filters',
        [
          '%view_name' => $view->id() ?? 'n/a',
          '%filters' => print_r($unhandled_filters, TRUE),
        ]
      );
      $this->messenger()->addWarning(
        $this->t(
          'Some filters could not be handled by the external entity query for the view %view_name. Check the logs for details.',
          ['%view_name' => $view->id() ?? 'n/a']
        )
      );
    }
    $entities = $xntt_storage->loadMultiple(array_values($xntts));
    $view->execute_time = microtime(TRUE) - $start;

    $index = 0;
    foreach ($entities as $entity) {
      $row['index'] = $index++;
      $row['_entity'] = $entity;
      $row['xntt_entity_id'] = $entity->id();
      $fields = array_keys($this->fields ?? ['title' => 'title']);
      foreach ($fields as $field) {
        [$entity_field, $property] = array_pad(
          explode('.', $this->viewsData[$field]['entity field'] ?? $field, 2),
          2,
          NULL
        );
        if (empty($property)) {
          $row[$field] = $entity->get($entity_field);
        }
        elseif ('%delta' == $property) {
          $raw_value = $entity->get($entity_field)->count();
          $row[$field] = $raw_value;
        }
        else {
          // @todo Code not used as we don't allow to display properties. Maybe
          // we could find a way to allow property display as field?
          $raw_value = $entity->get($entity_field)->get(0)->get($property)->getValue();
          $row[$field] = $raw_value;
        }
      }
      $view->result[] = new ResultRow($row);
    }

    // Resolve external entity reference relationships for this page.
    $this->attachRelationshipEntities($view);

    // Let the pager decide whether it needs the extra row used to detect a
    // next page (for the mini pager) and trim it before rendering. Core Views
    // calls pager postExecute() after the query result is built; doing the same
    // here keeps the result rows aligned with the pager contract.
    $view->pager->postExecute($view->result);

    if ($view->pager->useCountQuery() || !empty($view->get_total_rows)) {
      $view->pager->total_items = $count_query->count()->execute();

      if (!empty($view->pager->options['offset'])) {
        $view->pager->total_items -= $view->pager->options['offset'];
      }
    }

    $view->pager->updatePageInfo();
    $view->total_rows = $view->pager->getTotalItems();

    // Fill info.
    $view->build_info = [
      'query' => $this->query,
      'count_query' => $count_query,
      'query_args' => [],
    ]
    + $view->build_info;
  }

}
