# External Entities Development Notes

This file holds technical information on how the External Entities module is
architectured, how to us it programmatically and how to extend it through
plugins (ie. how to create plugins).


## Table of contents

- Glossary
- Architecture
- Code notes
- v2 to v3 changes
- v2 to v3 plugin upgrade
- v3.0 to v3.1 plugin upgrade (Drupal 12)
- Creating a storage client plugin
- Creating a field mapper plugin
- Creating a property mapper plugin
- Creating a data processor plugin
- Creating a data aggregator plugin
- Creating external entity types programmatically


## Glossary

Here is a set of "tentative" definitions for terms used in External Entities:
- Data aggregator: a plugin that handles common entity operations such has
  querying, loading, creating/saving, and deleting and manage a set of storage
  clients on the backend to perform those operations.
- Data processor: a plugin that gets a set of values as input and can apply
  altering operations on each value of that set to produce a new set of altered
  values that would fulfill a given use case. It may (or may not) be also able
  to revert the altering process to save values in their original format for
  instance.
- Entity: a Drupal entity (in a generic sense).
- External entity type: definition of an external entity with it fields, how
  they are mapped, its data sources (data aggregator, storage clients) and other
  settings such as if there are annotations and how caching is managed.
- External entity (instance): a Drupal entity which values are not stored into
  the Drupal database but rather in a external source (ie. "external" to Drupal
  but which could reside on the same server).
- Field: in a Drupal context, a Drupal field is an object that holds one or more
  property in a structured way to attach them to an entity. In a "source"
  context, it's just the key of an associative data array provided by a storage
  client.
- Field mapper: a plugin that provides a used interface to map a given field
  type to source data. The plugin usualy delegates the field property mapping to
  property mapper plugins but it may also completly handle the mapping by
  itself without property mappers.
- Original data: it is an associative array containing the original values
  provided by the "source" storage clients (after aggregation) and hold by the
  external entity.
- Property mapper: a plugin that can map source values to a given value (ie.
  a field property). A property mapper often uses a mapping expression provided
  by the user to extract the piece of data needed from the source values, but
  the "constant" property mapper is an example of exception to that rule.
  Property mappers often use a set of data processor to alter source values but
  it is possible, for a property mapper, to alter values by itself and not use
  data processors (ex.: xnttstrjp).
- Raw data: can be used to refer to original/source data as an associative
  array.
- Source: external data provider. Service or system that is queried and returns
  the values used to fill external entities.
- Storage client: a plugin that queries a source to get external data and that
  can also be used to send altered data to its source for saving. Common storage
  clients get their data from a single source which can be a REST service, a
  database, a file or a set of files.
- XNTT: it is a shortcut for "eXternal eNTiTy".


## Architecture

### Typical data stream
```
+----------------------+
| External storages    | 1  1 +----------------+ 1..* 1 +-----------------+
+======================+ <--> | Storage client | <----> | Data Aggregator |
| REST service,        |      +----------------+        +-----------------+
| files, databases,... |                                         ^ 1
+----------------------+                                         |
                                                                 v 1
                             +--------------+ *..0  1 +-----------------------+
                             | Field mapper | <-----> | ExternalEntityStorage |
                             +--------------+         +-----------------------+
                                     ^ 1                         ^ 1
                                     |                           |
                                     v 0..*                      v 1..*
                            +-----------------+         +-----------------+
                            | Property mapper |         | External entity |
                            +-----------------+         +-----------------+
                                     ^ 1
                                     |
                                     v 0..*
                             +----------------+
                             | Data processor |
                             +----------------+
```

### Typical storage streams for external entity data (simplified)

#### Loading
```
  EntityStorageBase::load()
  EntityStorageBase::loadMultiple()
    ContentEntityStorageBase::preLoad()
      hook_entity_preload()
      // "preLoad()" may use ExternalEntityStorage::getQuery().
    ExternalEntityStorage::doLoadMultiple()
      ExternalEntityStorage::getFromPersistentCache()
      ExternalEntityStorage::getFromExternalStorage()
        ExternalEntityStorage::getRawDataFromExternalStorage()
          DataAggregatorInterface::loadMultiple()
            StorageClientInterface::loadMultiple()
          [Event] ExternalEntityTransformRawDataEvent
        ExternalEntityStorage::mapRawDataToExternalEntities()
          ExternalEntityStorage::extractEntityValuesFromRawData()
            FieldMapperInterface::extractFieldValuesFromRawData()
              // Depending on the field mapper type, it may or may not
              // use property mappers.
              PropertyMapperInterface::extractPropertyValuesFromRawData()
                // Depending on the property mapper type, it may or
                // may not use data processors.
                DataProcessorInterface::processData()
            [Event] ExternalEntityMapRawDataEvent
          ExternalEntityStorage::doCreate()
            new ExternalEntity()
      hook_entity_storage_load()
      hook_<xntt_type_id>_storage_load()
      ExternalEntity::mapAnnotationFields()
    EntityStorageBase::postLoad()
      hook_entity_load()
      hook_<xntt_type_id>_load()
```

