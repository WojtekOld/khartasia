<?php

declare(strict_types=1);

namespace Drupal\sophron_guesser_test\EventSubscriber;

use Drupal\sophron\Event\MapEvent;
use FileEye\MimeMap\MapHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modifies the MIME type map using fileeye/mimetype primitives.
 */
class SophronTestEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MapEvent::INIT => 'initializeMap',
    ];
  }

  /**
   * Reacts to a 'sophron.map.initialize' event.
   *
   * @param \Drupal\sophron\Event\MapEvent $event
   *   Sophron's map event.
   */
  public function initializeMap(MapEvent $event): void {
    $map = MapHandler::map($event->getMapClass());
    $map->addTypeExtensionMapping('constellation/orion-belt', 'alnitak');
    $map->addTypeExtensionMapping('constellation/orion-belt', 'alnilam');
    $map->addTypeExtensionMapping('constellation/orion-belt', 'mintaka');
    $map->addTypeDescription('constellation/orion-belt', 'Orion\'s Belt is an asterism in the constellation of Orion');
    $map->addTypeAlias('constellation/orion-belt', 'constellation/three-kings');
    $map->addTypeAlias('constellation/orion-belt', 'constellation/three-sisters');
    $map->sort();
  }

}
