<?php

namespace Drupal\proc\EventSubscriber;

use Drupal\proc\Event\ProcElementEvent;
use Drupal\proc\Event\ProcEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Protected Content event subscriber example.
 */
class ProcSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[ProcEvents::PROC_ELEMENT_SETTINGS][] = ['changeProcElementSettings'];

    return $events;
  }

  /**
   * Change Proc field element.
   *
   * @param \Drupal\proc\Event\ProcElementEvent $event
   *   The event object.
   */
  public function changeProcElementSettings(ProcElementEvent $event): void {
    // Change the fetcher endpoint.
    // $url = 'https://www.example.com';
    // $element = $event->getElement();
    // $element->configuration['#proc-settings']['fetcher_endpoint'] = $url;
    // $event->setElement($element);
    // $event->stopPropagation();
  }

}