#### Saving
```
  EntityStorageBase::save()
    EntityStorageBase::doPreSave()
      // Blocks read-only xntt types.
      ExternalEntityStorage::preSave()
      hook_presave()
    ExternalEntityStorage::doSave()
      // Only if not read-only.
      EntityStorageBase::doSave()
        // The main place where things are saved.
        ExternalEntityStorage::doSaveFieldItems()
          ExternalEntityType::getDataAggregator()
            DataAggregatorInterface::save()
              StorageClientInterface::save()
                ExternalEntity::toRawData()
                  ExternalEntityStorage::createRawDataFromEntityValues()
                    FieldMapperInterface::addFieldValuesToRawData()
                      // Depending on the field mapper type, it may or may not
                      // use property mappers.
                      PropertyMapperInterface::addPropertyValuesToRawData()
                        PropertyMapperBase::reverseDataProcessing()
                          // Depending on the property mapper type, it may or
                          // may not use data processors.
                          DataProcessorInterface::reverseDataProcessing()
                  [Event] ExternalEntityExtractRawDataEvent
    EntityStorageBase::doPostSave()
      postSave()
      hook_update() / hook_insert()
```

### Typical external entity querying stream (simplified)
```
  EntityStorageBase::getQuery()
    EntityStorageBase::getQueryServiceName()
    --> Drupal\external_entities\Entity\Query\External\QueryFactory
      --> Drupal\external_entities\Entity\Query\External\Query
        Query::result()
          // For counts:
          EntityStorageBase::countExternalEntities()
          // For filtering:
          EntityStorageBase::queryRawDataFromExternalStorage()
            // "Count" is not developed here but uses a similar approach.
            DataAggregatorInterface::query()
              StorageClientInterface::query()
                StorageClientInterface::transliterateDrupalFilters()
                  [Event] ExternalEntityTransliterateDrupalFiltersEvent
                StorageClientInterface::transliterateDrupalSorts()
                  StorageClientInterface::transliterateDrupalSortsAlter()
                    [Event] ExternalEntityTransliterateDrupalSortsEvent
                // Filter on source with source-supported filters.
                StorageClientInterface::querySource()
                // Filter results on the Drupal side and process the rest of the
                // filters not supported by the source.
                StorageClientBase::postFilterQuery()
                StorageClientBase::sortEntities()
```

## Code notes

What is the difference between ExternalEntityType::id(),
ExternalEntityType::getDerivedEntityTypeId(), and
ExternalEntityType::getBasePath()?
- ExternalEntityType::id() will give you the external entity type identifier.
- ExternalEntityType::getDerivedEntityTypeId() will give you the external
  content entity type name. Usually, it is the same as its corresponding
  external entity type identifier. However, programmatically created external
  entities types (derived for ExternalEntity and ExternalEntityType classes) may
  use different identifiers between config types and content (instance) types.
- ExternalEntityType::getBasePath() provides the URL base path to a given
  external entity content. It used to be the same as ExternalEntityType::id()
  with only underscores turned into dashes, but it is now possible to set
  it manually and it may not correspond at all to the type ID anymore.

For example, you could theorically have an external entity type instance that
uses the identifier 'my_xntt' (returned by a derived class
ExternalEntity::getExternalEntityType() method), which defines how to get, map
data and display the external entity content type 'external_content'. Such
external entity content could be made accessible throught the URL path:
"/ext/data".

Use `ExternalEntityType::id()` when referring to the external entity type
identifier. It should be rare (log messages, debugging).

Use `ExternalEntityType::getDerivedEntityTypeId()` when referring to the
corresponding external content entity type identifier. For instance:
```
  $xntt_instance_42 = \Drupal::entityTypeManager()
    ->getStorage($xntt_type->getDerivedEntityTypeId())
    ->load(42);
  // Or to check permissions:
  $permission =
    'create '
    . $xntt_type->getDerivedEntityTypeId()
    . ' external entity';
  if ($account->hasPermission($permission)) {
    // ...
  }
```

