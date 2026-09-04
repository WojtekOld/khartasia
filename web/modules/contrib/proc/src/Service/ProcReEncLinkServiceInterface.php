<?php

namespace Drupal\proc\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for the Proc Re-encryption Link service.
 */
interface ProcReEncLinkServiceInterface {

  /**
   * Add Request Re-encryption Link.
   *
   * Adds a link (render array element) into a form that allows requesting
   * re-encryption for one or more protected proc entities found in the form's
   * fields. The method inspects entity reference widgets in $form to collect
   * proc entity IDs that have non-empty `field_wished_recipients_set` and, if
   * any are found, it appends a link render element which opens a modal to
   * confirm the re-encryption request.
   *
   * @param array &$form
   *   The form render array to modify (passed by reference).
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity the form represents.
   * @param array $link_class_attribute
   *   Optional additional class(es) to add to the generated link's class
   *   attribute.
   * @param int $weight
   *   Weight to apply to the generated link element.
   *
   * @return void
   *   The function modifies $form in-place.
   */
  public function addReEncLink(array &$form, EntityInterface $entity, array $link_class_attribute = [], int $weight = 0): void;

}
