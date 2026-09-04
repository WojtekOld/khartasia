<?php

declare(strict_types=1);

namespace Drupal\sophron_guesser;

use Drupal\Core\File\MimeType\MimeTypeMapFactory;
use Drupal\Core\File\MimeType\MimeTypeMapInterface;
use Drupal\sophron\MimeMapManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Factory for creating the MIME type map.
 */
class SophronMimeTypeMapFactory extends MimeTypeMapFactory {

  public function __construct(
    EventDispatcherInterface $eventDispatcher,
    protected readonly MimeMapManagerInterface $mimeMapManager,
  ) {
    parent::__construct($eventDispatcher);
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreateMap(): MimeTypeMapInterface {
    return new SophronMimeTypeMap($this->mimeMapManager);
  }

}
