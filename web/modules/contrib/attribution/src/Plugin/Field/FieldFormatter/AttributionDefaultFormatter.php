<?php

declare(strict_types=1);

namespace Drupal\attribution\Plugin\Field\FieldFormatter;

use Drupal\attribution\AttributionLicenseInterface;
use Drupal\attribution\Entity\AttributionLicense;
use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the configurable 'Default' formatter.
 */
#[FieldFormatter(
  id: 'attribution_default',
  label: new TranslatableMarkup('Default'),
  field_types: ['attribution'],
)]
class AttributionDefaultFormatter extends AttributionFormatterBase {

  /**
   * Creative Commons icon file names.
   *
   * Keyed by version-stripped identifier slug (e.g. 'by-nc' for any
   * 'CC-BY-NC-<version>' identifier).
   */
  private const CC_ICONS = [
    'cc-zero' => 'cc-zero.svg',
    'by' => 'by.svg',
    'by-nc' => 'by-nc.svg',
    'by-nc-nd' => 'by-nc-nd.svg',
    'by-nc-sa' => 'by-nc-sa.svg',
    'by-nd' => 'by-nd.svg',
    'by-sa' => 'by-sa.svg',
  ];

  /**
   * AI creation type icon file names, keyed by creation type.
   */
  private const AI_ICONS = [
    AttributionItem::CREATION_TYPE_AI_GENERATED => 'ai_generated.svg',
    AttributionItem::CREATION_TYPE_AI_MODIFIED => 'ai_modified.svg',
  ];

