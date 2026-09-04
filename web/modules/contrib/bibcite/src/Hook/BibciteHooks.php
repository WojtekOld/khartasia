<?php

namespace Drupal\bibcite\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for bibcite.
 */
class BibciteHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme($existing, $type, $theme, $path) {
    return [
      'bibcite_citation' => [
        'variables' => [
          'data' => [],
          'style' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_bibcite_citation')]
  public static function preprocessBibciteCitation(&$variables) {
    /** @var \Drupal\bibcite\CitationStylerInterface $styler */
    $styler = \Drupal::service('bibcite.citation_styler');
    $data = $variables['data'];
    if ($variables['style']) {
      $styler->setStyleById($variables['style']);
    }
    else {
      $styler->setStyle(NULL);
    }
    $processed = $styler->render($data);
    $config = \Drupal::config('bibcite.settings');
    $convert_urls = $config->get('convert_urls') ?? FALSE;
    if ($convert_urls === TRUE) {
      $filter = new \stdClass();
      $filter->settings = [
        'filter_url_length' => 72,
      ];
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $processed = DeprecationHelper::backwardsCompatibleCall(
        currentVersion: \Drupal::VERSION,
        deprecatedVersion: '11.4',
        currentCallable: fn() => \Drupal::service('plugin.manager.filter')->createInstance('filter_url', [
          'settings' => $filter->settings,
        ])->process($processed, $langcode)->getProcessedText(),
        deprecatedCallable: fn() => _filter_url($processed, $filter),
      );
    }
    $variables['content'] = [
      '#markup' => $processed,
    ];
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bibcite':
        $links = [
          ':proc' => Url::fromRoute('bibcite.settings')->toString(),
          ':csl' => Url::fromRoute('entity.bibcite_csl_style.collection')->toString(),
        ];
        $module = 'bibcite';
        return \Drupal::service('bibcite.help_service')->getHelpMarkup($links, $route_name, $module);
    }
  }

}
