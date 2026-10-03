<?php

namespace Drupal\external_entities\Entity\Query\External;

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * The external entities storage entity query interface.
 */
interface ExternalQueryInterface extends QueryInterface {

  /**
   * Provides the array of unhandled filters after a query execution.
   *
   * Unhandled filters are those that could not be processed by the storage
   * client(s) or the data aggregator.
   *
   * @return array
   *   An empty array if all filters were handled, otherwise an array of
   *   unhandled filters with 4 optional keys:
   *   - parameters: array of unhandled filter conditions if some.
   *   - sorts: array of unhandled sort conditions if some.
   *   - start: the "start" key is TRUE if the offset cannot by applied.
   *   - length: the "length" key is TRUE if the length option could not be
   *     applied.
   *   Note: for both "start" and "lenght" keys, they are set regardless the
   *   number of elements really available; it is not a problem of parameter
   *   values but a problem of handling those parameters.
   */
  public function getUnhandledFilters() :array;

}
