<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\views\display_extender;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns_views\ViewsPluginUiPatternsTrait;
use Drupal\views\Attribute\ViewsDisplayExtender;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a whole view display with a component.
 *
 * The component gets the views:display sources. Without a component the
 * display renders as usual. The component is a display option, inherited
 * from the default display like the style or the pager.
 *
 * @see \Drupal\ui_patterns_views\Hook\UiPatternsViewsHooks
 */
#[ViewsDisplayExtender(
  id: 'ui_patterns',
  title: new TranslatableMarkup('Component (UI Patterns)'),
  help: new TranslatableMarkup('Renders a whole display with a component.'),
)]
class ComponentDisplayExtender extends DisplayExtenderPluginBase {

  use ViewsPluginUiPatternsTrait;

  /**
   * The plugin ID, also the Views UI form section.
   */
  public const ID = 'ui_patterns';

  /**
   * The display option holding the component.
   */
  public const OPTION = 'ui_patterns';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->initialize($container);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptionsAlter(&$options): void {
    $options[self::OPTION] = ['default' => self::getComponentFormDefault()['ui_patterns']];
    // A new display starts on the default display's component.
    $options['defaults']['default'][self::OPTION] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultableSections(&$sections, $section = NULL): void {
    $sections[self::OPTION] = [self::OPTION];
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options): void {
    if (!$this->isApplicable()) {
      return;
    }
    $component_id = $this->getComponentConfiguration()['component_id'] ?? NULL;
    $options[self::ID] = [
      'category' => 'other',
      'title' => $this->t('Component'),
      'value' => $component_id ?: $this->t('None'),
      'desc' => $this->t('Render the whole display with a component.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('section') !== self::ID || !$this->isApplicable()) {
      return;
    }
    $form['#title'] = ($form['#title'] ?? '') . $this->t('Component');
    $form[self::ID] = $this->buildComponentsForm($form_state, $this->getFullContext(), NULL, TRUE, TRUE, self::ID, [
      // No component: the display renders as usual.
      '#component_required' => FALSE,
      '#component_validation' => FALSE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('section') !== self::ID) {
      return;
    }
    $value = $form_state->getValue(self::ID);
    // Saved on the default display when this display follows it.
    $this->displayHandler->setOption(self::OPTION, \is_array($value) ? $value : self::getComponentFormDefault()['ui_patterns']);
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentSettings(): array {
    $configuration = $this->displayHandler->getOption(self::OPTION);
    return [self::ID => \is_array($configuration) ? $configuration : []];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAjaxUrl(FormStateInterface $form_state): ?Url {
    return $this->getViewsUiBuildFormUrl($form_state);
  }

  /**
   * Whether the display has an output a component can replace.
   *
   * @return bool
   *   FALSE for feeds and entity reference displays.
   */
  public function isApplicable(): bool {
    $definition = $this->view->getDisplay()->getPluginDefinition();
    return \is_array($definition) && empty($definition['returns_response']) && ($definition['id'] ?? '') !== 'entity_reference';
  }

  /**
   * Builds the component element of the display.
   *
   * @return array|null
   *   The component element, or NULL when no component is configured.
   */
  public function buildRenderable(): ?array {
    $component_id = $this->getComponentConfiguration()['component_id'] ?? NULL;
    if (!$component_id || !$this->isApplicable()) {
      return NULL;
    }
    $build = $this->buildComponentRenderable((string) $component_id, $this->getFullContext());
    $view = $this->view;
    // The view classes, Views AJAX needs them.
    $classes = [
      'view',
      'view-' . Html::cleanCssIdentifier((string) $view->id()),
      'view-id-' . $view->id(),
      'view-display-id-' . $view->current_display,
    ];
    if (!empty($view->dom_id)) {
      $classes[] = 'js-view-dom-id-' . $view->dom_id;
    }
    $build['#attributes']['class'] = $classes;
    return $build;
  }

  /**
   * The contexts of the display, for the form and for the render.
   *
   * @return array
   *   The contexts.
   */
  protected function getFullContext(): array {
    $context = $this->getComponentSourceContexts();
    $context['ui_patterns_views:display'] = new Context(new ContextDefinition('string'), (string) $this->view->current_display);
    return RequirementsContext::addToContext(['views:display'], $context);
  }

}
