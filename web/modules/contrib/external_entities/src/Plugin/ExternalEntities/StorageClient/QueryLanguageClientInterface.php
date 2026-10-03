<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Interface for external entities storage client using query languages.
 *
 * This interface defines the client methods required to work with query
 * language storage sources. The concept is that a query language source uses
 * queries to create, read, update, delete (CRUD), list and count records that
 * would represent external entities.
 * It usualy works with a server that processes queries and return resutls.
 * The server might store more than just entity records, like any other kind of
 * values that may be queried and also used in other queries.
 *
 * Therefore, this interface defines generic server connection methods,
 * methods to handle placeholders in queries and methods to manage queries. It
 * should be suitable to work with SQL database servers and other type of
 * database servers (MongoDB, ElasticSearch, Neo4J, SPARQL, etc.).
 */
interface QueryLanguageClientInterface extends StorageClientInterface, PluginFormInterface {

  /**
   * Sets the value of a placeholder.
   *
   * @param string $name
   *   Placeholder name including its ':' prefix (and possibly a '[]' suffix).
   * @param mixed $value
   *   Value to use.
   *
   * @return self
   *   Returns current instance.
   */
  public function setPlaceholderValue(string $name, $value) :self;

  /**
   * Returns the value of the given placeholder for current entity type.
   *
   * @param string $placeholder_name
   *   The qualified placeholders name (ie. inclding ':' prefix and possible
   *   '[]' suffix).
   *
   * @return mixed
   *   The corresponding value (string, int, array, etc.).
   */
  public function getPlaceholderValue(string $placeholder_name) :mixed;

  /**
   * Returns placeholder values for the given query and current entity type.
   *
   * This method can be used either to retrieve all available placeholder values
   * as well as values of a set of placeholders mixed in a string (query) or the
   * the value of a single placeholder given as the $query parameter.
   *
   * @param ?string $query
   *   A query containing placeholders of the form ':placeholder_name'.
   *   If not empty, only placeholders matching the query will be returned.
   *   If empty, all available placeholders are returned.
   *
   * @return array
   *   An associative array of placeholders as where keys are fully qualified
   *   placeholder names (ie. including ':' prefix and possible '[]' suffix) and
   *   values are the corresponding values (string, int, array, etc.).
   */
  public function getPlaceholderValues(?string $query = NULL) :array;

  /**
   * Replace placeholders in a set of queries.
   *
   * @param array $queries
   *   A set of queries containing placeholders of the form ':placeholder_name'.
   *   The query structure can be more complex than just an array of strings and
   *   each value of the sub-arrays should be processed while keys remain
   *   unchanged.
   * @param array $additional_replacements
   *   Additional replacement patterns, such as ':id[]' or ':filters' that would
   *   override any existing corresponding placeholder name.
   *
   * @return string
   *   The query containing actual replacement values.
   */
  public function replacePlaceholders(
    array $queries,
    array $additional_replacements = [],
  ) :array;

  /**
   * Returns the queries corresponding to the requested type.
   *
   * @param string $query_type
   *   Type of query. Should be one of 'qcreate', 'qread', 'qupdate', 'qdelete',
   *   'qlist', and 'qcount'.
   * @param array $context
   *   An optional context that can be used by derived clients to adapt queries.
   *   Typically, 'caller' key should contain the calling method name and the
   *   'form' key is set to TRUE when called in a form generation process.
   *
   * @return array
   *   An array of corresponding queries.
   */
  public function getQueries(string $query_type, array $context = []) :array;

  /**
   * Performs the given set of queries using placeholders and entity data.
   *
   * Note: depending on the QL server, it placeholder replacement may occur on
   * the server side (usually more secure).
   *
   * @param array $queries
   *   A set of queries.
   * @param array $entity_data
   *   Entity raw data as an associative array using source fields (and not the
   *   Drupal field structure). If queries contain placeholder names that don't
   *   match any of the defined placeholder names nor any of the
   *   $additional_replacements placeholders, $entity_data keys will be
   *   considered as candidate placeholder names without the ':' prefix (or the
   *   possible '[]' suffix). For instance, if a query contains ':xid[]' but no
   *   corresponding placeholder is defined, ':xid[]' will be replaced by
   *   $entity_data['xid'] if it is an array or by [$entity_data['xid']] if not.
   * @param array $additional_replacements
   *   Additional replacement patterns, such as ':id[]' or ':filters' that would
   *   override any existing corresponding placeholder name or entity field.
   *
   * @return array
   *   An empty array if a query failed and non-empty array otherwise. The array
   *   will contain an aggregation of query results keyed by field names. In
   *   case of multiple queries returning the same field name, only the last
   *   value will be kept. If no fields are returned, the array will just
   *   contain the "TRUE" value.
   */
  public function runQueries(
    array $queries,
    array $entity_data = [],
    array $additional_replacements = [],
  ) :array;

}