Finally, ExternalEntityType::getBasePath() should be used when you need to work
with an external entity content base pathirectly. However, you may consider
using adapted services instead. For instance:
```
  Url::fromRoute("entity.{$xntt_type->getDerivedEntityTypeId()}.collection");
```

## v2 to v3 changes

v3 has been introduced to allow major External Entities upgrades (through
breaking changes) while keeping v2 available to let previous system continue
working without being required to upgrade. The v3 changes come from raised
issues, improvements and new feature requests, after several years of use of
External Entities in various contexts.

List of *BREAKING CHANGES* between External Entities v2 and v3:
- Global changes:
  - Type hinting has been added to v3 wherever possible.
  - ExternalEntityInterface and ExternalEntityTypeInterface have been moved to
    the Drupal\external_entities\Entity namespace to regroup them with their
    respective implementations.
  - Storage class name simplification: "ExternalEntityStorageClient*" become
    "StorageClient*".
  - Storage client plugin type name changed from "ExternalEntityStorageClient"
    to "StorageClient".
  - ExternalEntityGetMappableFieldsEvent event class does not have a field
    mapper anymore.
- Field Mapper
  - Removed unnecessary abstraction layers for field mappers:
    - "ExpressionFieldMapperInterface" has been merged to
      "FieldMapperInterface".
    - "ConfigurableExpressionFieldMapperBase" and "ExpressionFieldMapperBase"
      have beend merge to "FieldMapperBase".
    Those layers came from a discussion when introducing field mapper plugins:
    https://www.drupal.org/project/external_entities/issues/3079012#comment-13437754
    Field mapper plugins should now use "FieldMapperBase" class as base.
    The rational behind this change is that those layers were adding complexity
    to the code since some methods of "ExpressionFieldMapperInterface" were
    required at some places where "FieldMapperInterface" was used. It would be
    hard to choose where to use one interface or the other while only one is
    used by plugins and current external entities implementation forces the use
    of plugins for field mappers. If one day, someone would create external
    entities type from code and want to force the use of a non-plugin field
    mapper, either this person would have to turn it into a plugin (which would
    not be so complicated) or raise an issue with explanations on the use case.
  - What used to be "Field Mapper" is now "Property Mapper" with little
    differences. Basically, old field mappers were mapping all the external
    entity field properties. Now, it is possible to select which "old" field
    mapper to use to map which field property. Therefore, the new "property
    mappers" are very similar to old "field mappers" and upgrading your custom
    old field mapper means you will have to turn it into a property mapper
    plugin instead. That being said, here are some changes:
  - "extractEntityValuesFromRawData" has been replaced by
    "extractPropertyValuesFromRawData" and uses an additional parameter
    $context (see method doc).
  - "addFieldValuesToRawData" has been turned into "addPropertyValuesToRawData".
  - "createRawDataFromEntityValues", "extractFieldPropertyValuesFromRawData",
    "extractFieldValuesFromRawData", "extractIdFromRawData",
    "getConstantMappingPrefix", "getFieldMapping", "getFieldMappings",
    "getFieldPropertyMapping", and "getRequiredFieldMappings" have been removed.
  - Constants can be used through a dedicated property mapper and therefore do
    not need to be handled by other property mappers anymore (no mode use of
    "getConstantMappingPrefix").
  - To get the identifier from raw data, you should use the external entity
    type:
    ```
      $xntt_type
        ->getFieldMapper('id')
        ->extractFieldValuesFromRawData($raw_data)[0]['value']
    ```
  - The "simple field mapper" use now a different synatx with dots as separators
    rather than slashes to be similar to Drupal field syntax for filtered
    queries.
- Storage client:
  - The class and the interface names have changed as describbed in the global
    changes, as well as the plugin name.
  - the "query"  and "countQuery" methods are still there but their v2
    implementation corresponds now to the new methods "querySource" and
    "countQuerySource" (renamed). In fact, in v2, the $parameters array was
    containing Drupal field names instead of source field names while in most
    implementations, thoses fields were not translated to their corresponding
    mapped source field names. Furthermore, some filter operators amy not be
    supported by the source. So "querySource" and "countQuerySource" are now
    expecting a $parameters containing only source-supported operators and field
    names (transcribbed).
  - To manage the difference between Drupal supported filters and source
    supported filters in filtering queries (ie. "query" and "countQuery"), there
    is a new method that needs to be implemented: "transliterateDrupalFilters".
  - To supported automated Drupal field generation and mapping, 2 new methods
    were added (and have a default implementation if not needed):
    "getRequestedDrupalFields" and "getRequestedMapping".
