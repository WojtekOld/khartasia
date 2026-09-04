<?php

namespace Drupal\tmgmt_deepl;

use DeepL\Translator as DeepLTranslator;

/**
 * Interface for creating DeepL SDK client instances.
 */
interface DeepLClientFactoryInterface {

  /**
   * Creates a DeepL Translator client.
   *
   * @param string $apiKey
   *   The DeepL API key.
   * @param string $appName
   *   The application name for the app info.
   * @param string $version
   *   The module version.
   *
   * @return \DeepL\Translator
   *   The DeepL Translator client.
   */
  public function create(string $apiKey, string $appName, string $version): DeepLTranslator;

}
