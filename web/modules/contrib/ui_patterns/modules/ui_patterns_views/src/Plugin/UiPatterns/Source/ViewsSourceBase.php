<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\UiPatterns\Source;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
abstract class ViewsSourceBase extends SourcePluginBase {

  /**
   * The views temp store.
   */
  protected ?SharedTempStore $tempStore = NULL;

  /**
   * The view executables resolved from the entity in context, per request.
   *
   * Keyed by view and display. The Views UI tempstore gives a new copy on
   * every read; without this, each source would run its own copy.
   *
   * @var \Drupal\views\ViewExecutable[]
   */
  protected static array $executables = [];

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $plugin = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    if ($plugin->moduleHandler->moduleExists('views_ui')) {
      $plugin->tempStore = $container->get(SharedTempStoreFactory::class)->get('views');
    }
    return $plugin;
  }

  /**
   * Returns the view from context.
   *
   * A running view wins over the view entity.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The view executable.
   */
  protected function getView(): ?ViewExecutable {
    try {
      if (isset($this->context['ui_patterns_views:view'])) {
        return $this->getContextValue('ui_patterns_views:view');
      }
      if (isset($this->context['ui_patterns_views:view_entity'])) {
        return $this->getViewFromEntity($this->getContextValue('ui_patterns_views:view_entity'));
      }
    }
    catch (ContextException) {
    }
    return NULL;
  }

  /**
   * Returns the view of the entity, once per request.
   *
   * @param \Drupal\views\ViewEntityInterface $view_entity
   *   The view entity.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The view on the display from context, or NULL when that display does
   *   not exist.
   */
  protected function getViewFromEntity(ViewEntityInterface $view_entity): ?ViewExecutable {
    $display_id = isset($this->context['ui_patterns_views:display']) ? $this->getContextValue('ui_patterns_views:display') : NULL;
    $display_id = \is_string($display_id) && $display_id !== '' ? $display_id : NULL;
    $key = $view_entity->id() . ':' . ($display_id ?? '');
    if (isset(self::$executables[$key])) {
      return self::$executables[$key];
    }
    $view = $this->getViewExecutable($view_entity);
    if ($view === NULL || ($display_id !== NULL && !$view->setDisplay($display_id))) {
      return NULL;
    }
    return self::$executables[$key] = $view;
  }

  /**
   * Get the view executable.
   *
   * @param \Drupal\views\ViewEntityInterface|\Drupal\views\ViewExecutable $view
   *   The view entity or view executable.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The view executable.
   */
  protected function getViewExecutable($view): ?ViewExecutable {
    if ($view && $this->tempStore && ($view_in_edition = $this->tempStore->get((string) $view->id()))) {
      return $view_in_edition->getExecutable();
    }
    return ($view instanceof ViewEntityInterface) ? $view->getExecutable() : $view;
  }

  /**
   * Returns the views field options or NULL if not applicable.
   *
   * @param \Drupal\views\ViewExecutable|null $view
   *   The view executable.
   *
   * @return array|null
   *   The options or NULL if not applicable.
   */
  protected static function getViewsFieldOptions(?ViewExecutable $view = NULL): ?array {
    if (!$view || !$view->display_handler) {
      return NULL;
    }
    $fields_options = $view->display_handler->getOption('fields') ?? [];
    // Maybe a test on $row_options type could be done here
    // $row_options = $view->display_handler->getOption("row") ?? [];.
    if (!\is_array($fields_options) || \count($fields_options) < 1) {
      return NULL;
    }
    $options = [];
    foreach ($fields_options as $field_id => $field) {
      $options[$field_id] = empty($field['label']) ? $field_id : \sprintf('%s (%s)', $field['label'], $field_id);
    }
    return $options;
  }

  /**
   * Test if field is excluded.
   *
   * @param string $field_name
   *   The field name.
   * @param \Drupal\views\ViewExecutable $view
   *   The current view.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *
   * @return bool
   *   Return TRUE if the field need to be removed from render.
   */
  protected function isViewFieldExcluded(string $field_name, ViewExecutable $view): bool {
    $field = $this->getViewField($field_name, $view);
    // Exclude field.
    return !$field || (\is_array($field->options) && ($field->options['exclude'] ?? FALSE));
  }

  /**
   * Test if field must be deleted from render.
   *
   * @param string $field_name
   *   The field name.
   * @param mixed $field_output
   *   The render of field.
   * @param \Drupal\views\ViewExecutable|null $view
   *   The current view.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *
   * @return bool
   *   Return TRUE if the field need to be removed from render.
   */
  protected function isViewFieldHidden(string $field_name, mixed $field_output, ?ViewExecutable $view = NULL): bool {
    $field = $this->getViewField($field_name, $view);
    if (!$field) {
      // If the field is not found, we consider it as excluded.
      return TRUE;
    }
    $options = $this->getViewPluginOptions();
    $empty_value = $field->isValueEmpty($field_output, $field->options['empty_zero']);
    // Remove field if empty.
    return $empty_value && ($field->options['hide_empty'] === TRUE || ($options['hide_empty'] ?? FALSE) === TRUE);
  }

  /**
   * Returns a view field plugin.
   *
   * @param string $field_name
   *   The field name.
   * @param \Drupal\views\ViewExecutable|null $view
   *   The view executable or null to get from context.
   *
   * @return \Drupal\views\Plugin\views\field\FieldPluginBase|null
   *   The field plugin or NULL if not found.
   */
  protected function getViewField(string $field_name, ?ViewExecutable $view): ?FieldPluginBase {
    if (!$view) {
      $view = $this->getView();
    }
    if (!$view || !\is_array($view->field) || !isset($view->field[$field_name]) || !($view->field[$field_name] instanceof FieldPluginBase)) {
      return NULL;
    }
    return $view->field[$field_name];
  }

  /**
   * Returns the view row options.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *
   * @return array
   *   The view row options.
   */
  protected function getViewPluginOptions(): array {
    return isset($this->context['ui_patterns_views:plugin:options']) ? $this->getContextValue('ui_patterns_views:plugin:options') : [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $propTitle = $this->propDefinition['title'] ?? '';
    $form['label_map'] = [
      '#type' => 'label',
      '#title' => empty($propTitle) ? $this->label() : $propTitle . ': ' . $this->label(),
    ];
    return $form;
  }

  /**
   * Helper function to render output as render array markup.
   *
   * @param mixed $output
   *   The output to render.
   *
   * @return array
   *   The render array.
   */
  protected static function renderOutput($output) {
    if (empty($output)) {
      return ['#markup' => ''];
    }
    // We use #children to avoir filtering.
    if ($output instanceof MarkupInterface) {
      return [
        '#children' => (string) $output,
      ];
    }
    if (\is_scalar($output)) {
      return ['#children' => $output];
    }
    return $output;
  }

}
