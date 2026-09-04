<?php

namespace Drupal\proc;

/**
 * An interface for all ProcRelabelling type plugins.
 */
interface ProcRelabellingInterface {

  /**
   * Provide a description of the proc relabelling plugin.
   *
   * @return string
   *   A string description of the proc relabelling plugin.
   */
  public function description(): string;

  /**
   * Relabel a proc entity.
   *
   * Relabel a proc entity when the host entity form is submitted.
   *
   * @param array $context
   *   An array of metadata to feed the relabelling pattern, containing:
   *   - element: The form element where the proc entity is added
   *   - form_state: The form state of the host entity form
   *   - host_entity: The host entity object
   *   - proc_id: The proc ID for relabelling
   *   - proc_key: The delta key of the proc entity
   *   - proc_field: The machine name of the source field.
   *
   * @return string
   *   The new label.
   */
  public function relabel(array $context): string;

}
