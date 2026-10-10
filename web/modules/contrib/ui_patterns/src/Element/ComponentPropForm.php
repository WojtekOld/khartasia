<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Component to render a single prop.
 *
 * Usage example:
 *
 * @code
 * $form['prop_name'] = [
 *   '#type' => 'component_prop_form',
 *   '#component_id' => 'component_id',
 *   '#prop_id' => 'prop'
 *   '#default_value' => [
 *     'source' => [],
 *     'source_id' => 'textfield'
 *   ],
 * ];
 *
 * @endcode
 *
 * Value example:
 *
 * @code
 * '#default_value' => ['source_id' => 'id', 'source' => []]
 *
 * @endcode
 *
 *  Configuration:
 *
 *  '#component_id' => Required Component ID.
 *  '#prop_id' => Required Prop ID.
 *  '#source_contexts' => The context of the sources.
 *  '#tag_filter' => Filter sources based on these tags.
 */
#[FormElement('component_prop_form')]
class ComponentPropForm extends ComponentFormBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#default_value' => NULL,
      '#source_contexts' => [],
      '#tag_filter' => [],
      '#component_id' => NULL,
      '#slot_id' => NULL,
      // Embedded prop forms show their selector open, with no close button.
      '#open_selector' => FALSE,
      // Wrapped (into details/summary) or not.
      '#wrap' => FALSE,
      '#render_sources' => TRUE,
      '#process' => [
        [$class, 'buildForm'],
        [$class, 'processPropOrSlot'],
      ],
      '#pre_render' => [
        [$class, 'preRenderPropOrSlot'],
      ],
      '#theme_wrappers' => [],
      '#after_build' => [
        [$class, 'afterBuild'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
    ];
  }

  /**
   * Build props forms.
   *
   * Closed (default): only the saved source is loaded — a widget shows its
   * form, anything else a summary — next to a button opening the selector.
   * Open: all candidate sources are listed.
   */
  public static function buildForm(array &$element, FormStateInterface $form_state): array {
    $element['#tree'] = TRUE;
    $component = static::getComponent($element);
    if (!$component || !isset($element['#prop_id'])) {
      return [];
    }
    $prop_id = $element['#prop_id'];
    $definition = $component->metadata->schema['properties'][$prop_id];
    $configuration = $element['#default_value'] ?? [];
    $wrapper_id = static::getElementId($element, 'ui-patterns-prop-item-' . $prop_id);
    $row_wrapper_id = static::getElementId($element, 'ui-patterns-prop-row-' . $prop_id);
    $render_sources = $element['#render_sources'] ?? TRUE;
    $open = $render_sources && (!empty($element['#open_selector']) || static::isSelectorOpen($element));
    // Replaced as a whole when the selector opens or closes.
    $element['#prefix'] = ($element['#prefix'] ?? '') . '<div id="' . $row_wrapper_id . '">';
    $element['#suffix'] = '</div>' . ($element['#suffix'] ?? '');
    $element['#attached']['library'][] = 'ui_patterns/source_selector';
    $element = static::addRequired($element, $prop_id);
    if ($open) {
      $element = static::buildOpenState($element, $form_state, $prop_id, $definition, $configuration, $wrapper_id, $row_wrapper_id);
    }
    else {
      $element = static::buildClosedState($element, $form_state, $prop_id, $definition, $configuration, $wrapper_id, $row_wrapper_id);
    }
    $element['node_id'] = [
      '#type' => 'hidden',
      '#value' => $configuration['node_id'] ?? NULL,
    ];
    // Carries the open state across rebuilds; see isSelectorOpen().
    $element['_open'] = [
      '#type' => 'hidden',
      '#default_value' => $open ? '1' : '',
    ];
    return static::wrapOrTitle($element, $prop_id);
  }

  /**
   * Wrap the prop form into its closed box, or title the bare form.
   */
  protected static function wrapOrTitle(array $element, string $prop_id): array {
    if ($element['#wrap'] ?? TRUE) {
      // Like checkDetailsElement(), plus our class and required marker.
      $element[$prop_id] = [
        '#type' => 'details',
        '#title' => !empty($element['#title']) ? $element['#title'] : $prop_id,
        '#description' => $element['#description'] ?? NULL,
        // Opened by processPropOrSlot().
        '#open' => FALSE,
        '#required' => !empty($element['#required']),
        '#attributes' => ['class' => ['uip-prop-box']],
      ];
    }
    elseif (empty($element['#open_selector'])) {
      // Bare widgets get a title; embedded forms are named by their
      // parent.
      $element = static::addTitleAndDescription($element);
    }
    return $element;
  }

  /**
   * Build the prop form with the source selector open.
   */
  protected static function buildOpenState(array $element, FormStateInterface $form_state, string $prop_id, array $definition, array $configuration, string $wrapper_id, string $row_wrapper_id): array {
    $sources = static::getSources($prop_id, $definition, $element);
    $selected_source = static::getSelectedSource($configuration, $sources);
    $source_selector = static::buildSourceSelector($sources, $selected_source, $wrapper_id, TRUE);
    $source_form = $selected_source ? static::getSourcePluginForm($form_state, $selected_source, $wrapper_id) : [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];
    $element += [
      'source_id' => $source_selector,
      'source' => \array_merge($source_form, ['#prop_id' => $prop_id]),
    ];
    // A bare checkbox is unreadable: show its label here too.
    if (($element['source']['value']['#type'] ?? '') === 'checkbox' && ($element['source']['value']['#title_display'] ?? '') === 'invisible') {
      $element['source']['value']['#title_display'] = 'after';
    }
    if (empty($element['#open_selector'])) {
      $element += static::buildOpenRowControls($element, $prop_id, $row_wrapper_id);
    }
    return $element;
  }

  /**
   * Build the prop form with the source selector closed.
   */
  protected static function buildClosedState(array $element, FormStateInterface $form_state, string $prop_id, array $definition, array $configuration, string $wrapper_id, string $row_wrapper_id): array {
    $render_sources = $element['#render_sources'] ?? TRUE;
    $definitions = static::getSourceDefinitions($prop_id, $definition, $element);
    $selected_source_id = static::resolveClosedSourceId($configuration, $definitions);
    $selected_source = $selected_source_id ? static::createSourceInstance($selected_source_id, $definitions[$selected_source_id], $prop_id, $definition, $element) : NULL;
    $source_form = $selected_source ? static::getSourcePluginForm($form_state, $selected_source, $wrapper_id) : [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];
    $element += [
      'source_id' => [
        '#type' => 'hidden',
        '#value' => $selected_source_id ?? '',
      ],
      'source' => \array_merge($source_form, ['#prop_id' => $prop_id]),
    ];
    // A bare checkbox is unreadable: show its label.
    if (($element['source']['value']['#type'] ?? '') === 'checkbox' && ($element['source']['value']['#title_display'] ?? '') === 'invisible') {
      $element['source']['value']['#title_display'] = 'after';
    }
    // One shown candidate: no button. An unset prop always keeps it.
    if ($render_sources && (\count($definitions) > 1 || !$selected_source)) {
      $element = static::addClosedSummaryAndButton($element, $element, $prop_id, $row_wrapper_id, $selected_source);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected static function cleanValues(array &$value): void {
    unset($value['_configure'], $value['_close'], $value['_cancel'], $value['_snapshot'], $value['_open']);
  }

}
