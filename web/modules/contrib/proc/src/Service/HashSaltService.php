<?php

namespace Drupal\proc\Service;

use Drupal\Core\Site\Settings;

/**
 * Service to provide the hash salt.
 */
class HashSaltService {

  /**
   * The settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected Settings $settings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * Get the hash salt.
   *
   * @return string
   *   The hash salt.
   */
  public function getHashSalt(): string {
    return $this->settings->getHashSalt();
  }

}
