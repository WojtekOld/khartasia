<?php

namespace Drupal\proc\Event;

use Drupal\proc\Element\ProcField;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Wraps a proc element for event subscribers.
 *
 * @ingroup proc
 */
class ProcElementEvent extends Event {

  /**
   * Processed element.
   *
   * @var \Drupal\proc\Element\ProcField
   */
  public ProcField $element;

  /**
   * Constructs a proc element event object.
   *
   * @param \Drupal\proc\Element\ProcField $element
   *   The processed proc element.
   */
  public function __construct(ProcField &$element) {
    $this->element = $element;
  }

  /**
   * Get the element.
   *
   * @return \Drupal\proc\Element\ProcField
   *   The proc element.
   */
  public function getElement(): ProcField {
    return $this->element;
  }

  /**
   * Set the element.
   */
  public function setElement(&$element): void {
    $this->element = $element;
  }

}