- REST client:
  - The REST client has been greatly changed (to not say completly rewritten).
    If your plugin is derivated from the REST client, please have a look to the
    JSON::API storage client (as well as the new module xntt_wiki) to see how
    to inherit the base class and the base form.
  - The syntax for parameters has been changed: pipe was used to separate
    parameter names from their values; now we use the equal sign which is more
    similar to query string syntax.
  - The wiki client has been removed and turned into a standalone module
    xntt_wiki.

List of v3 new features:
- Global:
  - New method to set and retrieve source raw data from an external entity:
    ´setSourceRawData(array)´ and ´getSourceRawData()´. When external entities
    are saved, source fields that were not mapped to Drupal fields are kept.
  - Plugins (Storage Clients and others) can now rely on the method
    submitConfigurationForm() rather than validateConfigurationForm() to set
    their configuration changes while still use validateConfigurationForm() to
    validate/invalidate form submission. It is less confusing for people
    familiar with the Drupal form API.
  - Added support for debugging external entities (loading, mapping, saving).
  - Both field mapping and storage settings can have administrative notes
    for an easier maintenance (document specific setting reasons, other possible
    options not used, etc.).
  - External Entity Types can be created programmatically or by config, with or
    without editing restrictions and are highly customizable (list builder,
    forms, etc.).
  - Plugins using PluginFormTrait now benefit from the new method
    ::buildAjaxParentSubForm() allowing to use Ajax call to update any parts of
    subforms.
- Storage clients:
  - New interfaces and base classes for categories of storage clients:
    - RestClientInterface and RestClient (previously just "Rest") to implement
      any custom REST storage client. Note: the "base" class RestClient can be
      used "as is" (ie. not an abstract class) and is therefore not suffixed
      "Base".
    - FileClientInterface and FileClientBase to implement any file storage
      client.
    - QueryLanguageClientInterface and QueryLanguageClientBase to implement any
      query language or database storage client.
- Aggregator plugin: a new aggregator plugin has been added to allow aggregation
  of multiple storage clients (similar to xnttmulti plugin used to do for v2).
  2 implementations are provided: single storage client (no aggregation) and
  group aggregator that can do complex aggrgation and be used as base for
  similar aggragation needs.
- REST client:
  - New endpoint options to support specific URLs to fetch a single entity or to
    count entities.
  - Support for any response structure using JSONPath to locate data in result
    sets.
  - Improved pager settings to support server limits and paging starting from 1.
  - Better support for authentication. Bearer token built-in support. API key
    support through query string. Support for custom authentication header.
  - Added support for custom headers. Ex.: "Accept: application/vnd.api+json".
  - Additional parameters can now be sent either as a query string or as POST
    data using the endpoint data encoding format. It is possible to mix POST and
    GET parameters using a question mark to specify query parameters in the
    endpoint URL.
  - Added an entity identifier placeholder "{id}" replaced in single entity
    endpoint URL and parameters.
  - Added support for query rate limitations (see QueryLimitationTrait) that
    could be used by other plugins.
- Files storage client is part of external entities module.
  - The files storage plugin ID changed from 'xnttfiles' to just 'files'.
  - A file field mapper plugin is also provided sepratly as a companion module.

## v2 to v3 plugin upgrade

### Upgrading v2 storage clients
- In the module's composer.json (if one) update requirements ("require") from
  `"drupal/external_entities": "^2.0@alpha"`
  (or similar) to
  `"drupal/external_entities": "^3.0@alpha"`
- In the storage class, update annotations from:
  ` * @ExternalEntityStorageClient(`
  to
  ` * @StorageClient(`
