<?php

declare(strict_types=1);

namespace Drupal\attribution\Plugin\Field\FieldWidget;

use Drupal\attribution\Entity\AttributionLicense;
use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Base class for attribution field widgets.
 */
abstract class AttributionWidgetBase extends WidgetBase {

  /**
   * Builds the license select element options for the current field.
   */
  protected function buildLicenseOptions(array $field_settings): array {
    $options = [];
    /** @var \Drupal\attribution\Entity\AttributionLicense $license */
    foreach (AttributionLicense::loadMultiple() as $license) {
      $options[$license->getId()] = $license->getName();
    }
    return $field_settings['licenses'] ? array_intersect_key($options, $field_settings['licenses']) : $options;
  }

  /**
   * Builds the creation-type select and its conditionally visible AI fields.
   *
   * 'creation_type', 'ai_tool', and 'ai_prompt' are added as direct children
   * of $element rather than nested inside a container, so the #states
   * selector built by getFieldElementSelector() stays a simple sibling
   * lookup. 'prompt_editor_name'/'prompt_editor_link' are nested inside a
   * 'prompt_editor' inline container (matching the 'source'/'author'
   * containers built by concrete widgets), flattened back out in
   * massageFormValues().
   */
  protected function buildCreationTypeElements(array $element, int $delta, FieldItemListInterface $items): array {
    $element['creation_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Creation type'),
      '#options' => AttributionItem::creationTypeOptions(),
      '#empty_option' => $this->t('- Unspecified -'),
      '#default_value' => $items[$delta]->creation_type ?? NULL,
    ];

    $creation_type_selector = $this->getFieldElementSelector($element, $delta, 'creation_type');
    $ai_states = [
      'visible' => [
        [$creation_type_selector => ['value' => AttributionItem::CREATION_TYPE_AI_GENERATED]],
        'or',
        [$creation_type_selector => ['value' => AttributionItem::CREATION_TYPE_AI_MODIFIED]],
      ],
    ];

    $element['ai_tool'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AI tool'),
      '#default_value' => $items[$delta]->ai_tool ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('e.g. Midjourney v6'),
      '#states' => $ai_states,
    ];
    $element['ai_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI prompt'),
      '#default_value' => $items[$delta]->ai_prompt ?? NULL,
      '#rows' => 2,
      '#states' => $ai_states,
    ];
    $element['prompt_editor'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
      '#states' => $ai_states,
    ];
    $element['prompt_editor']['prompt_editor_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prompt editor'),
      '#default_value' => $items[$delta]->prompt_editor_name ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('First Middle Lastname'),
    ];
    $element['prompt_editor']['prompt_editor_link'] = [
      '#type' => 'url',
      '#title' => $this->t('Link'),
      '#default_value' => $items[$delta]->prompt_editor_link ?? NULL,
      '#size' => 20,
      '#placeholder' => $this->t('https://example.org/editor'),
    ];

    return $element;
  }

  /**
   * Computes a #states ':input[name="..."]' selector for a sibling property.
   *
   * Relies on $element['#field_parents'] being present, which WidgetBase
   * guarantees is set before formElement() runs.
   */
  protected function getFieldElementSelector(array $element, int $delta, string $property): string {
    $parents = array_merge(
      $element['#field_parents'],
      [$this->fieldDefinition->getName(), $delta, $property]
    );
    $name = array_shift($parents);
    if ($parents) {
      $name .= '[' . implode('][', $parents) . ']';
    }
    return ':input[name="' . $name . '"]';
  }

  /**
   * Adds the shared widget classes and library to $element.
   */
  protected function attachElementDefaults(array $element): array {
    $element['#attributes']['class'][] = 'attribution-elements';
    $element['#attached']['library'][] = 'attribution/attribution';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state): array|false {
    $element = parent::errorElement($element, $violation, $form, $form_state);
    if ($element === FALSE) {
      return FALSE;
    }
    $property_path = explode('.', $violation->getPropertyPath());
    if (!isset($property_path[1])) {
      return $element;
    }
    $property = $property_path[1];
    $parents = $this->getAllNestedProperties()[$property] ?? [$property];
    $exists = FALSE;
    $error_element = NestedArray::getValue($element, $parents, $exists);
    return $exists ? $error_element : $element;
  }

  /**
   * Nested widget properties to flatten during form value massaging.
   *
   * Keyed by the flat field-item property name, valued by the ['container',
   * 'property'] parents within the submitted widget value. Only covers the
   * 'source'/'author' containers built by concrete widgets, which differ per
   * widget variant; the 'prompt_editor' container built by
   * buildCreationTypeElements() is always merged in on top of this in
   * massageFormValues() since every widget has it. Properties that are never
   * nested in a container (license, creation_type, ai_tool, ai_prompt) are
   * handled separately.
   *
   * @return array<string, array{0: string, 1: string}>
   *   The nested property parents, keyed by flat property name.
   */
  protected function getNestedProperties(): array {
    return [];
  }

  /**
   * Nested widget properties including the shared 'prompt_editor' container.
   *
   * @return array<string, array{0: string, 1: string}>
   *   The nested property parents, keyed by flat property name.
   */
  private function getAllNestedProperties(): array {
    return $this->getNestedProperties() + [
      'prompt_editor_name' => ['prompt_editor', 'prompt_editor_name'],
      'prompt_editor_link' => ['prompt_editor', 'prompt_editor_link'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $nested_properties = $this->getAllNestedProperties();
    foreach ($values as $delta => $value) {
      foreach ($nested_properties as $property => $parents) {
        [$container, $key] = $parents;
        if ($value[$container][$key] === '') {
          $values[$delta][$container][$key] = NULL;
        }
        else {
          $values[$delta][$property] = $value[$container][$key];
        }
      }

      foreach (['license', 'creation_type', 'ai_tool', 'ai_prompt'] as $property) {
        if ($value[$property] === '') {
          $values[$delta][$property] = NULL;
        }
      }

      // #states only hides fields client-side; explicitly clear whatever a
      // user typed before switching creation_type back so stale hidden
      // values are never persisted.
      $creation_type = $values[$delta]['creation_type'];
      $ai_creation_types = [
        AttributionItem::CREATION_TYPE_AI_GENERATED,
        AttributionItem::CREATION_TYPE_AI_MODIFIED,
      ];
      if (!in_array($creation_type, $ai_creation_types, TRUE)) {
        $values[$delta]['ai_tool'] = NULL;
        $values[$delta]['ai_prompt'] = NULL;
        $values[$delta]['prompt_editor_name'] = NULL;
        $values[$delta]['prompt_editor_link'] = NULL;
      }
    }
    return $values;
  }

}
