<?php

namespace Drupal\tmgmt_deepl;

use DeepL\AppInfo;
use DeepL\Translator as DeepLTranslator;

/**
 * Factory for creating DeepL SDK client instances.
 */
class DeepLClientFactory implements DeepLClientFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function create(string $apiKey, string $appName, string $version): DeepLTranslator {
    $app_info = new AppInfo($appName, $version);
    return new DeepLTranslator($apiKey, ['app_info' => $app_info]);
  }

}
