External Entities 3.1.0, 2026-09-22
----------------------------------------
- D12 compatibility.
- Breaking changes on class method prototypes (return types).

External Entities 3.0.0, 2026-09-22
----------------------------------------
- Fixed Views mini-pager extra-element issue.
- File storage client supports nested structures.
- Removed hard coded SQL range queries to improve compatibility with SQL Server.
- Fixed requests for single JSON:API entities.
- Improved automated tests.
- Fixed some external entities label issues.
- Added support for Views relationship.
- Refactoring on group aggregator.
- Fixed base path validation rules.
- Improved date time data processor.
- Fixed issues in lock system.
- Multiple other minor improvements and fixes.


External Entities 3.0.0 rc 2, 2026-04-15
----------------------------------------
- Multiple minor improvements and fixes.
- Improved Views pager info.

External Entities 3.0.0 rc 1, 2026-01-31
----------------------------------------
- Multiple fixes and improvements on views support.
- Improved multilingual support.
- Improved Drupal token support.
- Multiple fixes and improvements on file field mapper.
- Removed publishing status from external entities view display.
- Fixes field cardinality support (issue #3562888).
- Improved data aggregator join.
- Code refactoring.

External Entities 3.0.0 beta 7, 2025-12-18
------------------------------------------
- Fixed issue when trying to save changes on file field settings.
- Added new field events to allow extensions to manage how extenral entities
  handle fields.
- Added support to select which fields can be saved and in which order.
- Fixed an issue on extenal entity listing cache (cache setting was ignored).
- Fixed views support including translation support (xntt_views).
- Added pattern property mapper.
- Introduced a breaking change on data aggregators and storage clients to
  better support unhandled filtering. Now DataAggregatorInterface::query(),
  DataAggregatorInterface::countQuery(), StorageClientInterface::query(), and
  StorageClientInterface::countQuery() have an extra optional argument
  `$unhandled_filters` passed by reference to hold unhandled filters.
  Data aggregator and storage client plugins implementing those methods should
  be updated. Storage client not implementing/overriding those methods (ie.
  relying on the `StorageClientBase` implementations) do not need to be updated
  unless they are not supporting "start" and "length" query parameters. In that
  case, they should override the quey() method like this:
  ```
  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array {
    if (!empty($start) || !empty($length)) {
      $unhandled_filters['start'] = TRUE;
      $unhandled_filters['length'] = TRUE;
      return [];
    }
    return parent::query(
      $parameters,
      $sorts,
      $start,
      $length,
      $unhandled_filters
    );
  }
  ```
- Introduced a breaking change on ExternalEntity::getBaseDefinition() to add
  support for a new altering event.
- Improved REST client
- Updated documenttion
- Improved mapping data processor
- Fixed config schema issues
- Requires Drupal 11.2

External Entities 3.0.0 beta 6, 2025-09-30
-------------------------------------------
- Added conditional property mapper
- Added multiple-mapping property mapper
- Modified External Entity type lock system
- Major improvements on external file field mapper
- Deprecated vertical data aggregator "tranlsation" aggregation mode
  See https://www.drupal.org/project/external_entities/issues/3506455#comment-16195753
- Added multi-lingual support and external translation support
- Fixed issues on xntt_views
- Cleaned code structure for external_entities_pathauto sub-module
- Annotation without inline entity
- Fixes in config schema
- Fixed SQL database client issue (xntt creation, default connection)
- Fixed issue external file field mapper and xntt reference fields
- Fixed issue on numeric key mapping
- Fixed issues on date management

External Entities 3.0.0 beta 5, 2025-04-08
-------------------------------------------
- Fixed issues in external entity type config saving
- Fixed bad routing issue in test controller
- Improved new external  entity creation
- Code cleaning
- Multiple fixes on token support

External Entities 3.0.0 beta 4, 2024-12-12
-------------------------------------------
- Added support for token replacement in REST authentication
- Added translation support in vertical data aggregator
- Fixed issues on group data aggregator
- Added GitLab Continuous Integration config
- Fixed issues on FieldMapperBase
- Fixed issues on JSON:API client
- Increased REST endpoint URL length
- Fixed dynamic property warnings
- Fixed StorageClientBase::getSourceIdFieldName()
- Fixed and improved file storage client base

External Entities 3.0.0 beta 3, 2024-12-12
-------------------------------------------
- Fixed tests
- Fixed group aggregator/horizontal aggregator (entity ranges)
- Fixed xntt_views for language management errors related to DBtables

External Entities 3.0.0 beta 2, 2024-11-25
-------------------------------------------
- Improved documentation
- Multiple fixes in config schemas
- External entity list link added to Drupal Config page
- Added examples for JSON:API storage client
- Fixed multiple redirection issues on external entity type form saving
- Changed how queries are tracked for query limitations
- Query limitation is provided by a trait rather than RestClient
- Allowed to alter sorts through event subscriber
- Changed return type declaration of ExternalEntityType::getLogger() to
  LoggerInterface
- Allowed automatic field mapping by storage clients
- Added horizontal and vertical data aggregators
- Drupal 11 compatibility

External Entities 3.0.0 beta 1, 2024-09-13
-------------------------------------------
- Fixed multiple issues on file client base
- Improved Drupal-side filtering and limitations
- Removed invalid warning when creating a new xntt type
- Fixed missing dependencies
- Compatible storage client configs are transfered when switching storage
  clients or data aggregators
- Added tests for data processors
- Improved SQL database filter mapping
- Improved transliterate event
- Added xntt_views as experimental companion module
- Refactoring in form elements

External Entities 3.0.0 alpha 6, 2024-08-23
-------------------------------------------
- Beta 1 candidate
- Fixes multiple issues of External Entities v2
- Introduces multilingual support
- Added support for programmatically created external entity types
- Improved support for complex entity queries (sub-queries and "OR")
- Added native support for file and image field types (companion module)
- Added new base classes for storage clients to support files and query
  languages
- Improved user interface (xntt type list link, save without redirection,
  mapping interface for new external entity types)
- Improved support for data saving back to source storage (keep non-mapped
  values, allows reverse data processing)
- Introduced the data aggregator plugin to mix storage sources
- Replaced field mapping based on a single field mapper plugin for all the
  Drupal fields and their properties by 3 plugins: a new field mapper plugin
  that can be selected on a Drupal field basis, a property mapper plugin that
  can be selected on a field property basis and a data processor plugin that can
  be used to process source field data before its use (data processor can be
  chained on each property).
- Added debug features for non-developers (using logs).
- Improved test coverage
