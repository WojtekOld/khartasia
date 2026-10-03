# External Entities

This module allows you to connect to and use datasets from external sources in
your Drupal website. While content lives externally, Drupal sees those remote
entities as internal. This makes it possible to alter the field displays, add
references, comments, path aliases, share buttons, web-shop products and more.

For a full description of the module, visit the 
[project page](https://www.drupal.org/project/external_entities).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/external_entities).


## Table of contents

- Requirements
- Installation
- Configuration
- How it works / How to
- Security
- Performances
- Native plugins
- Companion modules
- Recommended modules
- Maintainers & Credits


## Requirements

This module requires no modules outside of Drupal core but will require the
JsonPath PHP library ([galbar/jsonpath](https://github.com/Galbar/JsonPath-PHP))
which is automatically installed by composer (or ludwig) when using it.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Enable the module at Administration > Extend.
2. To enable integration of external entities, create a new external entity type
   on admin/structure/external-entity-types.
3. Configure the external storage source(s), save, set the identifier and title
   field mapping, save, add additional (Drupal) fields as needed, and configure
   their mappings. It is very important to correctly map the entity identifier
   field otherwise no entity could be listed.

For more information, see the documentation page about
[External Entities](https://www.drupal.org/docs/contributed-modules/external-entities).


## How it works / How to

### External Entities concept

An external entity type is defined by a name (note: non-word characters and
underscores are turned into dashes in external entity URLs), one or more storage
clients that can extract data from external sources, a data aggregator to
aggregate data when using multiples sources, field mappers, property mappers and
optional data processors to correctly map raw data into Drupal fields.
Additionally, it is possible to attach an annotation entity to each external
entity to add local annotations on external content.

From a technical perspective, an external entity type only stores the structure
of an external entity on Drupal. It contains the plugin configurations
(aggregator, storage clients, field mappers, property mappers, data processors)
and the field settings (definition and display settings). No external data is
stored on the Drupal side: all is dynamically retrieved from the data sources
when an external entity is loaded. However, when using annotations, those are
stored on the local Drupal instance as content entities.

### Multiple storage client aggregation

#### Aggregation modes

When combining data from multiple storage client sources into one entity, three
use cases are possible:

1. each data source adds properties to the entities of the previous source(s),
  either because the entity data from both sources share the same identifier or
  the value of a specified field of the new data source entity matches the
  value of a specified field from the previous sources (ie. "join").

2. each data source brings its own set of entities. Therefore, entity
  identifiers should all differ from one data source to another. If it is not
  the case, data source entity identifiers could be prefixed by xnttmulti
  using a "virtual group prefix" on a data source-basis making each identifier
  unique (ie. source A as an entity with id '001', source B as a different
  entity that also uses the id '001'; xnttmulti can virtually prefix A ids with
  'A' and B ids with 'B' which would lead to 2 distinct identifiers: 'A001' and
  'B001' which clearly identifies each source).

3. a mix of the 2 previous cases.

This module offers different approaches to deal with those 3 cases but with
some restrictions. Those restrictions are here to solve some technical aspects:
 - being able to efficiently count and filter external entities without needing
   to query all the data from all the sources and process all of them each time.
 - being able to clearly identify the corresponding element in each data source
   given an identifier without any ambiguity.

For the first case, this module assumes the first data source holds ALL the
entity identifiers and can be used for paging management. If an identifier is
not present in the first data source but in one (or more) of the next data
sources, the corresponding entity will be discarded. For filters, only
reference sources are used (at the moment).

For the second and third cases, a "group prefix" must be provided to every set
of data in order to discriminate data source according to the given identifiers.
For instance, if we have 2 data sources A and B, we need to know that the
identifier '001' corresponds to an entity stored in A or B. Therefore, either
one of the 2 sources uses a prefix like '0' (or '00') and can be clearly
identified (which might be necessary when creating a new entity instance to
avoid duplicating it in the 2 sources) or the sources have been assigned a
virtual group prefix that will not be stored (nor known by the data sources) but
only used by xnttmulti to discriminate between sources. We call it a "group
prefix" because a same prefix could be used by multiple data sources in order to
aggregate them together like in the first use case.

The first data source of each group will be considered as the group "reference"
and processed just like in the first use case for aggregation and paging
management. When group prefixes are used, any entity from data sources with or
without group prefix will only be considered and aggregated if its identifier or
join field value matches an existing entity from previous sources. Group
prefixes may be virtual (stripped and not communicated to the data source) or
physical (part of the identifier provided by the data source) but all group
prefixes must be distinct (no overlap): you can't use the prefix 'AB' on one
data source and the prefix 'ABC' on another for a same external entity type
using xnttmulti since 'AB' is also a part of the prefix 'ABC'.

There is a special case when the first(s) data source(s) is (are) not using a
group prefix while other sources have one. Then, xnttmulti will use the first
use case behavior and only use the next specified group as filters on each data
source that has a group.

It is possible to specify more than one group prefix to each data source by
separating them with a semicolon. It might be convenient when some data sources
are common to other distinct sources. For instance, a data source A and a data
source B may contain distinct entities while a data source C may contain
additional data for both; then A would use the prefix group "A", B the prefix
group "B" and C would use "A;B".

If a data source uses multiple *virtual* prefixes (ie. not part of the "real"
identifier), each of its entities will be duplicated for each prefix. For
instance, if a data source C contains an entity '001' and uses the virtual group
prefixes "A;B", xnttmulti will consider that entity '001' has 2 copies: 'A001'
and 'B001' (it virtually doubles the number of entities).

When an entity is aggregated from several data sources (same identifier or with
join), it is possible to choose the way to merge the last data source:
- either without override or only new fields are added
- or with override and any field from the last source will replace previous
  existing values, except for the entity identifier field or its join fields
- or as a sub-object which will be hold in a new user-defined field name not
  existing in the sources.

#### Joins

Sometimes, the identifier field has different names on different sources,
sometimes a different field should be used to join a record from a data source
to the corresponding record in another "joined" source.

To manages those cases, this module offers 2 setting fields and different
behavior depending on the way they are filled; the first field is "Previous
storage clients field name that provides the identifier value for this storage
client (join field)", which we will call "join field", and the field "Source
(id) field to use to join data", which we will call "source id" that correspond
to a field of the joined source that will be used as id field for that source.

If both "join id" and "source id" are left empty, xnttmulti assumes the default
identifier field must be available on the joined data source and will match it.
If both "join id" and "source id" are filled, xnttmulti will use the field value
of "join id" field from the previous sources as the identifier value to
use to match "source id" field in the new data source.
If "join id" is filled but "source id" is empty, xnttmulti assumes "source id"
field is the same as the one specified for "join id" and proceed just like when
the "join id" and "source id" are filled.
If "join id" is empty but "source id" is filled, xnttmulti will match the
default identifier provided by previous sources with "source id" field against
the new data source (and it will apply prefix virtualization if needed).
Note: when "join id" is filled, the group prefix will not be used to filter data
on the new source; it will only be used to know if the source should be
aggregated into a given group.

### Saving

- Non-mapped source field values are left "as they are".
- Drupal fields mapped to constant values are not saved.
- Multiple values for a given source field are replaced by the Drupal values.
- Mapped Drupal fields that have empty values replace/clear source values.
- Drupal fields mapped to non-existing source fields are saved (if the source
  storage takes them into account).
- Source associative arrays which values are mapped to a Drupal field (with
  multiple values) but keys are not mapped (it can depend on the field mapper
  used) will result on original key-value pairs being maintained while new
  values are added using regular numeric indexation. This would result in
  duplicate values (with different keys).

### Creating external entity types programmatically

It is possible to create external entities programmatically with constraints.
See companion example modules in the "modules" directory for examples.
You can either create external entity types using YAML config files in your
module "config/install/" directory or create them by code in your module install
file (in hook_install()). See DEVELOPMENT.md for more details.


## Security

As External Entities provides a way to expose external data into a Drupal site,
it is important to understand that the external data sources may be compromised
(in terms of security) or contain untrusted data. Exposing such data on a
Drupal site may also compromise the Drupal site. Just as a basic example, you
can consider an external entity REST storage client that is used to display
content of external pages. If that content is not filtered from the Drupal side,
it may contain Javascripts that could be used to intercept credentials used on
the Drupal site, execute operations using current user credentials or even trick
the user to make him/her provide information he/she should not.

One should also consider the reverse problem: if a Drupal external entity
storage client is used to access external data and is able to alter them, it
would be possible to corrupt external data or access to external private data if
the storage client is not secured properly.

Therefore, it is the external entities administrator concern to take those
security aspects into account, configure the storage client properly and
restrict access to sensible external data.

It is obvious but worse mentioning it: people allowed to create external entity
types can have access to the system's private data. It is possible to create an
external entity type that would display the content of Drupal's "settings.php"
file and expose the database credentials as well as accessing other sensible
files. Using an SQL storage client would also enable to perform any type of
query on Drupal database. Therefore, the Drupal site administrator should only
provide permission to manage external entity types to trusted users.


## Performances

The current implementation of External Entities is not focused on performances
but rather of possibilities and performances may be improved in the future. At
least, External Entities enables to query multiple external sources once to
gather data for a single external entity unlike other existing plugins
([External Data Source](https://www.drupal.org/project/external_data_source),
[Tripal](https://www.drupal.org/project/tripal)) would query them one time for
each entity field.

Performances are also closely related to storage clients. While a REST storage
client might be slow to load entities, a file or an SQL storage client could be
pretty fast depending on their configuration. To mitigate the problem, External
Entities module provides support for entity caching at the cost of having
possibly unsynchronized data. Storage clients may also include features to
reduce entity loading; for instance, if the "list" endpoint of a REST storage
client already provides fully loaded entities, the storage client will not need
to use the single entity loading endpoint if the "list" endpoint was just used
before and fetched the requested entities.

If the solutions above do not meet your needs, you may also consider the
(External Entity Manager)[https://www.drupal.org/project/xnttmanager] which
provides features to turn external entities into local Drupal content (with
duplicated data that may need to be synchronized). It also offers features to
compare local and remote data and synchronize data manually or automatically
using a cron.

Finaly, the External Entities Views plugin which allows to use external entities
in the Drupal "views" system may work well with local data from file or SQL
storage clients but not so well with REST services, especially when data
filtering is involved. Therefore, it might be more appropriate to use the
[Search API](https://www.drupal.org/project/search_api) module to index remote
data and use it in views.


## Native plugins

This module ships with several native plugins and plugin interfaces described
hereafter:
- Data aggregators (DataAggregatorInterface)
  - Single Storage Client (no aggregation)
  - Group Aggregator
  - Horizontal Data Aggregator
  - Vertical Data Aggregator
- Storage clients (StorageClientInterface)
  - Main interfaces:
    - REST storage client (RestClientInterface)
    - Files storage client interface (FileClientInterface)
    - Query Language storage client interface (QueryLanguageClientInterface)
  - Implementations:
    - REST storage client
    - JSON:API storage client
    - Files storage client
    - SQL storage client (companion module)
- Field mappers (FieldMapperInterface)
  - Generic field mapper
  - Text field mapper
  - File and image field mapper (companion module)
- Property mappers (PropertyMapperInterface)
  - Constant property mapper
  - Field property mapper
  - Simple property mapper
  - JSONPath property mapper
- Data processors (DataProcessorInterface)
  - Autodetect datatype
  - Boolean
  - Date and time
  - Hash
  - Numeric value
  - Numeric value with unit
  - String case
  - Value mapping
  - Version

### Single Storage Client

This data aggregator does not aggregate data but rather provides a simple
interface when working with a single storage client.

### Group Aggregator

This aggregator enables the aggregation of multiple storage client data into one
data structure. It supports grouping/separation of sources as well as a control
on how data elements are merged together.

For instance, with this plugin, it is possible to add the entities of multiples
sources into one bigger set of entities as well as aggregating data of one
source "A" to another one "B" and merge their fields to generate virtual
hybrid entities that contain data from both "A" and "B" sources.

For more details, see section "How it works / How to" before.

### Horizontal Data Aggregator

This data aggregator cumulates entities from multiple data sources into a global
set. For instance, if we consider 2 storage client data sources, A with 5
entities, and B with 3 entities, the result will be one set with 8 distinct
entities.

### Vertical Data Aggregator

This data aggregator merges content of entities from multiple data sources into
corresponding entities of a master source. For instance, if we consider 2
storage client data sources, A with 5 entities, and B with 3 entities, and only
2 entities of B share a same identifier with entities of A, the result will be
one set with 5 entities containing 2 entities that were merge between an A
entity and a B entity. One entity of B will not be represented in that set.

It is possible to perform more complex merges/joins using merge configuration
features (ie. joining on other fields than identifiers, merge more than one
element from a secondary source using a custom sub-field to hold the set, etc.).

### Storage client interfaces

The provided 3 storage client interfaces should be use as base for storage
client implementations. The "REST" interface is dedicated to web services, the
"files" interface is dedicated to storage client working either with file
systems or file content, with data records either stored in one or multiple
files, and the "query language" interface deals with query languages such as
SQL databases, noSQL storages, SPARQL (RDF) and such.

All those 3 interfaces come with a base implementation that can be used to
implement new storage clients. See DEVELOPMENT.md for more details. You may also
find existing extensions that extend those base classes: see
"Recommended modules" section for examples.

### REST storage client

The REST storage client provides a full featured support to RESTful web
services. The base class can be extended for specific client needs and/or to
simplify the configuration interface.

### JSON:API storage client

It is derived from the base REST storage client and enable the use of REST
services provided by Drupal JSON:API module. It supports field filtering.

### Files storage client

Enables the use of local files as entities. Available fields are file properties
(name, size, modification date, etc.) and parts of file path (when using
path structure patterns).

### SQL storage client ("xnttsql" companion module)

Turns SQL (MySQL/PostgreSQL) queries into external entities. An SQL column
becomes an entity field. It requires "Database Cross-Schema Queries" Drupal
extension to work with external databases. External database credentials must be
set in Drupal site "settings.php" file for security reasons.

### Generic field mapper

Provides a generic interface to map any field type.

### Text field mapper

Provides an interface to map formatted text field types with simplified
selection for text format.

### File field mapper ("xntt_file_field" companion module)

Allows to map external and local file URIs provided by external sources to file
or image fields just like if they were local. Image filters/styles can then be
used just like on any other regular image field.

### Constant property mapper

Maps constant values to field properties.

### Field property mapper

Maps a source field name directly to an external entity Drupal field name. This
property mapper does not support complex mapping expressions and should only be
used for 1-to-1 field (property) mapping. It can be used when a source field
name uses special characters that could be interpreted by other property
mappers as special expressions like dots (".") for instance.

### Simple property mapper

The simple property mapper provides a simple way to map source sub-fields or
multiple value fields to Drupal field properties. The syntax is quite simple: a
dot "." is used to go down into the data structure and the jocker sign "*" is
used to map a set of values to a Drupal field property.

Note: in previous version of External Entities module, the slash "/" was used to
separate sub-fields. This syntax has been changed to dots for multiple reasons:
- more similar to JSON Path to more easily switch from one property mapper to
  the other.
- it is a common syntax used in computer languages.
- it simplifies the code of filtering queries.

### JSONPath property mapper

The JSONPath property mapper provides more flexibility to map complex structures
to Drupal field properties using the standardized
[JSONPath](https://goessner.net/articles/JsonPath/) structure.

### Autodetect datatype data processor

In some cases, a Drupal field property requires a certain type of data while the
source data values are often just "strings". This data processor detects the
data type required by the field and tries to cast the source value to that
required type to avoid Drupal errors.

### Boolean data processor

This data processor can translate many types of source values into Booleans.
Values evaluated to false are: "the empty string", 0, false, null, nul, nil,
undef, empty, no, nothing, none, zero, and "-" (all are case insensitive).
Other non-empty values are evaluated to TRUE.

### Date and time data processor

This data processor handles date strings in many formats and adapt their values
to any Drupal field property type (Date-time field properties but also text or
integer field properties).

### Hash data processor

This data processor can compute a hash value from a source field value using
the selected hash algorithm. The hash result can then be used in a Drupal field
property.

### Numeric value data processor

This data processor can be used to extract the numeric part of a value in a
string, either at the beginning or the end of a text. Supported numeric values
are signed/unsigned integers, floating points, and can use scientific notation.
The rest of the text can be maintained when saving the numeric value.

### Numeric value with unit data processor

This data processor also works with numeric values, like the numeric value data
processor, but can be used to take into account units and includes unit
conversion features. Source values can use different units while those values
would be converted by this data processor to a selected common one.

### String case data processor

This data processor enables changing the character case of a source text.
Multiple options are available such as upper or lower casing everything or
using camel case notation.

### Value mapping data processor

This data processor can be used to map a source value to another specified one.

### Version data processor

This data processor is dedicated to handle (software) versioning and extract
the required version part(s) of a source string.


## Companion modules

This module ships with several companion modules described hereafter:
- External Entities pathauto (external_entities_pathauto)
- External entity type Drupal 7 import example (xntt_example_d7import)
- External entities file and image fields support (xntt_file_field)
- External Entities SQL Database Storage Client (xnttsql)

### External Entities pathauto (external_entities_pathauto)

Defines automatic aliases for external entities with the
[Pathauto](https://www.drupal.org/project/pathauto) module.

### External entity type Drupal 7 import example (xntt_example_d7import)

Example module that demonstrates how to create an external entity type
programmatically. The provided external entity type has been designed to map
Drupal 7 content exposed using the Drupal 7
[RESTful Web Services](https://www.drupal.org/project/restws) module. It is
customizable: fields can be added, mapping can be adjusted and the JSON:API REST
endpoint can be configured.

With this module, one can display Drupal 7 content under a Drupal 9+ site and
even import the data physically using the
[External Entity Manager](https://www.drupal.org/project/xnttmanager) module
content synchronization feature.

### External entities file and image fields support (xntt_file_field)

This module provides support for Drupal file and image fields in external
entity field mapping. Indeed, such fields should be mapped to internal Drupal
managed file identifier while on the source side, only a file URI is available.
This module allows to map file URIs to file and image fields and use all the
features available for those fields (field formatters, image styles, etc.).

Since this module uses a couple of tricks that relies on a custom stream
wrapper ("xntt://" custom stream), it is provided as a companion module that can
be easily disabled in case of issues.

### External Entities SQL Database Storage Client (xnttsql)

This module provides an SQL database storage client but is not natively
integrated in external entities default storage clients because it relies on an
external module
[Database cross-schema query API](https://www.drupal.org/project/dbxschema) that
would introduce an unnecessary dependency for people not using that client.

It supports MySQL and PostgreSQL databases but relies in fact on what
Database cross-schema query API supports. To use external databases, you must
define their access settings in your Drupal site "settings.php" file in the
"$databases" array. See Database cross-schema query API documentation for
configuration details.

An SQL database storage client provides external entities through SQL queries.
Queries correspond to Create, Read, Update, Delete, List and Count. Raw data
fields correspond to the Read query column names, therefore it is possible to
use very complex queries to return a data model with custom field names not
necessary corresponding to table columns. However, it will complexify the field
mapping for data filtering and data saving.


## Recommended modules

- [External Entity Manager](https://www.drupal.org/project/xnttmanager), which
allows to inspect available external fields and their mapping, to synchronize
local data with external data and auto-generate associated annotation entities.

- [Search API](https://www.drupal.org/project/search_api), when enabled
it is possible to make overviews of external entities.

- Other contributed modules implementing storage clients:
  - REST:
    - [BrAPI Client](https://www.drupal.org/project/xnttbrapi)
    - [Wiki Client](https://www.drupal.org/project/xnttwiki)
  - Files:
    - [Image Files with exif data](https://www.drupal.org/project/xnttexif)
    - [Media files](https://www.drupal.org/project/xnttmedia)
    - [JSON files](https://www.drupal.org/project/xnttjson)
    - [TSV/CSV files](https://www.drupal.org/project/xntttsv)
    - [XML files](https://www.drupal.org/project/xnttxml)
    - [YAML files](https://www.drupal.org/project/xnttyaml)
  - SQL:
    - [Chado database schema](https://www.drupal.org/project/chadol)

Find more contributed modules at the
[External Entities plugins page](https://www.drupal.org/docs/contributed-modules/external-entities/external-entities-plugins)


## Maintainers & Credits

Current maintainers:
- v2
  - Raf Philtjens - [rp7](https://www.drupal.org/u/rp7)
  - Clemens Tolboom - [clemens.tolboom](https://www.drupal.org/u/clemenstolboom)
  - Hanno Lans - [Hanno](https://www.drupal.org/u/hanno)
  - Rodrigo Aguilera - [rodrigoaguilera](https://www.drupal.org/u/rodrigoaguilera)
  - Jelle Sebreghts - [Jelle_S](https://www.drupal.org/u/jelle_s)
  - Patrick van Efferen - [pefferen](https://www.drupal.org/u/pefferen)
  - Peter Grond - [pgrond](https://www.drupal.org/u/pgrond)
- v3
  - Andrii Yasynchuk - [velmir_taky](https://www.drupal.org/u/velmir_taky)
  - Valentin Guignon - [guignonv](https://www.drupal.org/u/guignonv)
  - Raf Philtjens - [rp7](https://www.drupal.org/u/rp7)

This project has been sponsored by:
- Attiks (Peter Droogmans) is no nonsense company with a highly skilled
  technical and graphical staff. We handle both on- and offline communication,
  web development and print design are our middle names. We work
  internationally and are based in Antwerp, Belgium.
- Datascape (Hanno Lans) is involved in Drupal projects with an advanced
  information architecture, with accessibility, open content and open source in
  mind.
- Kunstmuseum The Hague has a leading collection of modern and contemporary art,
  fashion and decorative arts. It is also the international 
  home of Piet Mondrian,
  with no fewer than 300 works by the famous Dutch artist in its collection.
-The Alliance Bioversity - CIAT (guignonv), a CGIAR center.

Credits (for v3 in addition to maintainers):
- Abhishek Gupta - [abhishek_gupta1](https://www.drupal.org/u/abhishek_gupta1)
  - Issue #3455366
- Antonio De Marco - [ademarco](http://nuvole.org/)
  - Issue #3479118
- [arousseau](https://www.drupal.org/u/arousseau) ([insite.coop](https://www.insite.coop))
  - Issue #3486164
- Joachim Noreiko - [joachim](https://www.drupal.org/u/joachim)
  - Issue #3374867
- [mortona2k](https://www.drupal.org/u/mortona2k)
  - Issue #3449832
  - Issue #3049317

### AI-Assisted Contributions
Some tests were added and some issues were solved using AI agents:
- Claude.
- ChatGPT.
- Mistral.
