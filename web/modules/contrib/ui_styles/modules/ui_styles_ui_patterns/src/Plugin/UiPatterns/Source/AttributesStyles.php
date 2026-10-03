<?php

declare(strict_types=1);

namespace Drupal\ui_styles_ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\AttributesTrait;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_styles\StylePluginManagerInterface;
use Drupal\ui_styles\UiStylesUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'ui_styles_attributes',
  label: new TranslatableMarkup('Styles attributes'),
  description: new TranslatableMarkup('Handle CSS classes with UI Styles.'),
  prop_types: ['attributes']
)]
class AttributesStyles extends SourcePluginBase {

  use AttributesTrait;

  /**
   * The styles manager.
   */
  protected StylePluginManagerInterface $stylesManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stylesManager = $container->get('plugin.manager.ui_styles');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $styles = $this->getSetting('styles');
    if (!\is_array($styles)) {
      return [];
    }

    // Old config structure.
    // @todo to remove in UI Styles 2.
    if (isset($styles['_ui_styles_extra'])) {
      /** @var array<string, string> $selected */
      // @phpstan-ignore-next-line
      $selected = UiStylesUtility::extractSelectedStyles($styles);
    }
    else {
      /** @var array<string, string> $selected */
      $selected = $styles['selected'] ?? [];
    }

    $summary = [];
    foreach ($selected as $style_id => $option_id) {
      $definition = $this->stylesManager->getDefinition($style_id, FALSE);
      if ($definition === NULL) {
        continue;
      }
      /** @var array<string, \Drupal\Core\StringTranslation\TranslatableMarkup> $options */
      $options = $definition->getOptionsAsOptions();
      $option = (string) ($options[$option_id] ?? $option_id);
      $label = (string) $definition->getLabel();
      // A style with a single option names itself, and its option carries
      // that same name: "Clearfix: Clearfix" says it twice.
      $summary[] = ($label === $option) ? $label : $label . ': ' . $option;
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    // If config is not set yet and coming from default value.
    $value = $this->getSetting('value');
    if (\is_string($value) && !empty($value)) {
      return static::convertStringToAttributesMapping($value);
    }

    /** @var string $extra */
    $extra = $this->getSetting('extra') ?? '';
    $mapping = static::convertStringToAttributesMapping($extra);

    $styles = $this->getSetting('styles');
    if (!\is_array($styles)) {
      return $mapping;
    }

    // Old config structure.
    // @todo to remove in UI Styles 2.
    if (isset($styles['_ui_styles_extra'])) {
      /** @var array $selected */
      // @phpstan-ignore-next-line
      $selected = UiStylesUtility::extractSelectedStyles($styles);
      /** @var string $extra */
      $extra = $styles['_ui_styles_extra'];
    }
    else {
      /** @var array $selected */
      $selected = $styles['selected'] ?? [];
      /** @var string $extra */
      $extra = $styles['extra'] ?? '';
    }

    $extra = \explode(' ', $extra);
    $classes = \array_merge($selected, $extra);
    $classes = \array_unique(\array_filter($classes));
    if (empty($classes)) {
      return $mapping;
    }

    $mapping['class'] = \array_values($classes);
    return $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    // If config is not set yet and coming from default value.
    $value = $this->getSetting('value');
    if (\is_string($value) && !empty($value)) {
      $mapping = static::convertStringToAttributesMapping($value);
      $selected = [];
      $extraStyles = $mapping['class'] ?? '';
      unset($mapping['class']);
      $attributes = new Attribute($mapping);
      // Trim because Attribute::__toString() add a space on the left as it is
      // intended to be printed in HTML.
      $extra = \trim((string) $attributes);
    }
    else {
      $extra = $this->getSetting('extra') ?? '';
      $styles = $this->getSetting('styles');
      if (!\is_array($styles)) {
        $styles = [];
      }
      // Old config structure.
      // @todo to remove in UI Styles 2.
      if (isset($styles['_ui_styles_extra'])) {
        // @phpstan-ignore-next-line
        $selected = UiStylesUtility::extractSelectedStyles($styles);
        $extraStyles = $styles['_ui_styles_extra'];
      }
      else {
        $selected = $styles['selected'] ?? [];
        $extraStyles = $styles['extra'] ?? '';
      }
    }

    $form['styles'] = [
      '#type' => 'ui_styles_styles',
      '#default_value' => [
        'selected' => $selected,
        'extra' => $extraStyles,
      ],
      '#wrapper_type' => 'container',
      '#tree' => TRUE,
    ];

    $form['extra'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra HTML attributes'),
      '#description' => $this->t('HTML attributes with double-quoted values.'),
      '#default_value' => $extra,
      '#placeholder' => 'title="Lorem ipsum" id="my-id"',
    ];

    return $form;
  }

}
