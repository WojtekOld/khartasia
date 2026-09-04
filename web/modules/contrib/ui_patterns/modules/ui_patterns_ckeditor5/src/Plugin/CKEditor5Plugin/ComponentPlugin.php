<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Drupal\ui_patterns_ckeditor5\Controller\PreviewController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 plugin embedding UI components.
 *
 * Gives the JavaScript plugin the dialog and preview URLs of the text format.
 */
class ComponentPlugin extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    protected CsrfTokenGenerator $csrfTokenGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('csrf_token'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $filter_format = $editor->getFilterFormat();
    if ($filter_format === NULL) {
      return $static_plugin_config;
    }
    $parameters = ['filter_format' => $filter_format->id()];
    $static_plugin_config['drupalComponent']['dialogURL'] = Url::fromRoute('ui_patterns_ckeditor5.dialog', $parameters)
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['drupalComponent']['previewURL'] = Url::fromRoute('ui_patterns_ckeditor5.preview', $parameters)
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['drupalComponent']['previewCsrfToken'] = $this->csrfTokenGenerator->get(PreviewController::CSRF_HEADER);
    return $static_plugin_config;
  }

}