- Adjust ExternalEntityInterface and ExternalEntityTypeInterface namespaces.
- If the storage client implements the constructor (`__construct()`) and the
  `create()` methods, ResponseDecoderFactoryInterface has been removed and
  two other services are added: LoggerChannelFactoryInterface, and
  EntityFieldManagerInterface. Make the changes in both methods unless you use
  ResponseDecoderFactoryInterface. In that later case, you will need to do the
  changes in the __constructor() when calling parent class constructor and
  add LoggerChannelFactoryInterface, and EntityFieldManagerInterface to your
  __constructor() and create() methods. You can get a logger channel factory and
  an entity field manager from core services using:
  ```
  $container->get('logger.factory')
  $container->get('entity_field.manager')
  ```
  Don't forget to update code documentation and to add the import:
  ```
  use Drupal\Core\Logger\LoggerChannelFactoryInterface;
  use Drupal\Core\Entity\EntityFieldManagerInterface;
  ```
  For the explanations, ResponseDecoderFactoryInterface was only needed by REST
  clients and was removed from the StorageClientInterface. On the other side,
  EntityFieldManagerInterface was needed by the new features added to
  EntityFieldManagerInterface for query filtering. And the logger factory should
  be provided to all storage clients as they all would need to log things.
- To log messages, you can now use `$this->logger->notice(...)` or
  `$this->logger->warning(...)` or whatever log method you need.
- If the storage client extends the built-in REST client, you will have to take
  into account new configuration items added as well as form element changes.
  Also note the Rest storage client class has been renamed into "RestClient" for
  consistency (regarding other base clients).
- You should provide an implementation for `submitConfigurationForm()` and move
  some parts of `validateConfigurationForm()` into this new handler that should
  call `$this->setConfiguration($form_state->getValues());` in the end:
  ```
  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    ...
    $this->setConfiguration($form_state->getValues());
  }
  ```
- Rename query() and countQuery() into querySource() and countQuerySource()
  respectively. This should be sufficient unless you already tried to translate
  Drupal field names into mapped source field names in your code logic. In that
  case, you may need to do some more code adjustments.
- Implement transliterateDrupalFilters() method. To start, you can use the
  basic but unefficient implementation:
  ```
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = []
  ) :array {
    // @todo: To really implement for better performances.
    return $this->transliterateDrupalFiltersAlter(
      ['source' => [], 'drupal' => $parameters],
      $parameters,
      $context
    );
  }
  ```
  When implementing, you may find helpfull to use
  $this->externalEntityType->getFieldMapper() and $this->entityFieldManager new
  class member as well as the getFieldDefinitions() and getFieldMappings() new
  methods. The storage client "knows" which operators are supported by its
  storage source (list of used operators: see StorageClientInterface::query()
  documentation and Drupal Query API documentation:
  https://www.drupal.org/docs/8/api/database-api/dynamic-queries/conditions and
  https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Query%21QueryInterface.php/function/QueryInterface%3A%3Acondition/10).
  Then, some operators may or may not be supported by some source fields. While
  the $parameters parameter containes filter on *Drupal* field names, it will be
  required to match those to source fields using the field mapper
  ($this->externalEntityType->getFieldMapper() and getFieldMappings() method).
  And to tell if a given source field can support a given operator, you may rely
  on the Drupal mapped field type given by the user to guess the source field
  type ($this->entityFieldManager and getFieldDefinitions() method).
- For derived REST clients:
  - You should get familiar with the new features and see if some may not change
    your approach.
  - Update buildConfigurationForm() to adjust elements that should be visible or
    hidden with pre-filled values. You may also adjust existing and new field
    properties. To simplify this process, a new method is provided:
    "overrideForm" from "PluginFormTrait". It also handles better futur possible
    changes in parent form with a default behavior for new elements.
    See JsonAPI client implementation for an example.
  - If overriden, update getListQueryParameters() signature to:
    ```
    protected function getListQueryParameters(array $filters = []) {
    ```
    and replace `$parameters` by `$filters`.
    Also remove the use of getPagingQueryParameters() in that method as it is
    handled separatly now.
  - If overriden, update getPagingQueryParameters() logic according to the new
    behavior. Maybe this new implementation would not need to be overriden and
    you may just remove the override.
  - If overriden, update getSingleQueryParameters() signature to:
    ```
    protected function getSingleQueryParameters(int|string|null $id) :array {
    ```
  - If overriden, update getHttpHeaders() signature to:
    ```
    public function getHttpHeaders(
      string $request_type = 'list',
      string $endpoint = '',
      array $parameters = []
    ) :array {
    ```
