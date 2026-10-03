# External Entities Tests

## Table of contents

- Test environment
- Test coverage
- Missing tests


## Test environments

### Environment for REST clients
The dedicated text module "external_entities_test" can be used as a REST server.
Classes and traits:
- ExternalEntitiesUiHelperTrait
- ExternalEntitiesBrowserTestBase
- ExternalEntitiesWebDriverTestBase

### Environment for File clients

@todo

### Environment for SQL clients

@todo


## Test coverage

### Functional
- FileClientFunctionalTest: covers Files storage client
  - file xntt listing
  - single file xntt display
  - single file xntt edit page (but not saving)
  - file xntt properties (path, dirname, basename, filename, extension, mode,
    size, modification time, md5 hash, sha1 hash)
- MultiStorageTest: covers Group data aggregator
  - no groups, no joins, no overrides
  - no groups, no joins, override all
  - no groups, no joins, override if empty
  - no groups, no joins, in subfield
  - no groups, with join field, with override all
  - no groups, with join using non-id field, with override all
  - no groups, with join field on non-id field, with override all
  - no groups, with multi-join field in JSONPath on non-id field, with override
    all
  - simple groups, no joins, no overrides
- QueryTest: covers Entity Query API support
  - basic query to get all
  - basic query to get the first 3
  - basic query with field filter "="
  - basic query with field filter "ENDS_WITH"
  - composed query with field filter "ENDS_WITH" or field filter "STARTS_WITH"
  - composed query with (field filter "ENDS_WITH" or field filter "STARTS_WITH")
    and field filter "!="
  - a complex query with multiple "or" and "and" and field filtering "BETWEEN",
    "CONTAINS", "IN", and "STARTS_WITH".
- RestClientFunctionalTest: covers REST storage client
  - REST xntt type editing form elements
- SimpleExternalEntityTest: covers general xntt operations with REST storage
  client
  - REST xntt listing
  - REST xntt display
  - REST xntt edition
  - REST xntt saving
  - String field mapping
  - Text field mapping
  - Formatted text field mapping
  - Boolean field mapping
  - Multiple string field mapping
  - Entity reference field mapping

### Unit
- DataAggregatorUnitTest: covers GroupAggregator::mergeArrays()
  - full override
  - deep merge
  - override empty
  - deep merge empty
  - preserve int keys
  - preserve int keys deep
  - deep merge no new keys
- RestClientUnitTest: covers RestClient::getPagingQueryParameters(),
  RestClient::getQueryParameters(), and RestClient::getHttpHeaders()
  - no paging
  - paging starting from zero
  - paging starting from one
  - paging starting from a given item
  - no query string
  - other parameters only
  - other parameters only no source filtering
  - other parameters only with 2 fields
  - other parameters only with 2 fields and limited basic fields
  - other parameters only with 2 fields no source filtering
  - single parameters only
  - list parameters only
  - list and single parameters
  - other parameters with single and unsupported list parameters
  - other parameters with single and repeated list parameters
  - other parameters with single and indexed list parameters
  - other parameters with single and unindexed list parameters
  - other parameters with single and post list parameters
  - other parameters with single and list parameters no source filtering
  - other, single and list parameters with authentication
  - other, single and list parameters with authentication no source filtering
  - authentication only
  - single parameters with id
  - no headers
  - 2 lines header
  - bearer header
  - 1 line header with bearer header
  - 2 lines header with duplicate authentication header
  - custom authentication header
  - query authentication with no header


## Missing tests

List of tests to implement:
- FileClientFunctionalTest:
  - File xntt saving (file rename, path change)
  - File indexation
- MultiStorageTest:
  - simple groups, no joins, with overrides
  - simple groups, with joins, no overrides
  - groups with shared first source, no joins, no overrides
  - groups with ungrouped first source, no joins, no overrides
  - groups with separated first source and shared sub-sources, no joins, no
    overrides
  - invalid group prefix: common substring
  - invalid group: a new group appears first in a set of groups for a source
- RestClientFunctionalTest
  - REST xntt type editing form submission
  - REST client options (endpoints, cache, parameters, ...)
- SimpleExternalEntityTest/ExternalEntityTypeTest
  - Xntt Type form
  - AJAX calls
    - UI for field mapper selection change
    - UI for property mapper selection change
    - UI for data processor addition
    - UI for data processor selection change
    - UI for data processor removal
    - UI for data aggregator selection change
    - UI for storage client addition
    - UI for storage client selection change
    - UI for storage client removal
  - UI restrictions on programmatically create xntt
- JSON:API client:
  - filtering features
- SQL database client:
  - complex queries
  - placeholder replacement
  - saving
- Simple property mapper:
  - "field.*" pattern (already tested in SimpleExternalEntityTest but it
    would require a faster unit test)
  - "field.*.something" pattern
  - "*.something" pattern
- JSONPath property mapper:
  - a semi-complex expression (we are not testing Galbar's JSONPath lib itself)
- File Field module:
  - stream wrapper for read access to a URL
  - stream wrapper for read access to a local file
  - stream wrapper prevents wrtie access to a system-writable local file
  - invalid xntt-file identifers
- Data processors
  - each should be tested individually with revert processing when possible
