<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\filter\Element\ProcessedText;
use Drupal\filter\FilterFormatInterface;
use Drupal\ui_patterns_ckeditor5\HostEntity;
use Drupal\ui_patterns_ckeditor5\Plugin\Filter\ComponentEmbed;
use Drupal\ui_patterns_ckeditor5\RenderingFilter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for ui_patterns_ckeditor5.
 */
class UiPatternsCkeditor5Hooks {

  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected RenderingFilter $renderingFilter,
    #[Autowire(service: 'logger.channel.ui_patterns')]
    protected LoggerInterface $logger,
  ) {}

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * The JavaScript plugin reads the entity from the textarea and posts it to
   * the dialog and the preview.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    if (($element['#type'] ?? NULL) !== 'text_format') {
      return;
    }
    $entity = $context['items']->getEntity();
    // TextFormat::processFormat() copies the attributes to the textarea.
    $element['#attributes']['data-ui-patterns-ckeditor5-entity'] = Json::encode([
      'type' => $entity->getEntityTypeId(),
      'id' => $entity->id(),
      'bundle' => $entity->bundle(),
    ]);
  }

  /**
   * Implements hook_entity_display_build_alter().
   *
   * The filter runs inside the pre-render of processed_text elements, which
   * know nothing of the entity: the entity is pushed for the time of that
   * pre-render.
   */
  #[Hook('entity_display_build_alter')]
  public function entityDisplayBuildAlter(array &$build, array $context): void {
    $entity = $context['entity'] ?? NULL;
    if (!$entity instanceof EntityInterface) {
      return;
    }
    foreach (Element::children($build) as $field_name) {
      foreach (Element::children($build[$field_name]) as $delta) {
        $element = &$build[$field_name][$delta];
        if (($element['#type'] ?? NULL) !== 'processed_text') {
          continue;
        }
        $element[HostEntity::ELEMENT_KEY] = $entity;
        $element['#pre_render'] = [
          [HostEntity::class, 'preRenderPush'],
          [ProcessedText::class, 'preRenderText'],
          [HostEntity::class, 'preRenderPop'],
        ];
      }
    }
  }

  /**
   * Implements hook_ui_patterns_form_alter().
   *
   * In the editor dialog, nested component forms only offer the components
   * the text format allows.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('ui_patterns_form_alter')]
  public function uiPatternsFormAlter(array &$form, FormStateInterface $form_state): void {
    if (($form['#type'] ?? NULL) !== 'component_form' || $this->routeMatch->getRouteName() !== 'ui_patterns_ckeditor5.dialog') {
      return;
    }
    $filter_format = $this->routeMatch->getParameter('filter_format');
    $filter = $filter_format instanceof FilterFormatInterface ? $filter_format->filters('component_embed') : NULL;
    $allowed = $filter instanceof ComponentEmbed ? $filter->getAllowedComponentIds() : [];
    if ($allowed === []) {
      return;
    }
    $form['#component_filter'] = isset($form['#component_filter'])
      ? \array_values(\array_intersect($form['#component_filter'], $allowed))
      : $allowed;
  }

  /**
   * Implements hook_ui_patterns_component_pre_build_alter().
   *
   * In a text, a nested component the text format does not allow renders
   * nothing.
   */
  #[Hook('ui_patterns_component_pre_build_alter')]
  public function uiPatternsComponentPreBuildAlter(array &$element): void {
    $filter = $this->renderingFilter->current();
    if ($filter === NULL || $filter->isComponentAllowed($element['#component'])) {
      return;
    }
    $this->logger->warning('Embedded component "@component_id" is not allowed by the text format.', ['@component_id' => $element['#component']]);
    $element['#ui_patterns'] = [];
    $element['#printed'] = TRUE;
  }

}
