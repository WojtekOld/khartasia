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
use Drupal\Core\Plugin\Component;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns\SourceInterface;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\SourceTags;

/**
 * Base class for components forms.
 */
abstract class ComponentFormBase extends FormElementBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderPropOrSlot', 'processPropOrSlot'];
  }

  /**
   * Check if the form element needs a details.
   *
   * @param array $element
   *   The form element.
   *
   * @return string|null
   *   Prop or slot id if the form element needs a details.
   */
  protected static function checkDetailsElement(array &$element): ?string {
    if (!isset($element['#wrap']) || !$element['#wrap']) {
      return NULL;
    }
    $prop_or_slot_id = $element['#prop_id'] ?? self::getSlotId($element);
    $title_in_component = $element['#title_in_component'] ?? $prop_or_slot_id;
    $title = !empty($element['#title']) ? $element['#title'] : $title_in_component;
    if (!\array_key_exists($prop_or_slot_id, $element)) {
      $element[$prop_or_slot_id] = [
        '#type' => 'details',
        '#title' => $title,
        '#description' => $element['#description'] ?? NULL,
        '#open' => FALSE,
      ];
    }
    return $prop_or_slot_id;
  }

  /**
   * Customize slot or prop form elements (pre-render).
   *
   * @param array $element
   *   Element to process.
   *
   * @return array
   *   Processed element
   */
  public static function preRenderPropOrSlot(array $element): array {
    // Set by the closed states. The hidden form still submits; it is
    // shown back on validation errors.
    if (isset($element['source']) && !empty($element['source']['#ui_patterns_hidden'])) {
      static::hideSource($element['source']);
    }
    if ($prop_or_slot_id = static::checkDetailsElement($element)) {
      $children_keys = Element::children($element);
      foreach ($children_keys as $child_key) {
        if ($child_key === $prop_or_slot_id) {
          continue;
        }
        $element[$prop_or_slot_id][] = $element[$child_key];
        $element[$child_key]['#printed'] = TRUE;
      }
    }
    return $element;
  }

  /**
   * Wraps a source form in a hidden div; its inputs keep submitting.
   */
  protected static function hideSource(array &$source): void {
    $source['#prefix'] = '<div hidden>' . ($source['#prefix'] ?? '');
    $source['#suffix'] = ($source['#suffix'] ?? '') . '</div>';
  }

  /**
   * Customize slot or prop form elements (process).
   *
   * A box holding an open selector stays open, and so does the box the
   * last action came from: its refreshed content carries the result and
   * the control taking the focus.
   *
   * @param array $element
   *   Element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed element.
   */
  public static function processPropOrSlot(array &$element, FormStateInterface $form_state) {
    if ($prop_or_slot_id = static::checkDetailsElement($element)) {
      if (static::isSelectorOpen($element) || static::isTriggeredWithin($element['#array_parents'] ?? [], $form_state)) {
        $element[$prop_or_slot_id]['#open'] = TRUE;
      }
    }
    return $element;
  }

  /**
   * Whether the action being processed was triggered inside these parents.
   *
   * @param array $array_parents
   *   The array parents of the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE when the triggering element sits at or below them.
   */
  protected static function isTriggeredWithin(array $array_parents, FormStateInterface $form_state): bool {
    $trigger = $form_state->getTriggeringElement();
    $trigger_parents = \is_array($trigger) ? ($trigger['#array_parents'] ?? NULL) : NULL;
    if (!\is_array($trigger_parents) || \count($array_parents) === 0) {
      return FALSE;
    }
    return \array_slice($trigger_parents, 0, \count($array_parents)) === $array_parents;
  }

  /**
   * Get a unique element id based on the parents and a parameter.
   */
  protected static function getElementId(array $element, string $base_id): string {
    $parents = (\array_key_exists('#array_parents', $element) && \is_array($element['#array_parents']))
      ? $element['#array_parents'] : [];
    return (\count($parents) > 0)
      ? Html::getId(\implode('_', $parents) . '_' . $base_id)
      : Html::getId($base_id);
  }

  /**
   * Expand each ajax element with ajax urls.
   *
   * @param array $element
   *   The ajax element.
   *
   * @return array
   *   The extended ajax form.
   */
  protected static function expandAjax(array $element): array {
    $url = $element['#ajax_url'] ?? NULL;
    if (isset($element['#ajax']) && $url) {
      $element['#ajax']['url'] = $url;
      // Core replaces the options of the URL with these: keep the host's.
      if ($url instanceof Url) {
        $element['#ajax']['options'] = $url->getOptions();
      }
    }
    return $element;
  }

  /**
   * Helper function to get the currently selected component ID.
   *
   * @param array $element
   *   The form element.
   *
   * @return string|null
   *   The selected component ID or NULL if not set.
   */
  protected static function getSelectedComponentId(array $element): ?string {
    return $element['#component_id'] ?? $element['#default_value']['component_id'] ?? NULL;
  }

  /**
   * Helper function to return the component.
   */
  protected static function getComponent(array $element): ?Component {
    $component_id = self::getSelectedComponentId($element);
    $component_plugin_manager = \Drupal::service(ComponentPluginManager::class);
    return $component_id ? $component_plugin_manager->find($component_id) : NULL;
  }

  /**
   * Get sources for a prop or slot, ordered.
   *
   * @param string $prop_or_slot_id
   *   The prop or slot ID.
   * @param array $definition
   *   The prop or slot definition.
   * @param array $element
   *   The form element.
   *
   * @return array<string, \Drupal\ui_patterns\SourceInterface>
   *   The sources, ordered.
   */
  protected static function getSources(string $prop_or_slot_id, array $definition, array $element): array {
    $configuration = $element['#default_value'] ?? [];
    $source_contexts = $element['#source_contexts'] ?? [];
    $form_array_parents = $element['#array_parents'] ?? [];
    $tag_filter = $element['#tag_filter'] ?? [];
    $source_plugin_manager = \Drupal::service(SourcePluginManager::class);
    /** @var \Drupal\ui_patterns\PropTypeInterface $prop_type */
    $prop_type = empty($definition) ? $source_plugin_manager->getSlotPropType() : $definition['ui_patterns']['type_definition'];
    $default_source_id = static::getDefaultSourceId($definition);
    $sources = $source_plugin_manager->getDefinitionsForPropType($prop_type->getPluginId(), $source_contexts, $tag_filter);
    $source_ids = \array_keys($sources);
    $source_ids = \array_combine($source_ids, $source_ids);
    if (empty($source_ids)) {
      return [];
    }
    $valid_sources = $source_plugin_manager->createInstances($source_ids, SourcePluginBase::buildConfiguration($prop_or_slot_id, $definition, $configuration, $source_contexts, $form_array_parents));
    foreach ($valid_sources as &$source) {
      /** @var \Drupal\ui_patterns\SourcePluginBase $source */
      $source_id = $source->getPluginId();
      $source->setConfiguration(\array_merge($source->getConfiguration(), [
        'selection' => [
          'default' => ($source_id === $default_source_id),
          'tags' => $sources[$source_id]['tags'] ?? [],
        ],
      ]));
    }
    unset($source);
    return static::orderSources($valid_sources, $default_source_id);
  }

  /**
   * Helper function to get the prop ID from an element.
   *
   * @param array $element
   *   The form element.
   *
   * @return string
   *   The prop ID or empty string if not set.
   */
  protected static function getSlotId(array $element): string {
    return (string) ($element['#slot_id'] ?? '');
  }

  /**
   * Get the default source id declared by the prop type.
   *
   * @param array $definition
   *   The prop definition, empty for a slot.
   *
   * @return string|null
   *   The default source id, or NULL when the prop type declares none.
   */
  protected static function getDefaultSourceId(array $definition): ?string {
    $source_plugin_manager = \Drupal::service(SourcePluginManager::class);
    /** @var \Drupal\ui_patterns\PropTypeInterface $prop_type */
    $prop_type = empty($definition) ? $source_plugin_manager->getSlotPropType() : $definition['ui_patterns']['type_definition'];
    $prop_plugin_definition = $prop_type->getPluginDefinition();
    return (\is_array($prop_plugin_definition) && isset($prop_plugin_definition['default_source'])) ? $prop_plugin_definition['default_source'] : NULL;
  }

  /**
   * Get source definitions for a prop or slot, ordered, without instances.
   *
   * Same candidates and order as getSources(), but nothing is instantiated.
   *
   * @param string $prop_or_slot_id
   *   The prop or slot ID.
   * @param array $definition
   *   The prop or slot definition.
   * @param array $element
   *   The form element.
   *
   * @return array<string, array>
   *   The source definitions, ordered.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  protected static function getSourceDefinitions(string $prop_or_slot_id, array $definition, array $element): array {
    $source_contexts = $element['#source_contexts'] ?? [];
    $tag_filter = $element['#tag_filter'] ?? [];
    $source_plugin_manager = \Drupal::service(SourcePluginManager::class);
    /** @var \Drupal\ui_patterns\PropTypeInterface $prop_type */
    $prop_type = empty($definition) ? $source_plugin_manager->getSlotPropType() : $definition['ui_patterns']['type_definition'];
    $definitions = $source_plugin_manager->getDefinitionsForPropType($prop_type->getPluginId(), $source_contexts, $tag_filter);
    return static::orderSourceDefinitions($definitions, static::getDefaultSourceId($definition));
  }

  /**
   * Order source definitions: default first, then native, then converted.
   *
   * Definition-level twin of orderSources().
   *
   * @param array<string, array> $definitions
   *   The source definitions.
   * @param string|null $default_source_id
   *   The default source id.
   *
   * @return array<string, array>
   *   The ordered source definitions.
   */
  protected static function orderSourceDefinitions(array $definitions, ?string $default_source_id): array {
    $returned = [];
    if ($default_source_id && isset($definitions[$default_source_id])) {
      $returned[$default_source_id] = $definitions[$default_source_id];
    }
    $native = [];
    $converted = [];
    foreach ($definitions as $source_id => $source_definition) {
      if ($source_id === $default_source_id) {
        continue;
      }
      if (\in_array(SourceTags::PropTypeNative->value, $source_definition['tags'] ?? [], TRUE)) {
        $native[$source_id] = $source_definition;
      }
      else {
        $converted[$source_id] = $source_definition;
      }
    }
    $by_label = static function (array $a, array $b): int {
      return \strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    };
    \uasort($native, $by_label);
    \uasort($converted, $by_label);
    return $returned + $native + $converted;
  }

  /**
   * Resolve the source id shown while the source selector is closed.
   *
   * @param array $configuration
   *   The prop configuration.
   * @param array<string, array> $definitions
   *   The ordered source definitions.
   *
   * @return string|null
   *   The configured source id when still available, else the first widget
   *   source, else NULL.
   */
  protected static function resolveClosedSourceId(array $configuration, array $definitions): ?string {
    $source_id = $configuration['source_id'] ?? NULL;
    if ($source_id && isset($definitions[$source_id])) {
      return $source_id;
    }
    foreach ($definitions as $candidate_id => $source_definition) {
      if (\in_array(SourceTags::Widget->value, $source_definition['tags'] ?? [], TRUE)) {
        return $candidate_id;
      }
    }
    return NULL;
  }

  /**
   * Create a single configured source instance.
   *
   * Instance-level twin of the tail of getSources(), for one source id.
   *
   * @param string $source_id
   *   The source id, present in $source_definition.
   * @param array $source_definition
   *   The source definition.
   * @param string $prop_or_slot_id
   *   The prop or slot ID.
   * @param array $definition
   *   The prop or slot definition.
   * @param array $element
   *   The form element.
   *
   * @return \Drupal\ui_patterns\SourceInterface
   *   The source instance.
   */
  protected static function createSourceInstance(string $source_id, array $source_definition, string $prop_or_slot_id, array $definition, array $element): SourceInterface {
    $configuration = $element['#default_value'] ?? [];
    $source_contexts = $element['#source_contexts'] ?? [];
    $form_array_parents = $element['#array_parents'] ?? [];
    $source_plugin_manager = \Drupal::service(SourcePluginManager::class);
    /** @var \Drupal\ui_patterns\SourcePluginBase $source */
    $source = $source_plugin_manager->createInstance($source_id, SourcePluginBase::buildConfiguration($prop_or_slot_id, $definition, $configuration, $source_contexts, $form_array_parents));
    $source->setConfiguration(\array_merge($source->getConfiguration(), [
      'selection' => [
        'default' => ($source_id === static::getDefaultSourceId($definition)),
        'tags' => $source_definition['tags'] ?? [],
      ],
    ]));
    return $source;
  }

  /**
   * Whether a source selector is open in this prop or slot.
   *
   * The state rides the _open input, written by toggleSelectorSubmit(): the
   * form builds from the raw input, and hosts which rebuild their form state
   * on every request — Views, the CKEditor 5 dialog — keep the input only.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE when open.
   */
  protected static function isSelectorOpen(array $element): bool {
    return !empty($element['#default_value']['_open']);
  }

  /**
   * Build the submit button which opens or closes the source selector.
   *
   * A real submit button: without JavaScript it triggers a full POST rebuild
   * and the selector renders inline.
   *
   * @param array $element
   *   The prop form element.
   * @param string $prop_id
   *   The prop ID.
   * @param string $row_wrapper_id
   *   The id of the wrapper div around the whole prop form.
   * @param string $operation
   *   Either 'open', 'close' or 'cancel'.
   * @param string|\Stringable|null $label
   *   The visible label, NULL for the operation default.
   *
   * @return array
   *   The button render array.
   */
  protected static function buildSelectorToggleButton(array $element, string $prop_id, string $row_wrapper_id, string $operation, string|\Stringable|null $label = NULL): array {
    $title = (string) ($element['#title'] ?? $prop_id);
    $values = [
      // The prop goes in the name: a screen reader lists buttons out of
      // context. The visible word comes first: voice control says it.
      'open' => \t('Edit'),
      // Save keeps the typed values in the form; the host form persists
      // them on its own submit.
      'close' => \t('Save'),
      // Cancel restores the values the row opened with.
      'cancel' => \t('Cancel'),
    ];
    $label ??= $values[$operation];
    $button = [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => \strtr($prop_id . '_' . \implode('-', $element['#array_parents']) . '_' . $operation, '-', '_'),
      '#submit' => [
        static::class . '::toggleSelectorSubmit',
      ],
      // Read by ComponentForm::validateFormElement() to skip the component
      // render validation.
      '#ui_patterns' => TRUE,
      '#ui_patterns_prop_parents' => $element['#parents'],
      '#ui_patterns_selector_operation' => $operation,
      // Validate nothing: the toggle rides the raw input only. Partial
      // values mislead hosts which rebuild their structure from them —
      // core's FieldBlock falls back to the default formatter, losing
      // this very form.
      '#limit_validation_errors' => [],
      '#button_type' => ($operation === 'close') ? 'primary' : NULL,
      '#attributes' => [
        'formnovalidate' => 'formnovalidate',
        'aria-label' => \t('@label: @prop', ['@label' => $label, '@prop' => $title]),
        'class' => [
          'uip-source-selector-toggle',
          'uip-source-selector-toggle--' . $operation,
        ],
      ],
      '#ajax' => [
        'callback' => [static::class, 'refreshSelectorForm'],
        'wrapper' => $row_wrapper_id,
        'effect' => 'fade',
        'progress' => ['type' => 'fullscreen'],
        // Click, not the mousedown core binds on buttons: the browser
        // fires the field's blur and change first, so the edit reaches a
        // host watching the form before this request replaces the row.
        'event' => 'click',
      ],
    ];
    $button['#id'] = static::getElementId($element, $button['#name']);
    return $button;
  }

  /**
   * The close and cancel controls of an open row.
   *
   * The snapshot hidden holds the row input at open time, written by
   * toggleSelectorSubmit(); cancel restores it. A row added open has none:
   * its cancel drops the row.
   *
   * @param array $element
   *   The element the buttons act on; carries the parents and title.
   * @param string $prop_id
   *   The prop or slot ID.
   * @param string $row_wrapper_id
   *   The id of the wrapper div around the row.
   *
   * @return array
   *   The controls, keyed by element name.
   */
  protected static function buildOpenRowControls(array $element, string $prop_id, string $row_wrapper_id): array {
    return [
      '_close' => static::buildSelectorToggleButton($element, $prop_id, $row_wrapper_id, 'close'),
      '_cancel' => static::buildSelectorToggleButton($element, $prop_id, $row_wrapper_id, 'cancel'),
      '_snapshot' => ['#type' => 'hidden'],
    ];
  }

  /**
   * Submit handler: open, close or cancel a source selector, then rebuild.
   *
   * Raw input only: a form-state value written here would hand hosts
   * rebuilding their structure from values a partial tree.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  public static function toggleSelectorSubmit(array $form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $base = $trigger['#ui_patterns_prop_parents'];
    $operation = $trigger['#ui_patterns_selector_operation'];
    $input = $form_state->getUserInput();
    if ($operation === 'cancel') {
      $snapshot = NestedArray::getValue($input, \array_merge($base, ['_snapshot']));
      $typed = NestedArray::getValue($input, $base);
      NestedArray::unsetValue($input, $base);
      $row = \is_string($snapshot) && $snapshot !== '' ? \json_decode($snapshot, TRUE) : NULL;
      if (\is_array($row)) {
        unset($row['_open'], $row['_snapshot']);
        NestedArray::setValue($input, $base, $row);
      }
      // Read by announceValues().
      $form_state->setTemporaryValue(static::restoredKey($base), static::rowValues($typed) !== static::rowValues($row));
    }
    else {
      if ($operation === 'open') {
        $row = NestedArray::getValue($input, $base);
        if (\is_array($row)) {
          unset($row['_open'], $row['_snapshot']);
          NestedArray::setValue($input, \array_merge($base, ['_snapshot']), \json_encode($row));
        }
      }
      else {
        NestedArray::unsetValue($input, \array_merge($base, ['_snapshot']));
      }
      NestedArray::setValue($input, \array_merge($base, ['_open']), ($operation === 'open') ? '1' : '');
    }
    $form_state->setUserInput($input);
    $form_state->setRebuild();
  }

  /**
   * Ajax handler: refresh a whole prop form.
   */
  public static function refreshSelectorForm(array $form, FormStateInterface $form_state): mixed {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    // One step up from the button: recorded paths go stale in nested
    // forms.
    $parents = \array_slice($triggering_element['#array_parents'], 0, -1);
    $form_state->setRebuild(TRUE);
    $returned = NestedArray::getValue($form, $parents);
    $returned = static::announceValues($returned, $triggering_element, $form_state);
    $response = new AjaxResponse();
    // The wrapper div is the element's own #prefix: replace only its content.
    $returned['#prefix'] = '';
    $returned['#suffix'] = '';
    $response->addCommand(new HtmlCommand('#' . $wrapper_id, $returned));
    // The button which triggered the refresh went away with the old row.
    $response->addCommand(new FocusFirstCommand(static::getRowFocusSelector($returned, $wrapper_id)));
    if ($message = static::foldMessage($returned, $triggering_element)) {
      $response->addCommand(new AnnounceCommand($message));
    }
    return $response;
  }

  /**
   * What a screen reader hears once a row folds; sighted users see the box.
   *
   * @param array $row
   *   The refreshed prop or slot row.
   * @param array $trigger
   *   The triggering element.
   *
   * @return string|null
   *   The message, or NULL when the row only opened.
   */
  protected static function foldMessage(array $row, array $trigger): ?string {
    $title = ($row['#title'] ?? '') ?: ($row['_box']['#title'] ?? $row['#prop_id'] ?? '');
    return match ($trigger['#ui_patterns_selector_operation'] ?? '') {
      'close' => (string) \t('@prop: values kept.', ['@prop' => $title]),
      'cancel' => (string) \t('@prop: values put back.', ['@prop' => $title]),
      default => NULL,
    };
  }

  /**
   * Marks a cancelled row whose values changed, for the page to hear.
   *
   * Saving keeps what the browser already reported; opening changes nothing.
   *
   * @param array $row
   *   The refreshed prop or slot row.
   * @param array $trigger
   *   The triggering element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The row, marked when a cancel changed its values.
   */
  protected static function announceValues(array $row, array $trigger, FormStateInterface $form_state): array {
    if (!isset($row['source_id']) || ($trigger['#ui_patterns_selector_operation'] ?? '') !== 'cancel') {
      return $row;
    }
    if (!$form_state->getTemporaryValue(static::restoredKey($trigger['#ui_patterns_prop_parents']))) {
      return $row;
    }
    // Read by js/source-selector.js.
    $row['source_id']['#attributes']['data-uip-announce'] = 'true';
    return $row;
  }

  /**
   * The form state key telling whether a cancel changed the row's values.
   *
   * @param array $base
   *   The row's parents.
   *
   * @return array
   *   The temporary value key.
   */
  protected static function restoredKey(array $base): array {
    return ['ui_patterns', 'restored', \implode('.', $base)];
  }

  /**
   * The posted values of a row, without the controls carrying its state.
   *
   * @param mixed $row
   *   The row's user input, or NULL when the row posted nothing.
   *
   * @return array
   *   The values.
   */
  protected static function rowValues(mixed $row): array {
    if (!\is_array($row)) {
      return [];
    }
    return \array_filter($row, static fn ($key): bool => !\str_starts_with((string) $key, '_'), \ARRAY_FILTER_USE_KEY);
  }

  /**
   * The control taking the focus once an action refreshed a row.
   *
   * Open: the source select, or Save when there is nothing to pick.
   * Closed: the button opening the selector again, else the row itself,
   * whose box summary takes the focus.
   *
   * @param array $row
   *   The refreshed prop or slot row.
   * @param string $fallback_id
   *   The id to focus into when the row carries neither control.
   *
   * @return string
   *   A selector for FocusFirstCommand.
   */
  protected static function getRowFocusSelector(array $row, string $fallback_id): string {
    if (isset($row['_close']['#id'])) {
      $target = (($row['source_id']['#type'] ?? '') === 'select') ? $row['source_id'] : $row['_close'];
      return '#' . $target['#id'];
    }
    return '#' . ($row['_configure']['#id'] ?? $fallback_id);
  }

  /**
   * Add the summary standing in for a non-widget source, and the open button.
   *
   * Shared by prop and slot rows.
   *
   * @param array $form
   *   The closed row.
   * @param array $element
   *   The element the button acts on; carries the parents and title.
   * @param string $prop_or_slot_id
   *   The prop or slot ID.
   * @param string $row_wrapper_id
   *   The id of the wrapper div around the row.
   * @param \Drupal\ui_patterns\SourceInterface|null $selected_source
   *   The source shown while closed, NULL when the row has none.
   *
   * @return array
   *   The closed row.
   */
  protected static function addClosedSummaryAndButton(array $form, array $element, string $prop_or_slot_id, string $row_wrapper_id, ?SourceInterface $selected_source): array {
    $is_widget = $selected_source && static::isWidget($selected_source);
    if ($selected_source && !$is_widget) {
      // Read by preRenderPropOrSlot() and preRenderSourceRow().
      $form['source']['#ui_patterns_hidden'] = TRUE;
      $form['summary'] = static::buildSourceSummary($selected_source);
    }
    elseif (!$selected_source) {
      $form['summary'] = static::buildSourceSummary(NULL);
    }
    // A widget is edited in place: its button only switches the source.
    $label = match (TRUE) {
      !$selected_source => \t('Choose source'),
      $is_widget => \t('Change source'),
      default => NULL,
    };
    $form['_configure'] = static::buildSelectorToggleButton($element, $prop_or_slot_id, $row_wrapper_id, 'open', $label);
    return $form;
  }

  /**
   * Whether a source is a widget, edited in place.
   */
  protected static function isWidget(SourceInterface $source): bool {
    return \in_array(SourceTags::Widget->value, $source->getConfiguration()['selection']['tags'] ?? [], TRUE);
  }

  /**
   * Build the summary standing in for a closed source form.
   *
   * @param \Drupal\ui_patterns\SourceInterface|null $source
   *   The source instance, NULL when the prop has no source yet.
   *
   * @return array
   *   The summary render array.
   */
  protected static function buildSourceSummary(?SourceInterface $source): array {
    if (!$source) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['uip-source-summary', 'uip-source-summary--empty']],
        'empty' => [
          '#markup' => '<em>' . \t('Not set') . '</em>',
        ],
      ];
    }
    // Source name and choice label first, then the settings summary.
    $summary = \array_filter($source->settingsSummary(), static fn ($item): bool => \trim((string) $item) !== '');
    $name = $source->label();
    $with_choice = $source->label(TRUE);
    \array_unshift($summary, ($with_choice !== $name) ? $name . ' ' . $with_choice : $name);
    $summary = static::nestSummaryLines(\array_values($summary));
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['uip-source-summary']],
      'items' => [
        '#theme' => 'item_list',
        '#items' => $summary,
      ],
    ];
  }

  /**
   * Turns indent-prefixed summary lines into nested list items.
   *
   * @param array<string|\Stringable> $lines
   *   The lines, two NBSP per level.
   *
   * @return array
   *   Nested item_list items.
   */
  protected static function nestSummaryLines(array $lines): array {
    $indent = "\u{00A0}\u{00A0}";
    $rows = [];
    foreach ($lines as $line) {
      $line = (string) $line;
      $depth = 0;
      while (\str_starts_with($line, $indent)) {
        ++$depth;
        $line = \substr($line, \strlen($indent));
      }
      $rows[] = [$depth, $line];
    }
    $i = 0;
    return static::buildSummaryLevel($rows, $i, 0);
  }

  /**
   * Builds one nesting level; deeper rows become a sub-list of their line.
   */
  protected static function buildSummaryLevel(array $rows, int &$i, int $depth): array {
    $items = [];
    while ($i < \count($rows)) {
      [$row_depth, $text] = $rows[$i];
      if ($row_depth < $depth) {
        break;
      }
      if ($row_depth > $depth) {
        $sub = static::buildSummaryLevel($rows, $i, $row_depth);
        $last = \array_key_last($items);
        if ($last === NULL) {
          $items[] = ['list' => ['#theme' => 'item_list', '#items' => $sub]];
        }
        else {
          $items[$last]['list'] = ['#theme' => 'item_list', '#items' => $sub];
        }
        continue;
      }
      $items[] = ['#markup' => Html::escape($text)];
      ++$i;
    }
    return $items;
  }

  /**
   * Get source plugin form.
   */
  protected static function getSourcePluginForm(FormStateInterface $form_state, ?SourceInterface $source, string $wrapper_id): array {
    if (!$source) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'id' => $wrapper_id,
        ],
      ];
    }
    $form = $source->settingsForm([], $form_state);
    // @phpstan-ignore-next-line
    $form['#prefix'] = static::sourceWrapper($wrapper_id) . ($form['#prefix'] ?? '');
    $form['#suffix'] = ($form['#suffix'] ?? '') . '</div>';
    // Weird, but :switchSourceForm() AJAX handler doesn't work without that.
    foreach (Element::children($form) as $child) {
      if (isset($form[$child]['#description']) && !isset($form[$child]['#description_display'])) {
        $form[$child]['#description_display'] = 'after';
      }
    }
    return $form;
  }

  /**
   * Ajax handler: Switch source plugin form.
   */
  public static function switchSourceForm(array $form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $subform = NestedArray::getValue($form, \array_slice($trigger['#array_parents'], 0, -1));
    $source = $subform['source'];
    // The form landing here holds values the browser never reported.
    $wrapper_id = $trigger['#ajax']['wrapper'];
    if (isset($source['#prefix'])) {
      $source['#prefix'] = \str_replace(static::sourceWrapper($wrapper_id), static::sourceWrapper($wrapper_id, TRUE), (string) $source['#prefix']);
    }
    else {
      $source['#attributes']['data-uip-announce'] = 'true';
    }
    return $source;
  }

  /**
   * The opening tag of the div a source form is replaced within.
   *
   * @param string $wrapper_id
   *   The AJAX wrapper id.
   * @param bool $announce
   *   TRUE to carry the marker read by js/source-selector.js.
   *
   * @return string
   *   The tag.
   */
  protected static function sourceWrapper(string $wrapper_id, bool $announce = FALSE): string {
    return "<div id='" . $wrapper_id . "'" . ($announce ? ' data-uip-announce="true"' : '') . '>';
  }

  /**
   * Get selected source plugin.
   */
  protected static function getSelectedSource(array $configuration, array $sources, bool $select_default = TRUE): ?SourceInterface {
    $source_id = $configuration['source_id'] ?? NULL;
    foreach ($sources as $source) {
      if ($source->getPluginId() === $source_id) {
        return $source;
      }
    }
    return $select_default ? static::selectDefaultSource($sources) : NULL;
  }

  /**
   * Select default source plugin from a list.
   *
   * @param array $sources
   *   The sources.
   *
   * @return \Drupal\ui_patterns\SourceInterface|null
   *   The selected source or NULL.
   */
  protected static function selectDefaultSource(array $sources): ?SourceInterface {
    // Try to return the first widget source.
    foreach ($sources as $source) {
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $plugin_definition = $source->getPluginDefinition() ?? [];
      $tags = \is_array($plugin_definition) ? ($plugin_definition['tags'] ?? []) : [];
      if (\in_array(SourceTags::Widget->value, $tags, TRUE)) {
        return $source;
      }
    }
    return NULL;
  }

  /**
   * Add required visual clue to the fieldset.
   *
   * The proper required control is managed by SourcePluginBase::addRequired()
   * so the visual clue is present whether or not the control is done by the
   * source plugin. This is feature, not a bug.
   */
  protected static function addRequired(array $element, string $prop_id): array {
    $component = static::getComponent($element);
    if (!$component || !isset($component->metadata->schema['required'])) {
      return $element;
    }
    $required_props = $component->metadata->schema['required'];
    if (!\in_array($prop_id, $required_props, TRUE)) {
      return $element;
    }
    $element['#required'] = TRUE;
    return $element;
  }

  /**
   * Add title and description to the source element.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The element with title and description.
   */
  protected static function addTitleAndDescription(array $element): array {
    if (isset($element['source']['value'])) {
      $element['source']['value']['#title_display'] = 'before';
      if (empty($element['source']['value']['#title'])) {
        $element['source']['value']['#title'] = $element['#title'];
      }
      if (empty($element['source']['value']['#description'])) {
        $element['source']['value']['#description'] = $element['#description'] ?? NULL;
      }
    }
    return $element;
  }

  /**
   * Alter the element after the form is built.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The altered element.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    if ($form_state->isProcessingInput()) {
      static::elementValidate($element, $form_state);
      static::resetSwitchedSiblingsInput($element, $form_state);
    }
    // Again once built: a remove with an error elsewhere skips the rebuild,
    // and the first pass processed the box before knowing its own button.
    $box = $element['#prop_id'] ?? self::getSlotId($element);
    if (($element[$box]['#type'] ?? '') === 'details' && static::isTriggeredWithin($element['#array_parents'] ?? [], $form_state)) {
      $element[$box]['#open'] = TRUE;
    }
    return $element;
  }

  /**
   * Lets a sub-form loaded by a switch select start from its default values.
   *
   * An AJAX request is processed twice: once with the submitted input, then
   * again as a rebuild, which is what the browser receives. Our forms build
   * the sub-form of the newly selected source, component, block or formatter
   * from the raw input, so it already exists in the first pass. In that pass
   * core writes a NULL entry into the user input for every element it cannot
   * find in the request (FormBuilder::handleInputElement()), and the rebuild
   * reads NULL as "unchecked" or "nothing selected" rather than applying the
   * element's #default_value. A source setting that defaults to TRUE thus
   * comes back unchecked the moment its source is selected, and is saved as
   * FALSE.
   *
   * The browser sends nothing for a new element and nothing for a box the
   * user unchecked, so the input alone cannot tell them apart. The select
   * that triggered the request can: when it carries
   * '#ui_patterns_resets_siblings', the elements next to it are new. Their
   * NULL and empty entries are dropped from the user input, so the rebuild
   * finds no input and applies the defaults. Entries with a real value are
   * kept, and elements outside the select's siblings are not touched: an
   * unchecked box elsewhere in the form stays unchecked.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected static function resetSwitchedSiblingsInput(array $element, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    if ($form_state->isRebuilding() || empty($trigger['#ui_patterns_resets_siblings'])) {
      return;
    }
    $trigger_parents = $trigger['#array_parents'] ?? [];
    $element_parents = $element['#array_parents'] ?? [];
    $depth = \count($element_parents);
    if (\array_slice($trigger_parents, 0, $depth) !== $element_parents) {
      return;
    }
    $parent = NestedArray::getValue($element, \array_slice($trigger_parents, $depth, -1));
    $trigger_key = (string) \end($trigger_parents);
    $input = $form_state->getUserInput();
    $changed = FALSE;
    foreach (Element::children($parent) as $key) {
      if ((string) $key !== $trigger_key) {
        self::dropEmptyInput($parent[$key], $input, $changed);
      }
    }
    if ($changed) {
      $form_state->setUserInput($input);
    }
  }

  /**
   * Drops the NULL and empty entries core wrote for elements missing input.
   *
   * Children first: a container only empties once its children are gone.
   *
   * @param array $element
   *   The element.
   * @param array $input
   *   The user input.
   * @param bool $changed
   *   Set when an entry is dropped.
   */
  private static function dropEmptyInput(array $element, array &$input, bool &$changed): void {
    foreach (Element::children($element) as $key) {
      self::dropEmptyInput($element[$key], $input, $changed);
    }
    if (!isset($element['#parents'])) {
      return;
    }
    $exists = FALSE;
    $value = NestedArray::getValue($input, $element['#parents'], $exists);
    if (!$exists) {
      return;
    }
    // Inputs drop their NULL and empty entries; any element drops the
    // array skeleton its children left, or an input-reading value
    // callback would trust it over the defaults.
    if ((!empty($element['#input']) && $value === NULL) || $value === []) {
      NestedArray::unsetValue($input, $element['#parents']);
      $changed = TRUE;
    }
  }

  /**
   * Alter the element during validation.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function elementValidate(array &$element, FormStateInterface $form_state): void {
    // For browser-submitted forms, the submitted values do not contain
    // values for certain elements (empty multiple select, unchecked
    // checkbox). Child elements are processed after the parent element,
    // The processed values, stored in the form_state, are propagated
    // to the '#value' of the element here (parents will process).
    $element['#value'] = $form_state->getValue($element['#parents']);
    // Values are cleaned
    // similarly to $form_state->cleanValues();
    if (!empty($element['#value'])) {
      static::cleanValues($element['#value']);
    }
  }

  /**
   * Clean values.
   *
   * @param array $value
   *   Value to clean.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  protected static function cleanValues(array &$value): void {}

  /**
   * Build sources selector widget.
   *
   * @param array $sources
   *   The candidate sources.
   * @param \Drupal\ui_patterns\SourceInterface|null $selected_source
   *   The selected source, if any.
   * @param string $wrapper_id
   *   The AJAX wrapper id.
   * @param bool $always_visible
   *   TRUE to show the selector even with one selected candidate.
   */
  protected static function buildSourceSelector(array $sources, ?SourceInterface $selected_source, string $wrapper_id, bool $always_visible = FALSE): array {
    if (empty($sources)) {
      return [];
    }
    if (!$always_visible && $selected_source && (\count($sources) === 1)) {
      return [
        '#type' => 'hidden',
        '#value' => \array_keys($sources)[0],
        // To allow the AJAX to work.
        '#ajax' => [
          'callback' => [static::class, 'switchSourceForm'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];
    }
    $options = static::sourcesToOptions($sources, TRUE, $selected_source);
    return [
      '#type' => 'select',
      '#options' => $options,
      '#title' => \t('Source'),
      '#default_value' => $selected_source?->getPluginId(),
      '#attributes' => [
        'class' => ['uip-source-selector'],
      ],
      '#empty_option' => \t('- Select -'),
      '#ajax' => [
        'callback' => [static::class, 'switchSourceForm'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
      // Read by resetSwitchedSiblingsInput().
      '#ui_patterns_resets_siblings' => TRUE,
    ];
  }

  /**
   * Order sources according to a strategy.
   *
   * @param array<string, \Drupal\ui_patterns\SourceInterface> $sources
   *   The sources to order.
   * @param string $default_source_id
   *   The default source id.
   *
   * @return array<string, \Drupal\ui_patterns\SourceInterface>
   *   The ordered sources.
   */
  protected static function orderSources(array $sources, string $default_source_id): array {
    $returned = [];
    if ($default_source_id && isset($sources[$default_source_id])) {
      $returned[$default_source_id] = $sources[$default_source_id];
    }
    $native_sources = \array_filter($sources, static function ($source) use ($default_source_id) {
      /** @var \Drupal\ui_patterns\SourcePluginBase $source */
      return ($source->getPluginId() !== $default_source_id) && \in_array('prop_type_compatibility:native', $source->getConfiguration()['selection']['tags'] ?? [], TRUE);
    });
    $converted_sources = \array_filter($sources, static function ($source) use ($default_source_id) {
      /** @var \Drupal\ui_patterns\SourcePluginBase $source */
      return ($source->getPluginId() !== $default_source_id) && !\in_array('prop_type_compatibility:native', $source->getConfiguration()['selection']['tags'] ?? [], TRUE);
    });
    \uasort($native_sources, static function ($a, $b) {
      return \strcasecmp($a->label(), $b->label());
    });
    \uasort($converted_sources, static function ($a, $b) {
      return \strcasecmp($a->label(), $b->label());
    });
    foreach ($native_sources as $source) {
      $returned[$source->getPluginId()] = $source;
    }
    foreach ($converted_sources as $source) {
      $returned[$source->getPluginId()] = $source;
    }
    return $returned;
  }

  /**
   * Get selected source plugin.
   */
  protected static function sourcesToOptions(array $sources, bool $use_group = TRUE, ?SourceInterface $selected = NULL): array {
    $options = [];
    $context_switchers = [];
    foreach ($sources as $valid_source_plugin) {
      $plugin_configuration = $valid_source_plugin->getConfiguration();

      $definition = $valid_source_plugin->getPluginDefinition();
      $label = (string) $definition['label'];
      if ($use_group && isset($plugin_configuration['selection'], $plugin_configuration['selection']['tags']) && \in_array(SourceTags::ContextSwitcher->value, $plugin_configuration['selection']['tags'], TRUE)) {
        $context_switchers[$valid_source_plugin->getPluginId()] = $label;
        continue;
      }
      $source_id = $valid_source_plugin->getPluginId();
      if (($definition['no_ui'] ?? FALSE) && ($source_id !== $selected?->getPluginId())) {
        // We don't hide no_ui sources if they are already selected.
        continue;
      }
      $options[$source_id] = $label;
    }
    if ($context_switchers) {
      $label = (string) \t('More data');
      $options[$label] = $context_switchers;
    }
    return $options;
  }

}
