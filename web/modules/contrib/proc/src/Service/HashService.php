<?php

namespace Drupal\proc\Service;

use Drupal\Component\Utility\Crypt;

/**
 * Service for hashing data.
 */
class HashService {

  /**
   * Hashes a string using base64 encoding.
   *
   * @param string $data
   *   The data to hash.
   *
   * @return string
   *   The hashed data.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function hashBase64(string $data): string {
    return Crypt::hashBase64($data);
  }

}
