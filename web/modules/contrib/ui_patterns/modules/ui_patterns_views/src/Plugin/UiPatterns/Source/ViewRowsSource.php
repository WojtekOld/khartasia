<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;

/**
 * The rows of a view.
 *
 * Used by the Component style plugin, it gives the rows the plugin received.
 * Used for a whole display, it gives the rows rendered by the style plugin of
 * the view.
 */
#[Source(
  id: 'view_rows',
  label: new TranslatableMarkup('[View] Rows'),
  description: new TranslatableMarkup('View rows results.'),
  prop_types: ['slot'],
  context_requirements: [['views:style', 'views:display']],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ]
)]
class ViewRowsSource extends ViewsDisplaySourceBase {

  /**
   * Get Prop Value When views has no rows.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   *
   * @return array
   *   The prop value.
   */
  protected function getPropValueViewsWithEmptyRows(ViewExecutable $view): array {
    $output = [];
    if (!$view->empty || empty($view->empty)) {
      return $output;
    }
    foreach ($view->empty as $key => $value) {
      $output[$key] = $value->render();
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    return $this->inStylePlugin() ? $this->renderRawRows($view) : $this->renderStyledRows($view);
  }

  /**
   * Whether the source is used by the Component style plugin.
   *
   * @return bool
   *   TRUE when the style plugin gave its rows in context.
   */
  protected function inStylePlugin(): bool {
    return isset($this->context['ui_patterns_views:rows']);
  }

  /**
   * The rows rendered by the style plugin of the view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   *
   * @return array
   *   The output of the style plugin.
   */
  protected function renderStyledRows(ViewExecutable $view): array {
    $style = $view->getStyle();
    if (!$style) {
      return [];
    }
    // Same preparation as ViewExecutable::render() before rendering the style.
    if ($style->usesFields()) {
      foreach ($view->field as $field) {
        $field->preRender($view->result);
      }
    }
    $style->preRender($view->result);
    return (!empty($view->result) || $style->evenEmpty()) ? $style->render() : [];
  }

  /**
   * The rows received by the Component style plugin.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   *
   * @return mixed
   *   The rows, or the empty area when there are none.
   */
  protected function renderRawRows(ViewExecutable $view): mixed {
    $rows = $this->getContextValue('ui_patterns_views:rows');
    if (!\is_array($rows) || \count($rows) < 1) {
      return $this->getPropValueViewsWithEmptyRows($view);
    }
    $view_style = $view->getStyle()->options;
    // If 'Force using fields' is checked in the view style settings,
    // we will render rows as an array of fields.
    $uses_fields = $view_style['uses_fields'] ?? FALSE;
    // If a field is selected, render only that field in rows.
    $field_name = $this->getSetting('ui_patterns_views_field') ?? NULL;
    if ($field_name || $uses_fields) {
      $this->renderRowsWithFields($view, $rows, $field_name);
    }
    // When there is only one row,
    // we wrap it in an array to prevent the slot normalization
    // to break the structure.
    return self::renderOutput((\count($rows) === 1) ? [$rows] : $rows);
  }

  /**
   * Render rows with a specific field.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param array $rows
   *   The rows.
   * @param string|null $field_name
   *   The field name.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  protected function renderRowsWithFields(ViewExecutable $view, array &$rows, ?string $field_name = NULL): void {
    $field_options = self::getViewsFieldOptions($view);
    if ($field_name && !isset($field_options[$field_name])) {
      $rows = [];
      return;
    }
    $view_style_plugin = $view->getStyle();
    if ($view_style_plugin) {
      $field_names = $field_name ? [$field_name] : \array_keys($field_options);
      foreach ($rows as $row_index => &$row) {
        $index = isset($row['#row'], $row['#row']->index) ? $row['#row']->index : $row_index;
        $new_row = $this->renderRowWithFields($view, $view_style_plugin, $field_names, $index);
        // When a specific field is selected,
        // we simplify the array to be the field value only.
        $row = self::renderOutput($field_name ? $new_row[$field_name] : $new_row);
      }
    }
  }

  /**
   * Render a row with specific fields.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\views\Plugin\views\style\StylePluginBase $view_style_plugin
   *   The view style plugin.
   * @param array $field_names
   *   The field names to render.
   * @param int $index
   *   The index of the row.
   *
   * @return array
   *   The rendered row as an array of fields.
   */
  protected function renderRowWithFields(ViewExecutable $view, StylePluginBase $view_style_plugin, array $field_names, int $index): array {
    $new_row = [];
    foreach ($field_names as $one_field_name) {
      $field_output = $view_style_plugin->getField($index, $one_field_name);
      if ($this->isViewFieldExcluded($one_field_name, $view)) {
        continue;
      }
      if ($this->isViewFieldHidden($one_field_name, $field_output, $view)) {
        $field_output = NULL;
      }
      $new_row[$one_field_name] = self::renderOutput($field_output ?? []);
    }
    return $new_row;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    // Picking a field only makes sense on the rows received by the plugin.
    $field_options = $this->inStylePlugin() ? self::getViewsFieldOptions($this->getView()) : NULL;
    if (\is_array($field_options)) {
      $form['ui_patterns_views_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Fields rendered in rows'),
        '#description' => $this->t('Render only this field in the rows.'),
        '#options' => $field_options,
        '#default_value' => $this->getSetting('ui_patterns_views_field') ?? '',
        '#required' => FALSE,
        '#empty_option' => $this->t('All'),
      ];
    }
    return $form;
  }

}
