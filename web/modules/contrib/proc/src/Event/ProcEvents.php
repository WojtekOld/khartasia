<?php

namespace Drupal\proc\Event;

/**
 * Defines events for the proc module.
 *
 * @ingroup proc
 */
final class ProcEvents {

  /**
   * Name of the event fired when a proc element is processed.
   *
   * This event allows modules to change the proc element settings, including
   * its endpoint for fetching recipients.
   * The event listener method receives a \Drupal\proc\Event\ProcElementEvent
   * instance.
   *
   * @Event
   *
   * @see \Drupal\proc\Event\ProcElementEvent
   *
   * @var string
   */
  const PROC_ELEMENT_SETTINGS = 'proc.proc_element_settings';

}
