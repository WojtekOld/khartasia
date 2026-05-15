<?php

namespace Drupal\bibcite;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class HelpService.
 *
 * @package Drupal\bibcite
 */
class HelpService implements HelpServiceInterface {

  /**
   * Extenstion list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $extensionList;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a HelpService object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list
   *   Module extension list service
   */
  public function __construct(ModuleExtensionList $extension_list, LanguageManagerInterface $language_manager) {
    $this->extensionList = $extension_list;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getHelpMarkup($links, $route, $module) {
    $module_path = $this->extensionList->getPath($module);
    $lang = $this->languageManager->getCurrentLanguage()->getId();
    $def = $this->languageManager->getDefaultLanguage()->getId();
    $path = $module_path . '/help/' . $lang . '/' . $route . 'html';
    if ($def === $lang || !file_exists($path)) {
      $path = $module_path . '/help/default/' . $route . '.html';
    }
    if (file_exists($path)) {
      $output = file_get_contents($path);
      return sprintf($output, $links);
    }
    return NULL;
  }

}