- For derived File clients:
  - Now xnttfiles plugin is part of external entities. You need to remove the
    dependency in you .info.yml file:
    ```
    dependencies:
    - external_entities:external_entities
    - xnttfiles:xnttfiles
    ```
    becomes:
    ```
    dependencies:
    - external_entities:external_entities
    ```
    and your module composer.json needs to be updated as well to require only
    external_entities v3:
    ```
    "require": {
      "drupal/external_entities": "^2.0@beta",
      "drupal/xnttfiles": "^1.0@beta"
    }
    ```
    becomes:
    ```
    "require": {
      "drupal/external_entities": "^3.0@beta"
    }
    ```
  - Method ::getIdField() has been removed and can be replaced by base
    class method ::getSourceIdFieldName(). method ::getProcessedId() has been
    added and should be considered as well as a replacement.
  - The internal raw field added to store file path has been renamed from
    '_xnttfiles_path' to '_xntt_path'.
  - The config structure changed a little (see
    FileClientBase::defaultConfiguration() and Files::defaultConfiguration()).
    You may need to implement a hook_update_N() in yourmodule.install file to
    manage settings upgrade. You will need to make sure your upgrade will occure
    after the external entity module corresponding one (93010). For example:
    ```
    /**
     * Implements hook_update_dependencies().
     */
    function yourmodule_update_dependencies() {
      $dependencies['yourmodule'][9101] = [
        'external_entities' => 93010,
      ];
      return $dependencies;
    }

    /**
     * Update field mapper configs to new structure.
     */
    function yourmodule_update_9101(&$sandbox) {
      $xntt_storage = \Drupal::entityTypeManager()
        ->getStorage('external_entity_type');
      $xntt_types = $xntt_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->execute();

      // Loop on external entity types.
      foreach ($xntt_types as $entity_type_id) {
        // Upgrade file storage client ids.
        /** @var \Drupal\Core\Config\Config $config */
        $config = \Drupal::configFactory()->getEditable(
          "external_entities.external_entity_type.{$entity_type_id}"
        );

        $scs = $config->get('storage_clients');
        $need_update = FALSE;
        foreach ($scs as &$sc) {
          if ($sc['id'] == 'yourmodule_pluginid') {
            $need_update = TRUE;
            $sc['config']['performances']['use_index'] =
              $sc['config']['use_index'];
            unset($sc['config']['use_index']);
            $sc['config']['performances']['index_file'] =
              $sc['config']['index_file'];
            unset($sc['config']['index_file']);
            $sc['config']['data_fields']['index_file'] =
              $sc['config']['field_list'];
            unset($sc['config']['field_list']);
            //... do some more stuff if needed.
          }
        }
        if ($need_update) {
          $config->set('storage_clients', $scs);
          $config->save();
        }
      }
    }
    ```

### Upgrading v2 field mappers
The field mapper approach changed a little: before, a single field mapper was
used to map all the fields and it could manage both a mapping expression and
support constants.

The new approach provides 3 layers: a field mapper plugin (totally different
from prevoious behavior) that can be selected for each field, a property mapper
plugin that can be selected for each field property and one or more data
processor plugins that can be used for each property. Therefore, the property
mapper plugin is what is closest to what used to be the "field mapper plugin".

Furthermore, it is now possible to have a field-type specific field mapper to
better handle field specificities like the text format for text fields or
the file to provide to the file or image fields. There is no need to support
a "constant" prefix to use constant values as there is a dedicated "constant"
property mapper now.

For more practical changes:
- In the module's composer.json (if one) update requirements ("require") from
  `"drupal/external_entities": "^2.0@alpha"`
  (or similar) to
  `"drupal/external_entities": "^3.0@beta"`
- The base class of field mapper plugins is not
  `ConfigurableExpressionFieldMapperBase` anymore but `PropertyMapperBase`.
  Replace:
  ```
  use Drupal\external_entities\FieldMapper\ConfigurableExpressionFieldMapperBase;
  ...
  ... extends ConfigurableExpressionFieldMapperBase ...
  ```
  By
  ```
  use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
  ...
  ... extends PropertyMapperBase ...
  ```
- Adjust ExternalEntityInterface and ExternalEntityTypeInterface namespaces.
- The default constructor has an addition parameter:
  LoggerChannelFactoryInterface $logger_factory
- Remove the implementation of:
  - ::getConstantMappingPrefix() (not used anymore)
  - ::extractIdFromRawData() (implemented in base class)
  - ::getFieldMappings() (if there are no special needs)
- Add type hinting to methods according to the FieldMapperInterface interface.
- Update ::buildConfigurationForm() according to the changes explained above
  about mapping type selection unless you rely on base implementation.
- "extractEntityValuesFromRawData" has been replaced by
  "extractPropertyValuesFromRawData" and uses an additional parameter
  $context (see method doc).
