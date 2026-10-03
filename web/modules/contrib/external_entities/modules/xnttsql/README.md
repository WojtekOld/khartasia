External Entities SQL Database plugin
=====================================

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * FAQ
 * Maintainers

INTRODUCTION
------------

Using the Database Cross-Schema Query API extension, this module enables the use
of extrnal data stored in either other schemas or other databases (even using
different drivers than the current Drupal database) as Drupal entities.

In other words, with a simple SQL query (with a few syntax adjustments), you
generate a data (storage) model which fields correspond to the returned columns.
That data model can be mapped through this extension to a Drupal external entity
type and treated just like any other type of content.

For technical reasons, you will need to provide at least 3 queries ("READ",
"LIST" and "COUNT") to access data in read only mode and you may provide 3 other
set of queries ("CREATE", "UPDATE" and "DELETE") to be able to write data.

This module has currently been tested with PostgreSQL and MySQL but it might
work with other drivers once they got implemented for dbxschema module.

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/external_entities

 * To know more about the Database Cross-Schema Query API extension:
   https://www.drupal.org/project/dbxschema


REQUIREMENTS
------------

This module requires the following modules:

 * [External Entities](https://www.drupal.org/project/external_entities)

 * [External Entities](https://www.drupal.org/project/dbxschema)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

 * You may have to setup other database connections: refer to the dbxschema
   documentation to do so.

 * You will have to enable at least one dbxschame database driver.

CONFIGURATION
-------------

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will add a new storage client for external entity types.

When you create a new external entity content type, you should be able to select
"Database" as storage client (in the "Storage" section). From this interface,
you can set you SQL queries.

The "Connection settings" allows you to select a secondary database and/or a
different schema name. There are many different use cases:
- if the data you want to select is stored in the same database as the Drupal
  one, you have nothing to change there.
- if the data is stored in the same PostgreSQL database as the Drupal one but in
  a different schema *OR* if the data is stored in a different MySQL database
  from the Drupal one but they share the same MySQL server and credentials, then
  you will just have to specify the schema name for PostgreSQL or the MySQL
  database name as a "schema name" for MySQL. Note: you can also access more
  than one schema (PostgreSQL) or database (MySQL) at a time by specifying more
  schemas.
- if the data is stored in an different database for PostgreSQL or an external
  database for MySQL (different server and/or different credentials), you will
  have to specify the connection key (set in settings.php, see dbxschema doc.)
  in the "Secondary database key name" field. Note: you may still also specify
  other schemas (PostgreSQL) or database (as "schema names" for MySQL).

The "Placeholders" section is optional and provided to simplify query writting
and maintenance. For instance, instead of using static "hard coded" identifiers
at one or more places in queries, it is possible to use a placeholder instead
and have this placeholder replaced dynamically in each query by its
corresponding value. That value is the result of the SQL query provided for the
placeholder. Therefore, it is possible to use a static constant such a
"SELECT 42" for the value 42 but also get that value from a table like in
"SELECT id FROM {1:some_table} WHERE name = 'h2g2';". It is also possible to
specify a set of values by adding square brackets to the placeholder name. For
instance ":red_cars[]" with the query
"SELECT car_id FROM {1:car} WHERE color = 'red'". Then, this placeholder could
be used in entity queries the following way:
"SELECT p.people_id, p.name, p.age
 FROM {1:people} p
   JOIN {1:people_car} pc ON
     pc.people_id = p.people_id
     AND pc.car_id IN (:red_cars[])
 WHERE p.people_id = :id"
Using placeholders based on SQL queries is also a safe way to use the same query
accross several schema/databases. In our previous example, the id corresponding
to 'h2g2' could be 42 in a database and 806 in another. But while keeping the
same entity queries and the same placeholder queries, it is still possible to
change the connection settings and keep a same behavior on both similar
databases even if the identifiers are different: when the database is changed in
the connection settings, the placeholder are updated accordingly.

The "CRUD + List/Count" section is the main part of the external entity
definition. It contains all the SQL queries used to interact with the database
and map external entity values to table columns. Each "CRUD" query can contain
more than one SQL statement (separated by ";") if needed.

3 queries are mandatory: READ, LIST and COUNT. However, in some simple cases,
the LIST and COUNT queries can be auto-generated from the READ query if they are
not explicitely provided. The CREATE, UPDATE and DELETE queries are only
required if you want to have writable entities (ie. not just read-only). It is
possible to only provide some of them in order to limit possibilities (ie. only
allow creation, or only allow update, etc.).

The READ query can be complex with joins and will return the wanted fields named
using "AS" clause (otherwise, the table field names will be used). Returned
field names starting with "array_" will be parsed as database arrays and will be
turned into array of values (ie. mapping fields should have a cardinality
greater than 1). The query must contain a placeholder ":id" refering to the
object identifier which field is aliased as "id". The ":id" placeholders used in
queries must be used as right operands with either "= :id" or "IN (:id)"
operators. Fields names should be distinct, contain only alphanumeric and
underscore characters and must not start with a number.

Ex.:
```
  SELECT
    s.stock_id AS "id",
    s.name AS "name",
    s.uniquename AS "uniquename",
    cv.name || ':' || cvt.name AS "type_name"
  FROM {1:stock} s
    JOIN {1:cvterm} cvt ON cvt.cvterm_id = s.type_id
    JOIN {1:cv} cv ON cv.cv_id = cvt.cv_id
  WHERE s.stock_id = :id;
```
When multiple queries are used, new field values are added to the entity and
existing ones are replaced by the last queries.

For the LIST query, there must be only one statement for listing. The query must
return object IDs and names and must NOT include "ORDER BY", "LIMIT" and
"OFFSET" clauses which will be appended automatically to the query by the
system. It should include a placeholder ":filters" (in a WHERE clause) to
indicate where filters could be added by the system in order to filter the list.
Since the list query may be used without filters, the system should
automatically remove the "WHERE" keyword if it is followed by nothing. If it
fails for your specific query, consider adding a simple "TRUE" keyword after the
"WHERE": `... WHERE TRUE :filters;`
When you use aliased columns (ie. `SELECT ... AS "..."`) you must specify their
mapping in the "Filter mapping overrides" section below. For instance, if you
used `SELECT ... field3 AS "yet_another_field_name"...` in the READ query, you
will have to add a mapping for the field "yet_another_field_name" that will
contain the value "field3".

The COUNT query must follow the same restrictions as the LIST query, the only
difference is that the query must return just one integer as "count".

For the CREATE query, each SQL statement must contain the fields it suppors
using placeholders ":<field_machine_name>".
For instance, to insert a stock that has "name"="My Stock",
"uniquename"="MYSTOCK001" and "type_name"="stock_types:my type" (using the
format "cv.name:cvterm.name") fields mapped, use:
```
    INSERT INTO {1:stock} (name, uniquename, type_id)
    SELECT :name, :uniquename, cvterm_id
    FROM {1:cvterm} cvt
      JOIN {1:cv} cv USING (cv_id)
    WHERE
      cvt.name = substr(:type_name, strpos(:type_name, ':') + 1)
      AND cv.name = substr(:type_name, 1, strpos(:type_name, ':') - 1)
    LIMIT 1;
```
The last SQL query MUST return the created item identifier value aliased as
"id". For PostgreSQL, it would be the last sequence value:
```SELECT currval('<name_id_seq>') AS id;```
and for MySQL it would be:
```SELECT last_insert_id() AS id;```

For the UPDATE query, each SQL query must contain the fields it supports using
placeholders ":<field_machine_name>".
For instance, to update a stock that has "name", "uniquename" and "type_name"
(using the format "cv.name:cvterm.name") fields mapped, use:
```
  UPDATE {1:stock} SET
    name = :name,
    uniquename = :uniquename,
    type_id = (
      SELECT :name, :uniquename, cvterm_id
      FROM {1:cvterm} cvt
        JOIN {1:cv} cv USING (cv_id)
      WHERE
        s.stock_id = :stock_id
        AND cvt.name = substr(:type_name, strpos(:type_name, ':') + 1)
        AND cv.name = substr(:type_name, 1, strpos(:type_name, ':') - 1)
      LIMIT 1
    )
  ;
```

For the DELETE query, each SQL query must contain the fields it needs using
placeholders ":". There should be the identifier field followed by the "= :id"
placeholder. For instance, to delete a given stock, use:
```DELETE FROM {1:stock} WHERE stock_id = :id;```

Managing CREATE, UPDATE and DELETE queries as set of SQL statements can be quite
challenging. To mitigate the risks of unexpected behaviors, it might be wise to
implement those features using embeded SQL procedures/functions. Then a simple
call would be performed and all the tests and logic would be handled on the
database side. For instance, a CREATE query could be simplified to calling a
home-made SQL function that would return the new identifier:
```SELECT create_my_entity(:id, :some_field, :some_other_field) AS "id";```

The "Filter mapping overrides" section is only needed when using complex SQL
queries with column aliases. In that case, the system will have hard time to
filter external entities on field values since their name do not correspond to
a table column name. The filter mapping overrides can solve this problem by
letting the admin define how to match a field against the database.
For instance, if you have a READ query like that:
"SELECT SUBSTR(somefield, 3, 10) AS "toto", ..." and the "toto" column is mapped
to a "field_tutu" Drupal field, when the system will try to filter all entities
having field_tutu equal to "something", it will try to add to the SQL "WHERE"
clause the condition " AND toto = 'something' " but since the "toto" column is
an alias, it will try to guess the used expression but will fail as it is too
complicated to parse and guess. The filter query will fail with an error saying
the "toto" field does not exist (or something similar). However, if the "toto"
column is mapped in the "Filter mapping overrides" to something like
"SUBSTR(somefield, 3, 10)", then the filter query will be using
" AND SUBSTR(somefield, 3, 10) = 'something' " wich will not be efficient for
sure but will work. If the efficiency is a problem in that case, consider using
materialized views with simple column name not aliased.

TROUBLESHOOTING
---------------

Don't forget to read the logs. There should be helpfull messages there. You may
also enable the "debug mode" on the related external entity type.

FAQ
---

Q: Can I have my Drupal site running on a MySQL server and use external data
from a PostgreSQL server and vice versa?

A: Yes. That's one purpose of this module.

Q: Can I gather data from multiple databases into one single external entity
type?

A: Yes. There will be some limitation with just this module as it can only
gather data from a same connection (ie. it can work with multiple schemas on
PostgreSQL or multiple database on MySQL if the use the same server and
credential). To work with any set of database connections, you will have to
setup multiple storage sources using this storage client.

Q: Is it possible to read data from one schema (or DB for MySQL) and write it
into another?

A: Yes. You will just have to make the CREATE, UPDATE and DELETE queries work on
the other schema (or DB for MySQL). An additional module may help in that
process:
- External Entities Manager that can be used to make massive operations (ie.
  save all the external entities in batch automatically).

MAINTAINERS
-----------

Current maintainers:
 * Valentin Guignon (guignonv) - https://www.drupal.org/u/guignonv
