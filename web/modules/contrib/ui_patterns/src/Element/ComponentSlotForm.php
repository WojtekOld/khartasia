<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\ui_patterns\PropTypePluginManager;

/**
 * Component to render a single slot.
 *
 * Usage example:
 *
 * @code
 * $form['slot'] = [
 *   '#type' => 'component_slot_form',
 *   '#component_id' => 'card',
 *   '#slot_id' => 'body',
 *   '#default_value' => [
 *     'sources' => [],
 *   ],
 * ];
 *
 * @endcode
 *
 * Value example:
 *
 * @code
 *    ['#default_value' =>
 *      ['sources' =>
 *        ['source_id' => 'id', 'value' => []]
 *      ]
 *    ]
 *
 * @endcode
 *
 * Configuration:
 *
 * '#component_id' =>Optional Component ID.
 *    A slot can rendered without knowing any context.
 * '#slot_id' =>Optional Slot ID.
 * '#source_contexts' =>The context of the sources.
 * '#tag_filter' =>Filter sources based on these tags.
 * '#display_remove' =>Display or hide the remove button. Default = true
 * '#cardinality_multiple' =>Allow or disallow multiple slot items
 * '#max_items' => Sources the slot accepts. NULL reads maxItems from the slot
 *   definition; the add button is disabled once the count is reached.
 */