  /**
   * Constructs an AttributionDefaultFormatter object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected ModuleExtensionList $moduleExtensionList,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'use_identifier' => FALSE,
      'cc_icon' => FALSE,
      'divider' => FALSE,
      'ai_icon' => FALSE,
      'show_prompt' => FALSE,
      'label_toggle' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['use_identifier'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use license identifier instead of name'),
      '#description' => $this->t('Display the short license identifier (e.g. CC-BY-4.0) instead of the full license name.'),
      '#default_value' => $this->getSetting('use_identifier'),
    ];
    $form['cc_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Creative Commons icon'),
      '#description' => $this->t('Display the corresponding Creative Commons badge instead of text, for licenses recognized as Creative Commons.'),
      '#default_value' => $this->getSetting('cc_icon'),
    ];
    $form['divider'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use divider markup instead of connector words'),
      '#description' => $this->t('Separate license, author, source, and creation information with visual dividers instead of connector words such as "by" or "as".'),
      '#default_value' => $this->getSetting('divider'),
    ];
    $form['ai_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show AI type icon'),
      '#description' => $this->t('Display an icon instead of a text label for AI-generated or AI-modified content.'),
      '#default_value' => $this->getSetting('ai_icon'),
    ];
    $form['show_prompt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show AI prompt'),
      '#description' => $this->t('Display the AI prompt text, if available, inside an expandable details element.'),
      '#default_value' => $this->getSetting('show_prompt'),
    ];
    $form['label_toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use field label as toggle'),
      '#description' => $this->t('Replace the field label with a clickable toggle (&lt;details&gt; markup with&lt;summary&gt; using the label text). This overrides the Label display.'),
      '#default_value' => $this->getSetting('label_toggle'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->getSetting('use_identifier')
      ? $this->t('License identifier shown instead of name')
      : $this->t('License name shown');
    if ($this->getSetting('cc_icon')) {
      $summary[] = $this->t('Creative Commons icon shown when applicable');
    }
    $summary[] = $this->getSetting('divider')
      ? $this->t('Divider markup instead of connector words')
      : $this->t('Connector words shown');
    if ($this->getSetting('ai_icon')) {
      $summary[] = $this->t('AI type icon shown when applicable');
    }
    if ($this->getSetting('show_prompt')) {
      $summary[] = $this->t('AI prompt shown when available');
    }
    if ($this->getSetting('label_toggle')) {
      $summary[] = $this->t('Field label used as expand/collapse toggle');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $settings = $this->getSettings();
    $divider = (bool) $settings['divider'];
    $label_toggle = (bool) $settings['label_toggle'];
    if ($label_toggle) {
      $label = $this->fieldDefinition->getLabel();
      $field_label = ($label !== NULL && (string) $label !== '') ? $label : $this->t('Attribution');
      // A sibling string key here wins in FormatterBase::view()'s
      // array_merge($info, $elements), overriding the site builder's Manage
      // Display "Label" setting for this field instance.
      $element['#label_display'] = 'hidden';
    }

    foreach ($items as $delta => $item) {
      $values = $item->getValue();
      $content = [
        '#theme' => 'attribution_default',
        '#divider' => $divider,
      ];

      $license_markup = NULL;
      if ($values['license'] ?? NULL) {
        /** @var \Drupal\attribution\Entity\AttributionLicense $license */
        $license = AttributionLicense::load($values['license']);
        $content['#attributes']['class'] = $this->buildLicenseClasses($license);
        $license_markup = $this->buildLicenseMarkup($license, $settings);
      }

      $author_values = $this->buildAuthorValues($values);
      $author_markup = $author_values
        ? $this->renderNameLink($author_values['name'], $author_values['link'], 'attribution--author')
        : NULL;

      $source_values = $this->buildSourceValues($values);
      $source_markup = $source_values
        ? $this->renderNameLink($source_values['name'], $source_values['link'], 'attribution--source')
        : NULL;

      $parts = [];
      if ($license_markup) {
        $parts['license'] = $divider ? $license_markup : $this->t('under @license', ['@license' => $license_markup]);
      }
      if ($author_markup) {
        $parts['author'] = $divider ? $author_markup : $this->t('by @author', ['@author' => $author_markup]);
      }
      if ($source_markup) {
        $parts['source'] = $divider ? $source_markup : $this->t('as published in @source', ['@source' => $source_markup]);
      }
      $content['#parts'] = $parts;
      if (!$divider && $parts) {
        $content['#lead'] = $this->t('Licensed');
      }

      if ($creation = $this->buildCreationValues($values)) {
        $content['#creation'] = $this->buildCreationParts($creation, $settings, $divider);
        $content['#attributes']['class'][] = 'attribution--creation-' . $creation['type'];
      }

      $element[$delta] = $label_toggle
        ? $this->buildLabelToggle($field_label, $content)
        : $content;
    }

    return $element;
  }

  /**
   * Wraps rendered content in a details element toggled by the field label.
   *
   * Reuses Drupal's own 'details' render element for consistency with the
   * AI prompt's expandable details element in buildCreationParts(): the
   * same 'js-form-wrapper form-wrapper' classes and the same
   * role="button"/aria-expanded attributes on the <summary> come for free.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $field_label
   *   The field label text.
   * @param array $content
   *   The render array to show/hide, as built by viewElements().
   *
   * @return array
   *   A 'details' render array.
   */
  protected function buildLabelToggle(string|TranslatableMarkup $field_label, array $content): array {
    // Carry the same license/creation classes the content div already has
    // (e.g. 'attribution--license-<id>', 'attribution--creation-<type>') so
    // themes can target the collapsed toggle the same way they target the
    // expanded markup.
    $classes = array_merge(['attribution--toggle'], $content['#attributes']['class'] ?? []);
    return [
      '#type' => 'details',
      '#title' => Markup::create('<span class="attribution--toggle-label">' . Html::escape((string) $field_label) . '</span>'),
      '#attributes' => ['class' => $classes],
      'content' => $content,
    ];
  }

  /**
   * Builds the ordered #creation sub-parts render values.
   *
   * @param array $creation
   *   The values from AttributionFormatterBase::buildCreationValues().
   * @param array $settings
   *   The formatter settings.
   * @param bool $divider
   *   Whether divider mode is active.
   *
   * @return array
   *   An ordered, associative array with any of the keys 'lead', 'type',
   *   'tool', 'editor', 'prompt' — only present when there is something to
   *   show. 'lead' is only present in word mode (never in divider mode).
   */
  protected function buildCreationParts(array $creation, array $settings, bool $divider): array {
    $parts = [];
    if (!$divider) {
      $parts['lead'] = $this->t('This work is');
    }

    $icon_url = $settings['ai_icon'] ? $this->resolveAiIconUrl($creation['type']) : NULL;
    $parts['type'] = $icon_url
      ? Markup::create('<img class="attribution--creation-icon" src="' . Html::escape($icon_url) . '" alt="' . Html::escape((string) $creation['type_label']) . '">')
      : $creation['type_label'];

    if ($creation['tool']) {
      $tool_markup = Markup::create('<em class="attribution--tool">' . Html::escape($creation['tool']) . '</em>');
      $parts['tool'] = $divider ? $tool_markup : $this->t('using @tool', ['@tool' => $tool_markup]);
    }

    $editor_markup = $creation['editor']
      ? $this->renderNameLink($creation['editor']['name'], $creation['editor']['link'], 'attribution--editor')
      : NULL;
    if ($editor_markup) {
      $parts['editor'] = $divider ? $editor_markup : $this->t('prompted by @editor', ['@editor' => $editor_markup]);
    }

    if ($settings['show_prompt'] && !empty($creation['prompt'])) {
      $parts['prompt'] = [
        '#type' => 'details',
        '#title' => $this->t('AI prompt'),
        '#attributes' => ['class' => ['attribution--creation-prompt']],
        'content' => ['#plain_text' => $creation['prompt']],
      ];
    }

    return $parts;
  }

  /**
   * Builds the rendered license markup.
   *
   * @param \Drupal\attribution\AttributionLicenseInterface $license
   *   The license.
   * @param array $settings
   *   The formatter settings.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered license markup.
   */
  protected function buildLicenseMarkup(AttributionLicenseInterface $license, array $settings): MarkupInterface|string {
    $use_identifier = (bool) $settings['use_identifier'];
    $label = $use_identifier ? $license->getIdentifier() : $license->getName();
    // When the identifier stands in for the name, keep the full name
    // available on hover / to assistive technology via a title attribute.
    $title = $use_identifier ? $license->getName() : NULL;

    $icon_url = ($settings['cc_icon'] && $this->isCreativeCommonsLicense($license))
      ? $this->resolveCcIconUrl($license)
      : NULL;
    $inner = $icon_url
      ? '<img class="attribution--license-icon" src="' . Html::escape($icon_url) . '" alt="' . Html::escape($label) . '">'
      : Html::escape($label);

    $title_attribute = $title ? ' title="' . Html::escape($title) . '"' : '';

    // Creative Commons licenses are assumed to always provide a link (per
    // the shipped spdx.org-sourced config entities); other licenses (e.g.
    // "All rights reserved") may not.
    $link = $license->getLink();
    if ($link) {
      return Markup::create('<a class="attribution--license" href="' . Html::escape($link) . '"' . $title_attribute . '>' . $inner . '</a>');
    }
    return Markup::create('<span class="attribution--license"' . $title_attribute . '>' . $inner . '</span>');
  }

  /**
   * Determines whether a license is recognized as Creative Commons.
   *
   * @param \Drupal\attribution\AttributionLicenseInterface $license
   *   The license.
   *
   * @return bool
   *   TRUE if the license's machine name starts with 'cc'.
   */
  protected function isCreativeCommonsLicense(AttributionLicenseInterface $license): bool {
    return str_starts_with($license->getId(), 'cc');
  }

  /**
   * Resolves the Creative Commons icon asset URL for a license.
   *
   * Derives the icon from the license's SPDX-style identifier rather than
   * its machine name, stripping the trailing version segment so future
   * version bumps of an already-supported license family (e.g.
   * CC-BY-4.1) resolve automatically without a code change.
   *
   * @param \Drupal\attribution\AttributionLicenseInterface $license
   *   The license.
   *
   * @return string|null
   *   The asset URL, or NULL if this license has no known Creative
   *   Commons icon.
   */
  protected function resolveCcIconUrl(AttributionLicenseInterface $license): ?string {
    $slug = (string) preg_replace('/-\d+(?:\.\d+)*$/', '', $license->getIdentifier());
    $slug = str_starts_with($slug, 'CC0') ? 'cc-zero' : strtolower((string) preg_replace('/^CC-/', '', $slug));
    if (!isset(self::CC_ICONS[$slug])) {
      return NULL;
    }
    return $this->assetUrl('creative-commons/' . self::CC_ICONS[$slug]);
  }

  /**
   * Resolves the AI icon asset URL for a creation type.
   *
   * @param string $creation_type
   *   The creation type (e.g. 'ai_generated').
   *
   * @return string|null
   *   The asset URL, or NULL if there is no icon for this creation type.
   */
  protected function resolveAiIconUrl(string $creation_type): ?string {
    if (!isset(self::AI_ICONS[$creation_type])) {
      return NULL;
    }
    return $this->assetUrl('ai/' . self::AI_ICONS[$creation_type]);
  }

  /**
   * Builds the URL for a module asset.
   *
   * @param string $relative_path
   *   The path relative to the module's assets/ directory.
   *
   * @return string
   *   The asset URL, relative to the site root.
   */
  protected function assetUrl(string $relative_path): string {
    return base_path() . $this->moduleExtensionList->getPath('attribution') . '/assets/' . $relative_path;
  }

}