- "addFieldValuesToRawData" has been turned into "addPropertyValuesToRawData".
- "createRawDataFromEntityValues", "extractFieldPropertyValuesFromRawData",
  "extractFieldValuesFromRawData", "extractIdFromRawData",
  "getConstantMappingPrefix", "getFieldMapping", "getFieldMappings",
  "getFieldPropertyMapping", and "getRequiredFieldMappings" have been removed.
- To log messages, you can now use `$this->logger->notice(...)` or
  `$this->logger->warning(...)` or whatever log method you need.
- You may implement a hook_update_N() in yourmodule.install file to manage
  settings upgrade. You will need to make sure your upgrade will occure after
  the external entity module corresponding one (93011) which adds property
  mappers. For example:
  ```
  /**
   * Implements hook_update_dependencies().
   */
  function yourmodule_update_dependencies() {
    $dependencies['yourmodule'][9101] = [
      'external_entities' => 93011,
    ];
    return $dependencies;
  }

  /**
   * Update field mapper configs to new structure.
   */
  function yourmodule_update_9101(&$sandbox) {
    $xntt_storage = \Drupal::entityTypeManager()->getStorage('external_entity_type');
    $xntt_types = $xntt_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    // Loop on external entity types.
    foreach ($xntt_types as $entity_type_id) {
      // Upgrade field mapper configs.
      /** @var \Drupal\Core\Config\Config $config */
      $config = \Drupal::configFactory()->getEditable(
        "external_entities.external_entity_type.{$entity_type_id}"
      );

      $fms = $config->get('field_mappers') ?? [];
      $need_upgrade = FALSE;
      foreach ($fms as &$fm) {
        //...
      }
      if ($need_upgrade) {
        $config->set('field_mappers', $fms);
        $config->save();
      }
    }
  }
  ```
  ...or you may just ask you user to update manually their external entity
  type configs.

## v3.0 to v3.1 plugin upgrade (Drupal 12)

v3.1 adds Drupal 12 support: the `core_version_requirement` is now
`^11.2 || ^12`. Two things changed that may affect custom plugins: plugin
annotations have been turned into PHP attributes and return types have been
added to the plugin base classes. The return types are needed because, without
them, PHP 8.5 and Drupal 12 turn the "method should return..." notices into
fatal errors.

For your own plugins:
- If you override one of the following methods, add the same return type as the
  base class, otherwise PHP will tell you the declaration is not compatible:
  - `getConfiguration()` and `defaultConfiguration()` on any plugin base class
    (`StorageClientBase`, `FileClientBase`, `QueryLanguageClientBase`,
    `FieldMapperBase`, `PropertyMapperBase`, `DataProcessorBase`,
    `DataAggregatorBase`). Update the signature to:
    ```
    public function defaultConfiguration() :array {
    ```
  - `calculateDependencies()` on `FieldMapperBase`, `PropertyMapperBase` and
    `DataAggregatorBase`. Update the signature to:
    ```
    public function calculateDependencies() :array {
    ```
- Return types have also been added to the built-in plugins and to the entity,
  storage, query and manager classes. If you extended one of them directly, do
  the same and add the return type reported by core. The full list of changed
  methods is in the merge request of the issue:
  https://www.drupal.org/project/external_entities/issues/3617205
- If you still use annotations in your plugins, they keep working for now but
  you should switch them to PHP attributes like the built-in plugins do (see any
  plugin under src/Plugin/ExternalEntities/).

## Creating a storage client plugin

To create a storage client plugin, you should start by choosing a base class and
see the source code of an example implementation:
- for REST/web services, use
   Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient
   Example:
     Drupal\external_entities\Plugin\ExternalEntities\StorageClient\JsonApi
- for files as objects of a file system use
  Drupal\external_entities\Plugin\ExternalEntities\StorageClient\Files
  Example:
    Drupal\xnttmedia\Plugin\ExternalEntities\StorageClient\MediaFiles
- for files as containers of one or more entities use
  Drupal\external_entities\Plugin\ExternalEntities\StorageClient\FileClientBase
  Example:
    Drupal\xntttsv\Plugin\ExternalEntities\StorageClient\TsvFiles
- for query languages (noSQL database for instance), use
  Drupal\external_entities\Plugin\ExternalEntities\StorageClient\QueryLanguageClientBase
  Example:
    Drupal\xnttsql\Plugin\ExternalEntities\StorageClient\Database
    (provided as a companion module in external entities module)