#[FormElement('component_slot_form')]
class ComponentSlotForm extends ComponentFormBase {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return \array_merge(parent::trustedCallbacks(), ['postRenderSlotTable', 'preRenderSourceRow']);
  }

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
      '#display_remove' => TRUE,
      '#display_weight' => TRUE,
      '#component_id' => NULL,
      '#slot_id' => NULL,
      // Embedded slot forms show their rows open, with no box.
      '#open_selector' => FALSE,
      '#cardinality_multiple' => TRUE,
      '#max_items' => NULL,
      '#process' => [
        [$class, 'buildForm'],
        [$class, 'processPropOrSlot'],
      ],
      '#pre_render' => [
        [$class, 'preRenderPropOrSlot'],
      ],
      '#wrap' => TRUE,
      '#title_in_component' => NULL,
      '#after_build' => [
        [$class, 'afterBuild'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function cleanValues(array &$value): void {
    unset($value['add_more_button']);
    if (isset($value['sources'])) {
      foreach (Element::children($value['sources']) as $delta) {
        unset(
          $value['sources'][$delta]['_remove'],
          $value['sources'][$delta]['_configure'],
          $value['sources'][$delta]['_close'],
          $value['sources'][$delta]['_cancel'],
          $value['sources'][$delta]['_snapshot'],
          $value['sources'][$delta]['_open'],
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * A slot holds an open selector while any of its rows is open.
   */
  protected static function isSelectorOpen(array $element): bool {
    $sources = $element['#default_value']['sources'] ?? [];
    foreach ((\is_array($sources) ? $sources : []) as $source) {
      if (\is_array($source) && !empty($source['_open'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    $trigger_element = $form_state->getTriggeringElement();
    if (isset($trigger_element['#ui_patterns_slot']) && ($trigger_element['#ui_patterns_slot_parents'] === $element['#parents'])) {
      $value = $form_state->getValue($trigger_element['#ui_patterns_slot_parents']);
      if (isset($trigger_element['#ui_patterns_slot_operation']) && $slot_operation = $trigger_element['#ui_patterns_slot_operation']) {
        switch ($slot_operation) {
          case 'remove':
            $delta_to_remove = $trigger_element['#delta'];
            $value['sources'] = \array_filter($value['sources'], static function ($key) use ($delta_to_remove) {
              return $key !== $delta_to_remove;
            }, \ARRAY_FILTER_USE_KEY);
            $element['sources'] = \array_filter($element['sources'], static function ($key) use ($delta_to_remove) {
              return $key !== $delta_to_remove;
            }, \ARRAY_FILTER_USE_KEY);
            break;
        }
      }
      $element['#default_value'] = $value;
      static::applyMaxItems($element);
    }

    return parent::afterBuild($element, $form_state);
  }

  /**
   * Disables the add button once the slot holds #max_items sources.
   *
   * Called again from afterBuild(): a remove drops its row there, with no
   * rebuild when the form has validation errors, so the build-time state
   * (and the attribute FormBuilder derived from it) must follow.
   */
  protected static function applyMaxItems(array &$element): void {
    if (!isset($element['add_more_button']) || $element['#max_items'] === NULL) {
      return;
    }
    $reached = \count($element['#default_value']['sources'] ?? []) >= $element['#max_items'];
    $element['add_more_button']['#disabled'] = $reached;
    if ($reached) {
      $element['add_more_button']['#attributes']['disabled'] = 'disabled';
      // Buttons render no #description.
      $element['add_more_button']['#suffix'] = '<span class="description">' . \t('This slot accepts @count items at most.', ['@count' => $element['#max_items']]) . '</span>';
    }
    else {
      unset($element['add_more_button']['#attributes']['disabled'], $element['add_more_button']['#suffix']);
    }
  }

  /**
   * Handle the rebuild of the form and operations.
   */
  protected static function handleFormRebuild(array &$element, FormStateInterface $form_state): void {
    $trigger_element = $form_state->getTriggeringElement();
    if ($form_state->isRebuilding() && isset($trigger_element['#ui_patterns_slot'])) {
      if ($trigger_element['#ui_patterns_slot_parents'] === $element['#parents']) {
        $value = $form_state->getValue($trigger_element['#ui_patterns_slot_parents']);
        if (isset($trigger_element['#ui_patterns_slot_operation']) && $slot_operation = $trigger_element['#ui_patterns_slot_operation']) {
          switch ($slot_operation) {
            case 'add':
              // The button validates nothing, so the form-state values are
              // empty: build on the input-derived default value. The row
              // arrives open: the user picks the source there.
              $value = $element['#default_value'] ?? [];
              $value['sources'][] = [
                'source_id' => $trigger_element['#source_id'] ?? NULL,
                'source' => [],
                '_open' => '1',
              ];
              break;
          }
        }
        $element['#default_value'] = $value;
      }
    }
  }

  /**
   * Build single slot form.
   */
  public static function buildForm(array &$element, FormStateInterface $form_state): array {
    static::handleFormRebuild($element, $form_state);
    $slot_id = self::getSlotId($element);
    $component = static::getComponent($element);
    if ($component !== NULL) {
      $slots = $component->metadata->slots;
      $definition = $slots[$slot_id];
    }
    else {
      $prop_type_manager = \Drupal::service(PropTypePluginManager::class);
      $definition = [
        'ui_patterns' => [
          'type_definition' => $prop_type_manager->createInstance('slot', []),
        ],
      ];
    }
    $wrapper_id = static::getElementId($element, 'ui-patterns-slot-wrapper-' . $slot_id);
    $element['#tree'] = TRUE;
    $element['#attached']['library'][] = 'ui_patterns/source_selector';
    $element['#table_title'] = $element['#title'];
    $element['#title_in_component'] = $element['#title'];
    $element['#title'] = '';
    $element['sources'] = static::buildSourcesForm($element, $form_state, $definition, $wrapper_id);
    if ($element['#cardinality_multiple'] === TRUE || \count($element['#default_value']['sources'] ?? []) === 0) {
      $element['add_more_button'] = static::buildAddSourceButton($element, $definition, $wrapper_id);
    }
    $element['#max_items'] ??= $definition['maxItems'] ?? NULL;
    static::applyMaxItems($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    return $element;
  }

  /**
   * Removes the first occurrence of the <thead> element from an HTML string.
   *
   * @param string $html
   *   The HTML string.
   *
   * @return string
   *   The modified HTML string without the first <thead> element.
   */
  public static function removeFirstThead($html) {
    // Load the HTML into a DOMDocument object.
    $document = Html::load($html);

    // Find the first <thead> element.
    $thead = $document->getElementsByTagName('thead')->item(0);

    // If a <thead> element is found, remove it.
    if ($thead) {
      $thead->parentNode->removeChild($thead);
    }

    // Serialize the modified DOM back into a string.
    return Html::serialize($document);
  }

  /**
   * Alters the rendered form to simulate input forgery.
   *
   * It's necessary to alter the rendered form here because Mink does not
   * support manipulating the DOM tree.
   *
   * @param string $rendered_form
   *   The rendered form.
   *
   * @return string
   *   The modified rendered form.
   *
   * @see \Drupal\Tests\system\Functional\Form\FormTest::testInputForgery()
   */
  public static function postRenderSlotTable($rendered_form) {
    return static::removeFirstThead($rendered_form);
  }

  /**
   * Build single slot's sources form.
   */
  protected static function buildSourcesForm(array $element, FormStateInterface $form_state, array $definition, string $wrapper_id): array {
    $configuration = $element['#default_value'] ?? [];
    $form = [
      '#theme' => 'field_multiple_value_form',
      '#title' => $element['#table_title'] ?? '',
      '#cardinality_multiple' => $element['#cardinality_multiple'],
      '#post_render' => [
        [self::class, 'postRenderSlotTable'],
      ],
    ];
    // Add fake #field_name to avoid errors from
    // template_preprocess_field_multiple_value_form.
    $form['#field_name'] = 'foo';
    if (!isset($configuration['sources'])) {
      return $form;
    }
    $slot_id = self::getSlotId($element);
    $n_sources = \count($configuration['sources']);
    foreach ($configuration['sources'] as $delta => $source_configuration) {
      $form[$delta] = static::buildSourceRow($element, $form_state, $definition, $source_configuration, $delta, $wrapper_id);
      if ($element['#display_remove'] ?? TRUE) {
        $form[$delta]['_remove'] = static::buildRemoveSourceButton($element, $slot_id, $wrapper_id, $delta);
      }
      if ($element['#display_weight'] ?? TRUE) {
        $form[$delta]['_weight'] = static::buildSlotWeight($source_configuration, $delta, $n_sources);
      }
    }
    return $form;
  }

  /**
   * Build one source row: a details box, like a prop.
   *
   * Closed (default): only the saved source is loaded — a widget shows its
   * form, anything else a summary — the box title carries the buttons.
   * Open: all candidate sources are listed.
   */
  protected static function buildSourceRow(array $element, FormStateInterface $form_state, array $definition, array $source_configuration, int $delta, string $slot_wrapper_id): array {
    $delta_element = \array_merge($element, [
      '#default_value' => $source_configuration,
      '#title' => $element['#table_title'] ?? self::getSlotId($element),
      '#array_parents' => \array_merge($element['#array_parents'], ['sources', $delta]),
      '#parents' => \array_merge($element['#parents'], ['sources', $delta]),
    ]);
    // Embedded like a prop: straight to the open selector.
    if (!empty($element['#open_selector'])) {
      return static::buildSourceForm($delta_element, $form_state, $definition, $source_configuration);
    }
    $row_wrapper_id = static::getElementId($delta_element, 'ui-patterns-slot-row');
    $open = !empty($source_configuration['_open']);
    $definitions = static::getSourceDefinitions(self::getSlotId($element), $definition, $delta_element);
    $form = $open
      ? static::buildOpenSourceRow($delta_element, $form_state, $definition, $source_configuration, $row_wrapper_id, $slot_wrapper_id)
      : static::buildClosedSourceRow($delta_element, $form_state, $definition, $definitions, $source_configuration, $row_wrapper_id);
    // Carries the open state across rebuilds.
    $form['_open'] = [
      '#type' => 'hidden',
      '#default_value' => $open ? '1' : '',
    ];
    // preRenderSourceRow() moves the row into it.
    $resolved_source_id = static::resolveClosedSourceId($source_configuration, $definitions);
    $form['_box'] = [
      '#type' => 'details',
      '#title' => $resolved_source_id ? $definitions[$resolved_source_id]['label'] : \t('Not set'),
      // A closed row keeps its box open: the result and the button taking
      // the focus are both in there.
      '#open' => $open || static::isTriggeredWithin($delta_element['#array_parents'], $form_state),
      '#attributes' => ['class' => ['uip-prop-box']],
    ];
    // On the row itself: an AJAX refresh renders the row alone, without
    // the slot element around it.
    $form['#pre_render'] = [[static::class, 'preRenderSourceRow']];
    // Replaced as a whole when the selector opens or closes.
    $form['#prefix'] = '<div id="' . $row_wrapper_id . '" class="uip-slot-row">' . ($form['#prefix'] ?? '');
    $form['#suffix'] = ($form['#suffix'] ?? '') . '</div>';
    return $form;
  }

  /**
   * Folds a source row into its details box.
   *
   * The remove button stays out, reachable with the box closed. So does
   * the weight, which the table renders in its own cell: a row refreshed
   * alone would render a second one.
   */
  public static function preRenderSourceRow(array $row): array {
    if (!empty($row['source']['#ui_patterns_hidden'])) {
      static::hideSource($row['source']);
    }
    unset($row['_weight']);
    foreach (Element::children($row) as $key) {
      if ($key === '_box' || $key === '_remove') {
        continue;
      }
      $row['_box'][] = $row[$key];
      $row[$key]['#printed'] = TRUE;
    }
    return $row;
  }

  /**
   * Build a source row with the source selector open.
   */
  protected static function buildOpenSourceRow(array $delta_element, FormStateInterface $form_state, array $definition, array $configuration, string $row_wrapper_id, string $slot_wrapper_id): array {
    $slot_id = self::getSlotId($delta_element);
    $form = static::buildSourceForm($delta_element, $form_state, $definition, $configuration);
    $form += static::buildOpenRowControls($delta_element, $slot_id, $row_wrapper_id);
    // A cancelled fresh row vanishes with its whole table row, the weight
    // cell outside the row wrapper included: refresh the slot, like a
    // remove does.
    $form['_cancel']['#ajax']['wrapper'] = $slot_wrapper_id;
    $form['_cancel']['#ajax']['callback'] = [static::class, 'refreshForm'];
    $form['_cancel']['#ui_patterns_slot_array_parents'] = \array_slice($delta_element['#array_parents'], 0, -2);
    return $form;
  }

  /**
   * Build a source row with the source selector closed.
   *
   * Only the saved source is instantiated.
   */
  protected static function buildClosedSourceRow(array $delta_element, FormStateInterface $form_state, array $definition, array $definitions, array $configuration, string $row_wrapper_id): array {
    $slot_id = self::getSlotId($delta_element);
    $item_wrapper_id = static::getElementId($delta_element, 'ui-patterns-slot-item-' . $slot_id);
    $selected_source_id = static::resolveClosedSourceId($configuration, $definitions);
    $selected_source = $selected_source_id ? static::createSourceInstance($selected_source_id, $definitions[$selected_source_id], $slot_id, $definition, $delta_element) : NULL;
    $form = [
      'source_id' => [
        '#type' => 'hidden',
        '#value' => $selected_source_id ?? '',
      ],
      'source' => $selected_source ? static::getSourcePluginForm($form_state, $selected_source, $item_wrapper_id) : [
        '#type' => 'container',
        '#attributes' => [
          'id' => $item_wrapper_id,
        ],
      ],
      'node_id' => ['#type' => 'hidden', '#default_value' => $configuration['node_id'] ?? ''],
      'third_party_settings' => ['#type' => 'value', '#value' => $configuration['third_party_settings'] ?? []],
    ];
    $form = static::addRequired($form, $slot_id);
    return static::addClosedSummaryAndButton($form, $delta_element, $slot_id, $row_wrapper_id, $selected_source);
  }

  /**
   * Add slot weight.
   */
  protected static function buildSlotWeight(array $configuration, int $delta, int $weight_delta): array {
    return [
      '#type' => 'weight',
      '#title' => \t(
        'Weight for row @number',
        ['@number' => $delta + 1]
      ),
      '#title_display' => 'invisible',
      '#delta' => $weight_delta,
      '#default_value' => $configuration['_weight'] ?? $delta,
      '#weight' => 100,
    ];
  }

  /**
   * Build single source form.
   */
  public static function buildSourceForm(array $element, FormStateInterface $form_state, array $definition, array $configuration, bool $select_default = TRUE): array {
    $slot_id = self::getSlotId($element);
    if (!isset($element['#default_value'])) {
      $element['#default_value'] = $configuration;
    }
    $wrapper_id = static::getElementId($element, 'ui-patterns-slot-item-' . $slot_id);
    $sources = static::getSources($slot_id, $definition, $element);
    $selected_source = static::getSelectedSource($configuration, $sources, $select_default);
    $source_selector = static::buildSourceSelector($sources, $selected_source, $wrapper_id);
    $form = [
      'source_id' => $source_selector,
      'source' => $selected_source ? static::getSourcePluginForm($form_state, $selected_source, $wrapper_id) : [
        '#type' => 'container',
        '#attributes' => [
          'id' => $wrapper_id,
        ],
      ],
      'node_id' => ['#type' => 'hidden', '#default_value' => $configuration['node_id'] ?? ''],
      // A value element, not a hidden input: an input posts a string, and the
      // config schema expects a sequence.
      'third_party_settings' => ['#type' => 'value', '#value' => $configuration['third_party_settings'] ?? []],
    ];

    if (empty($slot_id)) {
      return $form;
    }
    $form = static::addRequired($form, $slot_id);
    if (!($element['#wrap'] ?? TRUE)) {
      $form = static::addTitleAndDescription($form);
    }
    return $form;
  }

  /**
   * Build the button removing a source row, shown next to its box.
   */
  protected static function buildRemoveSourceButton(array $element, string $slot_id, string $wrapper_id, int $delta): array {
    $id = \implode('-', $element['#array_parents']);
    $remove_action = [
      '#type' => 'submit',
      '#name' => \strtr($slot_id, '-', '_') . $id . '_' . $delta . '_remove',
      '#value' => \t('Remove'),
      '#submit' => [
        static::class . '::rebuildForm',
      ],
      '#access' => TRUE,
      '#delta' => $delta,
      '#ui_patterns_slot' => TRUE,
      '#ui_patterns_slot_operation' => 'remove',
      '#ui_patterns_slot_parents' => $element['#parents'],
      '#ui_patterns_slot_array_parents' => $element['#array_parents'],
      // We used to have an empty array, but that caused issues with
      // handleErrorsWithLimitedValidation in FormValidator.
      '#limit_validation_errors' => FALSE,
      '#attributes' => [
        'formnovalidate' => 'formnovalidate',
        'aria-label' => \t('@label: @slot', ['@label' => \t('Remove'), '@slot' => $element['#table_title'] ?? $slot_id]),
        'class' => ['uip-slot-remove'],
      ],
      '#ajax' => [
        'callback' => [static::class, 'refreshForm'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];
    return static::expandComponentButton($element, $remove_action);
  }

  /**
   * Build the button adding an empty source row, which arrives open.
   *
   * No source is listed or instantiated here: the new row's selector
   * offers them.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  protected static function buildAddSourceButton(array $element, array $definition, string $wrapper_id): array {
    $slot_id = self::getSlotId($element);
    $id = \implode('-', $element['#array_parents']);
    $parents = $element['#parents'];
    $first = \array_shift($parents);
    $add_action = [
      '#type' => 'submit',
      '#name' => \strtr($slot_id, '-', '_') . $id . '_add',
      '#attributes' => [
        'formnovalidate' => 'formnovalidate',
        'aria-label' => \t('@label: @slot', [
          '@label' => \t('Add content'),
          '@slot' => $element['#table_title'] ?? $slot_id,
        ]),
        'class' => ['uip-slot-add'],
        // The slot's form path; the button #name is not path-based.
        'data-uip-slot-add' => $first . ($parents ? '[' . \implode('][', $parents) . ']' : ''),
      ],
      '#value' => \t('Add content'),
      '#submit' => [
        static::class . '::rebuildForm',
      ],
      '#access' => TRUE,
      '#ui_patterns_slot' => TRUE,
      '#ui_patterns_slot_operation' => 'add',
      '#ui_patterns_slot_parents' => $element['#parents'],
      '#ui_patterns_slot_array_parents' => $element['#array_parents'],
      // Validate nothing, like the toggle buttons: adding a row must not
      // require the rest of the form to be valid.
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [
          static::class,
          'refreshForm',
        ],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];
    return static::expandComponentButton($element, $add_action);
  }

  /**
   * Expand button base array into a paragraph widget action button.
   *
   * @param array $element
   *   Element.
   * @param array $button_base
   *   Button base render array.
   *
   * @return array
   *   Button render array.
   */
  protected static function expandComponentButton(array $element, array $button_base): array {
    // Do not expand elements that do not have submit handler.
    if (empty($button_base['#submit'])) {
      return $button_base;
    }

    $button = $button_base + [
      '#type' => 'submit',
    ];

    // Html::getId will give us '-' char in name but we want '_' for now so
    // we use strtr to search&replace '-' to '_'.
    $button['#name'] = \strtr(Html::getId($button_base['#name']), '-', '_');
    $button['#id'] = static::getElementId($element, $button['#name']);

    if (isset($button['#ajax'])) {
      $button['#ajax'] += [
        'effect' => 'fade',
        // Since a normal throbber is added inline, this has the potential to
        // break a layout if the button is located in dropbuttons. Instead,
        // it's safer to just show the fullscreen progress element instead.
        'progress' => ['type' => 'fullscreen'],
      ];
    }

    return static::expandAjax($button);
  }

  /**
   * Ajax submit handler: trigger rebuild of the sources form.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  public static function rebuildForm(array $form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Ajax handler: Refresh sources form.
   */
  public static function refreshForm(array $form, FormStateInterface $form_state): mixed {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $parents = $triggering_element['#ui_patterns_slot_array_parents'];
    $form_state->setRebuild(TRUE);
    $returned = NestedArray::getValue($form, $parents);
    $returned = static::announceValues($returned, $triggering_element, $form_state);
    $response = new AjaxResponse();
    $returned['#prefix'] = '';
    $returned['#suffix'] = '';
    $response->addCommand(new HtmlCommand('#' . $wrapper_id, $returned));
    $response->addCommand(new FocusFirstCommand(static::getSlotFocusSelector($returned, $triggering_element, $wrapper_id)));
    if (($triggering_element['#ui_patterns_slot_operation'] ?? '') === 'remove') {
      // A screen reader hears what left; sighted users see the row go.
      $response->addCommand(new AnnounceCommand((string) \t('@slot: content removed.', ['@slot' => $returned['#table_title'] ?? ''])));
    }
    return $response;
  }

  /**
   * The control taking the focus once an action refreshed the slot.
   *
   * The row the action landed in, else the button adding one: a removed
   * or abandoned row leaves the focus where the next row comes from.
   *
   * @param array $slot
   *   The refreshed slot element.
   * @param array $trigger
   *   The triggering element.
   * @param string $wrapper_id
   *   The id of the wrapper div around the slot.
   *
   * @return string
   *   A selector for FocusFirstCommand.
   */
  protected static function getSlotFocusSelector(array $slot, array $trigger, string $wrapper_id): string {
    $add_button = $slot['add_more_button'] ?? [];
    $fallback_id = (isset($add_button['#id']) && empty($add_button['#disabled'])) ? $add_button['#id'] : $wrapper_id;
    $rows = $slot['sources'] ?? [];
    $deltas = Element::children($rows);
    $row_parents = $trigger['#ui_patterns_prop_parents'] ?? [];
    $delta = (($trigger['#ui_patterns_slot_operation'] ?? NULL) === 'add') ? \end($deltas) : \end($row_parents);
    if ($delta === FALSE || !isset($rows[$delta])) {
      return '#' . $fallback_id;
    }
    return static::getRowFocusSelector($rows[$delta], $fallback_id);
  }

}
