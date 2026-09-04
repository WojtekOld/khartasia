<?php

declare(strict_types=1);

namespace Drupal\ui_icons_text\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for ui_icons_text.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
class UiIconsTextHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_filter_format_edit_form_alter')]
  public function formFilterFormatEditFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Add an additional validate callback so we can ensure the order of filters
    // is correct.
    $form['#validate'][] = self::class . ':filterFormatEditFormValidate';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_filter_format_add_form_alter')]
  public function formFilterFormatAddFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Add an additional validate callback so we can ensure the order of filters
    // is correct.
    $form['#validate'][] = self::class . ':filterFormatEditFormValidate';
  }

  /**
   * Validate callback to ensure filter order and allowed_html are compatible.
   *
   * This is a copy from media.module.
   */
  public function filterFormatEditFormValidate(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();

    if (!isset($triggering_element['#name']) || 'op' !== $triggering_element['#name']) {
      return;
    }

    $allowed_html_path = [
      'filters',
      'filter_html',
      'settings',
      'allowed_html',
    ];

    $filter_html_settings_path = [
      'filters',
      'filter_html',
      'settings',
    ];

    $filter_html_enabled = $form_state->getValue([
      'filters',
      'filter_html',
      'status',
    ]);

    $icon_embed_enabled = $form_state->getValue([
      'filters',
      'icon_embed',
      'status',
    ]);

    if (!$icon_embed_enabled) {
      return;
    }

    $get_filter_label = function ($filter_plugin_id) use ($form) {
      return (string) $form['filters']['order'][$filter_plugin_id]['filter']['#markup'];
    };

    if (!$filter_html_enabled || !$form_state->getValue($allowed_html_path)) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\filter\Entity\FilterFormat $filter_format */
    $filter_format = $form_object->getEntity();

    $filter_html = clone $filter_format->filters()->get('filter_html');
    $filter_html->setConfiguration(['settings' => $form_state->getValue($filter_html_settings_path)]);
    $restrictions = $filter_html->getHTMLRestrictions();

    if (FALSE === $restrictions) {
      return;
    }

    $allowed = $restrictions['allowed'];

    // Require `<drupal-icon>` HTML tag if filter_html is enabled.
    if (!isset($allowed['drupal-icon'])) {
      $form_state->setError($form['filters']['settings']['filter_html']['allowed_html'], $this->t('The %icon-embed-filter-label filter requires <code>&lt;drupal-icon data-icon-id data-icon-settings class aria-label aria-hidden role&gt;</code> among the allowed HTML tags.', [
        '%icon-embed-filter-label' => $get_filter_label('icon_embed'),
      ]));

      return;
    }

    $this->validateAllowedAttributes($form, $form_state, $allowed['drupal-icon']);
    $this->validateFilterOrder($form_state, $get_filter_label);
  }

  /**
   * Errors when `<drupal-icon>` is allowed without the attributes it needs.
   *
   * @param array $form
   *   The filter format form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array|false $allowed_attributes
   *   Allowed attributes for `<drupal-icon>`, FALSE when it allows none.
   */
  private function validateAllowedAttributes(array &$form, FormStateInterface $form_state, array|bool $allowed_attributes): void {
    $required_attributes = [
      'data-icon-id',
      'data-icon-settings',
      'class',
      'aria-label',
      'aria-hidden',
      'role',
    ];

    // If there are no attributes, the allowed item is set to FALSE,
    // otherwise, it is set to an array.
    if ($allowed_attributes === FALSE) {
      $missing_attributes = $required_attributes;
    }
    elseif (isset($allowed_attributes['*'])) {
      $missing_attributes = [];
    }
    else {
      $missing_attributes = array_diff($required_attributes, array_keys($allowed_attributes));
    }

    if (!$missing_attributes) {
      return;
    }

    $form_state->setError($form['filters']['settings']['filter_html']['allowed_html'], $this->t('The <code>&lt;drupal-icon&gt;</code> tag in the allowed HTML tags is missing the following attributes: <code>%list</code>.', [
      '%list' => implode(' ', $missing_attributes),
    ]));
  }

  /**
   * Errors when the icon filter runs too early or is cancelled out.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param callable $get_filter_label
   *   Returns the human readable label of a filter plugin id.
   */
  private function validateFilterOrder(FormStateInterface $form_state, callable $get_filter_label): void {
    $filters = $form_state->getValue('filters');

    // The icon filter must be after "filter_html", "filter_autop" and is
    // canceled by "filter_html_escape".
    $error_filters = [];
    foreach (['filter_html', 'filter_autop'] as $filter_name) {
      // A filter that should run before icon embed filter.
      $precedent = $filters[$filter_name];

      if (empty($precedent['status']) || !isset($precedent['weight'])) {
        continue;
      }

      if ($precedent['weight'] >= $filters['icon_embed']['weight']) {
        $error_filters[$filter_name] = $get_filter_label($filter_name);
      }
    }

    if (!empty($error_filters)) {
      $form_state->setErrorByName('filters', $this->formatPlural(
        count($error_filters),
        'The %icon-embed-filter-label filter needs to be placed after the %filter filter.',
        'The %icon-embed-filter-label filter needs to be placed after the following filters: %filters.',
        [
          '%icon-embed-filter-label' => $get_filter_label('icon_embed'),
          '%filter' => reset($error_filters),
          '%filters' => implode(', ', $error_filters),
        ]
      ));
    }

    if (isset($filters['filter_html_escape']['status']) && $filters['filter_html_escape']['status']) {
      $form_state->setErrorByName('filters', $this->t('The Embed icon will not work and should be removed if the %filter is enabled', [
        '%filter' => $get_filter_label('filter_html_escape'),
      ]));
    }
  }

}
