<?php

namespace Drupal\xntt_views\Plugin\views\field;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\Plugin\views\field\RenderedEntity;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders an ExternalEntity without relying on SQL translation code.
 */
#[ViewsField("rendered_external_entity")]
class RenderedExternalEntity extends RenderedEntity {

  use EntityTranslationRenderTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a RenderedExternalEntity object.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, EntityRepositoryInterface $entity_repository, EntityDisplayRepositoryInterface $entity_display_repository, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $entity_repository, $entity_display_repository);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity.repository'),
      $container->get('entity_display.repository'),
      $container->get('logger.factory')->get('xntt_views')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We purposefully do not call parent::query() because we do not want the
    // default query behavior for Views fields.
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   A render array for the rendered external entity.
   */
  public function render(ResultRow $values) {
    // Expect the query plugin to have provided the loaded entity on the row.
    if (empty($values->_entity)) {
      return ['#markup' => ''];
    }

    $entity = $values->_entity;

    // Determine language: prefer the view langcode if set.
    $langcode = NULL;
    $rendering_language = $this->view->getDisplay()->getOption('rendering_language');
    if ($rendering_language === '***LANGUAGE_site_default***') {
      // Use the site default language.
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    elseif ($rendering_language === '***LANGUAGE_language_interface***') {
      // Interface text language selected for page.
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    elseif ($rendering_language === '***LANGUAGE_entity_translation***') {
      // Content language of view row.
      $langcode = $entity->language()->getId();
    }
    elseif ($rendering_language === '***LANGUAGE_entity_default***') {
      // Original language of content in view row.
      $langcode = $entity->getUntranslated()->language()->getId();
    }
    elseif (!str_starts_with('***LANGUAGE_', $rendering_language)) {
      // Specific language (e.g., 'fr').
      $langcode = $rendering_language;
    }
    else {
      // Fallback to current language.
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      // Warn for unsupported option.
      $this->logger->warning(
        'Unsupported rendering_language option "%option" in view %view_name. Falling back to current language "%langcode".',
        [
          '%option' => $rendering_language,
          '%view_name' => $this->view->id() ?? 'n/a',
          '%langcode' => $langcode,
        ]
      );
    }
    if ($entity->isTranslatable()
      && $langcode
      && $entity->hasTranslation($langcode)
    ) {
      $entity = $entity->getTranslation($langcode);
    }

    // Determine view mode from options (default 'default').
    $view_mode = $this->options['view_mode'] ?? 'default';

    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $build = $view_builder->view($entity, $view_mode);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() :array {
    $dependencies = parent::calculateDependencies();
    // Add external entity type dependency.
    $entity_config_id =
      'external_entities.external_entity_type.'
      . $this->getEntityTypeId();
    $dependencies['config'][] = $entity_config_id;
    return $dependencies;
  }

}
