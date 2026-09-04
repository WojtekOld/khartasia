<?php

declare(strict_types=1);

namespace Drupal\sophron_guesser;

use Drupal\Core\File\MimeType\MimeTypeMapInterface;
use Drupal\sophron\MimeMapManagerInterface;
use FileEye\MimeMap\MapHandler;
use FileEye\MimeMap\MappingException;

/**
 * Provides a sensible mapping between filename extensions and MIME types.
 */
class SophronMimeTypeMap implements MimeTypeMapInterface {

  public function __construct(
    protected readonly MimeMapManagerInterface $mimeMapManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function addMapping(string $mimetype, string $extension): static {
    $map = MapHandler::map($this->mimeMapManager->getMapClass());
    $map->addTypeExtensionMapping($mimetype, $extension);
    $map->setExtensionDefaultType($extension, $mimetype);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMapping(string $mimetype, string $extension): bool {
    return MapHandler::map($this->mimeMapManager->getMapClass())->removeTypeExtensionMapping($mimetype, $extension);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMimeType(string $mimetype): bool {
    return MapHandler::map($this->mimeMapManager->getMapClass())->removeType($mimetype);
  }

  /**
   * {@inheritdoc}
   */
  public function listMimeTypes(): array {
    return $this->mimeMapManager->listTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function listExtensions(): array {
    return $this->mimeMapManager->listExtensions();
  }

  /**
   * {@inheritdoc}
   */
  public function hasMimeType(string $mimetype): bool {
    return MapHandler::map($this->mimeMapManager->getMapClass())->hasType($mimetype);
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtension(string $extension): bool {
    return MapHandler::map($this->mimeMapManager->getMapClass())->hasExtension($extension);
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeTypeForExtension($extension): ?string {
    try {
      $ext = $this->mimeMapManager->getExtension($extension);
      return $ext->getDefaultType();
    }
    catch (MappingException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionsForMimeType($mimetype): array {
    try {
      $type = $this->mimeMapManager->getType($mimetype);
      return $type->getExtensions();
    }
    catch (MappingException) {
      return [];
    }
  }

  /**
   * Gets the underlying mapping array.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
   *   replacement for this method.
   *
   * @see https://www.drupal.org/node/3494040
   * @see \Drupal\Core\File\EventSubscriber\LegacyMimeTypeMapLoadedSubscriber
   */
  public function getMapping(): array {
    // We do not trigger a deprecation error as this method is needed for
    // calling the deprecated hook_file_mimetype_mapping_alter() in
    // LegacyMimeTypeMapLoadedSubscriber::onMimeTypeMapLoaded().
    return [];
  }

  /**
   * Sets the underlying mapping array.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
   *   replacement for this method.
   *
   * @see https://www.drupal.org/node/3494040
   */
  public function setMapping(array $mapping): void {
    // We do not trigger a deprecation error as this method is needed for
    // calling the deprecated hook_file_mimetype_mapping_alter() in
    // LegacyMimeTypeMapLoadedSubscriber::onMimeTypeMapLoaded().
  }

}