- for an SQL database advanced client, use
  Drupal\xnttsql\Plugin\ExternalEntities\StorageClient\Database
  (provided as a companion module in external entities module)
  Example:
    Drupal\chadol\Plugin\ExternalEntities\StorageClient\Chado
- for another type of storage client plugin that does not fit the proposed base
  classes, use Drupal\external_entities\StorageClient\StorageClientBase
  and implement Drupal\Core\Plugin\PluginFormInterface to have config saved.
  Also considere using Drupal\external_entities\Plugin\PluginFormTrait.
  Examples:
    [Drupal\xnttsanity\Plugin\ExternalEntities\StorageClient\Sanity](https://git.drupalcode.org/project/xnttsanity/-/blob/1.0.x/src/Plugin/ExternalEntities/StorageClient/Sanity.php?ref_type=heads)
    [Drupal\gbif2_entity\Plugin\ExternalEntities\StorageClient\Gbif](https://git.drupalcode.org/project/gbif2/-/blob/2.0.x/modules/gbif2_entity/src/Plugin/ExternalEntities/StorageClient/Gbif.php?ref_type=heads) (companion module of gbif2 module)
Then, your storage client will have to implement a set of methods. Some methods
may be complex to implement, such as ::transliterateDrupalFilters(). While it
can have a basic implementation, consider providing a better implementation for
performances (see "Upgrading v2 storage clients" related part).
Also, the ::save() method is responsible to update the entity object identifier
for new entities (ie. `$entity->set('id', $id);`). See how the REST client does
it to have an idea on how to proceed. Indeed, the ::save() method does not
return the entity identifier but a status (SAVED_NEW or SAVED_UPDATED) so the
only way to return the new identifier is through the (mapped) entity object.

## Creating a field mapper plugin

The "Generic" field mapper provided in external entities module should cover any
field type. However, sometimes, it might be better to have a dedicated field
mapper for a given Drupal field type.

When developping new field mapper plugin, consider having a look to 2 examples
provided with external entities:
- Drupal\external_entities\Plugin\ExternalEntities\FieldMapper\TextFieldMapper
- Drupal\xntt_file_field\Plugin\ExternalEntities\FieldMapper\FileFieldMapper

## Creating a property mapper plugin

There is a couple of property mappers that can be used as example:
- Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper\*
- Drupal\xnttstrjp\Plugin\ExternalEntities\PropertyMapper\StringJsonPath
  (that one does not use data processors as it is able to process data by
  itself and aggregate data from multiple source data fields)

## Creating a data processor plugin

Consider having a look to the source code of data processors provided with
extenal entities module:
Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\*

While the main use case is to alter data to make it fit Drupal needs, don't
forget to consider the reverse process: get data from Drupal entity to "reverse
process" it to be saved back to its source storage. If it is not possible, then
::couldReverseDataProcessing() should return FALSE. If it might be possible but
it has not been implemented or in a given case, it is not possible,
::reverseDataProcessing() should return NULL.

Also keep in mind that a data processor always processes an array of values,
even if the given field only has one value (the data array will contain just one
value).

## Creating a data aggregator plugin

Consider having a look to
Drupal\external_entities\Plugin\ExternalEntities\DataAggregator\GroupAggregator
for a complex example. It could be used as a base class as it provides many
usefull features. Horizontal and vertical data aggregators are examples of
derived plugins from the GroupAggregator.
When playing with "foreign keys" (ie. field of one source data is used as
identifier on another one), the data aggregator is responsile to provide the
appropriate field mapping to the second data source to let that source know
which (Drupal entity) field is mapped to that data source identifier and also
temporarily adjust the provided entity object identifier.
Also, keep in mind some restrictions in the storage client selection can be set
though the ExternalEntityType lock settings. See ExternalEntityType::getLocks()
and ExternalEntityType::setLocks() for details, and support those restrictions
in your data aggregator implementation.

## Creating external entity types programmatically

See example companion modules "external_entities_drupalorg" and
"xntt_example_d7import". You can provide external entity type configurations in
your module config/install or config/optional (if the config should only be
loaded if all dependencies are available). You may also add constraints to
restrict your external entity type modifictations by users.

The configuration constraints can be defined for your external entity type
in its config. See external entity type config schema for details or
xntt_example_d7import for an example. It is also possible to override the
ExternalEntityType::getLocks() methods. See ExternalEntityType::setLocks() for
details on the lock structure.
