# This is a reminder list (last update 2026/03/05)

## Refactoring
- Several xntt plugin configs use a list of items that can be adjusted:
  - the list of data processor for property mappers
  - the list of sub-property mappers for the multi-property mapper
  - the list of storage clients for the group data aggregator
  - the list of schemas for the SQL database storage client
  - the list of placeholders for the query language base storage client
  - the list of filters for the SQL database storage client
  Those lists were managed using numeric indices but some of them should support
  text indicies (group data aggregator and multi-property mapper).
  I recently refactored the multi-property mapper to only support non-numeric
  keys (commit #52d1c0253716889d90444cf9430b132715e067a9). This approach let the
  user set a key for each item.
  Using text indices allows to manage list elements using their keys as a
  reference. For instance, if I have an automated process that adds storage
  clients to a group data aggregator, it is convinent to be able to identify
  clients added for a given purpose using their keys, and then be able to
  disable or remove a set, or perform other tasks. It is not possible with
  numeric keys and it would require to investigate the content of each
  sub-config.
  Also, it allows the user to reference those clients in other parts of the
  config (ie. storage clients that should be used for loading or saving).
  It is not possible to mix numeric and textual indices as Drupal will
  automatically reindex numeric indices: indices [1, 'text1', 4, 'text2'] will
  be turned into [0, 1, 'text1', 'text2'] on saving. The order is changed and
  any reference to "4" becomes invalid (and reference to "1" does not correspond
  to the right client!). That's why text keys should be used exclusively.
  This new approach, using only text keys, should be applied to existing lists
  when it could be usefull to reference their elements.

- The way external entity type config is managed during runtime is a bit
  complex: each external entity type uses plugins (field mappers, data
  aggregators) themselves using other plugins (property mappers, storage
  clients) and so on. Each plugin lives with its own config while it has been
  loaded with the external entity type base config. It means that the config of
  a property mapper can be changed from the property mapper instance itself
  while it can be also changed from the field mapper. Those living configs
  must be synchronized with the global external entity type config as well.
  Some turn-arounds have been used but the architecture may need to be
  refactored to be more reliable and performant.

- Forms: many plugin forms use methods to build sub-parts of the form. Those
  methods should be armonized to use most of the same arguments in the same
  order and they should return a form element rather than modifying the $form
  array itself if it is passed by reference.
  However, it might still be necessary to pass the $form array by reference
  because it might be needed by some sub-calls, especially when working with
  sub-form states.

- Forms: when using AJAX calls or '#states', HTML element identifiers must be
  unique. To do so, a complex approach is used to generate an ID from parent
  elements (sometime the base ID is provided as argument, sometimes it is
  retrieved from the $form hash when provided). Sometimes, the use of an HTML ID
  is not possible and an HTML "data-*" property is used instead. The approach
  needs to be normalized/homogenized (taking into account all the use cases).

- Form submissions:
  - some validateConfigurationForm() implementations modify form element
  values but should not. It should only be changed in submitConfigurationForm().
  I think it was mostly done to avoid double-computing the same things, once
  for the validation and once for the submission. This could be avoided using
  form state properties to store computed data but property names should be used
  with care as a same name could be used multiple times by plugins in different
  contexts (ex. a same field mapper plugin can be used twice for the same
  external entity, especially in case of translation support). To avoid such a
  problem, the property mappers can use ::getPropertyMapperFormIdentifier().
  This method should be generalized (trait?) and implemented in other plugins.
  Note: the need of a unique identifier is also shared with form elements
  using AJAX calls or '#states', to set an HTML identifier (or property) for an
  element (see previous point above).
  - Some form errors detected during validation use FormState::setErrorByName().
  It is possible that the name is not (always) valid and the form element with
  an error may not get highlighted. This should be checked and the use of
  FormState::setError() should be prefered (based on '#parents' form property).

- GitLab validation pipeline should pass without warnings. Currently, eslint
  issues could be easily fixed. phpstan would require more work but should be
  fixed as well. cspell  should be re-enabled and fixed. It would require a lot
  of work to clean code variable names and standardize their uses, but it would
  be helpfull for people new to this code and willing to contribute.


## Depreciations
- Drupal depreciations should be addressed while keeping in mind some users are
  still using Drupal 10. In the case of changes that would remove D10
  compatibility, a new version should be used instead (ie. "3.1"). We must
  maintain a D10 compatible version until a stable "3.0" is released.


## Tests
Many tests are missing or are incomplete. See issue #3001400. This is a list by
priority:
- improve test speed when using the Guzzle client for REST tests: having the
  HTTP output captured is not really usefull (is it?).
- user interface tests for ajax elements.
  - External entity Type form:
    - test UI for field mapper selection change
    - test UI for storage client selection change (single client radios)
    - test UI for storage client selection change (multiple clients dropdown)
      Need to test selection on each of a 3 clients xntt type
    - test enable mmultiple storage clients
    - test adding a new storage client
    - test removing the last storage client of a set of 3
    - test removing the middle storage client of a set of 3
    - test removing the first storage client of a set of 3
    - test the different UI restrictions on programmatically create xntt

- module install/uninstall (companion modules).
- file storage client.
  - test the file indexation.
- Query Language and SQL storage client (requires dbxschema module not shipped
  with xntt).
  - test placeholder replacement.
  - test complex queries.
- complete other plugin tests (some tests are existing but incomplete).
  - Simple field mapper:
    - test "field/*" pattern (already tested in SimpleExternalEntityTest but it
      would require a faster unit test)
    - test "field/*/something" pattern
    - test "*/something" pattern
  - Xntt File Field module:
    - test stream wrapper for read access to a URL
    - test stream wrapper for read access to a local file
    - test stream wrapper prevents wrtie access to a system-writable local file
    - test invalid xntt-file identifers
- list to be completed...


## Security
Once a stable release is done, security checks should be performed. Special care
should be taken for:
- file field formatter and its stream wrapper.
- access permissions.
- discrepencies between views permissions and displayed xntt permissions.
