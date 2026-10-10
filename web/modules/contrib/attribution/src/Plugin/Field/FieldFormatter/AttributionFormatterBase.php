<?php

declare(strict_types=1);

namespace Drupal\attribution\Plugin\Field\FieldFormatter;

use Drupal\attribution\AttributionLicenseInterface;
use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;

/**
 * Base class for attribution field formatters.
 */
abstract class AttributionFormatterBase extends FormatterBase {

  /**
   * Builds the CSS class list for a license.
   *
   * @param \Drupal\attribution\AttributionLicenseInterface $license
   *   The license to build classes for.
   * @param string[] $extra_classes
   *   Additional classes to insert after the base 'attribution' class.
   *
   * @return string[]
   *   The CSS classes.
   */
  protected function buildLicenseClasses(AttributionLicenseInterface $license, array $extra_classes = []): array {
    return array_merge(
      ['attribution'],
      $extra_classes,
      [
        'attribution--license-' . $license->getId(),
        'attribution--license-' . ($license->isOsiCertified() ? 'is-osi-approved' : 'not-osi-approved'),
        'attribution--license-' . ($license->isDeprecated() ? 'is-deprecated' : 'not-deprecated'),
      ]
    );
  }

  /**
   * Builds the #source render values.
   *
   * @return array{name: ?string, link: ?string}|null
   *   The source values, or NULL if both are empty.
   */
  protected function buildSourceValues(array $values): ?array {
    if (($values['source_name'] ?? NULL) || ($values['source_link'] ?? NULL)) {
      return [
        'name' => $values['source_name'] ?? NULL,
        'link' => $values['source_link'] ?? NULL,
      ];
    }
    return NULL;
  }

  /**
   * Builds the #author render values.
   *
   * @return array{name: ?string, link: ?string}|null
   *   The author values, or NULL if both are empty.
   */
  protected function buildAuthorValues(array $values): ?array {
    if (($values['author_name'] ?? NULL) || ($values['author_link'] ?? NULL)) {
      return [
        'name' => $values['author_name'] ?? NULL,
        'link' => $values['author_link'] ?? NULL,
      ];
    }
    return NULL;
  }

  /**
   * Builds the #creation render values.
   *
   * @return array{type: string, type_label: ?\Drupal\Core\StringTranslation\TranslatableMarkup, tool: ?string, prompt: ?string, editor: ?array{name: ?string, link: ?string}}|null
   *   The creation values, or NULL if no creation type is set.
   */
  protected function buildCreationValues(array $values): ?array {
    if (empty($values['creation_type'])) {
      return NULL;
    }
    return [
      'type' => $values['creation_type'],
      'type_label' => AttributionItem::creationTypeOptions()[$values['creation_type']] ?? NULL,
      'tool' => $values['ai_tool'] ?? NULL,
      'prompt' => $values['ai_prompt'] ?? NULL,
      'editor' => $this->buildPromptEditorValues($values),
    ];
  }

  /**
   * Builds the #creation.editor render values.
   *
   * @return array{name: ?string, link: ?string}|null
   *   The prompt editor values, or NULL if both are empty.
   */
  protected function buildPromptEditorValues(array $values): ?array {
    if (($values['prompt_editor_name'] ?? NULL) || ($values['prompt_editor_link'] ?? NULL)) {
      return [
        'name' => $values['prompt_editor_name'] ?? NULL,
        'link' => $values['prompt_editor_link'] ?? NULL,
      ];
    }
    return NULL;
  }

  /**
   * Renders a name/link pair as safe markup.
   *
   * Either value may be present alone; when a link is present it wins as
   * the wrapping element, falling back to the link itself as the visible
   * text when no name is given.
   *
   * @param string|null $name
   *   The name, if any.
   * @param string|null $link
   *   The link, if any.
   * @param string $class
   *   The CSS class for the wrapping element.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string|null
   *   The rendered markup, or NULL if both are empty.
   */
  protected function renderNameLink(?string $name, ?string $link, string $class): MarkupInterface|string|NULL {
    if (!$name && !$link) {
      return NULL;
    }
    $text = Html::escape($name ?: $link);
    if ($link) {
      return Markup::create('<a class="' . Html::escape($class) . '" href="' . Html::escape($link) . '">' . $text . '</a>');
    }
    return Markup::create('<span class="' . Html::escape($class) . '">' . $text . '</span>');
  }

}
