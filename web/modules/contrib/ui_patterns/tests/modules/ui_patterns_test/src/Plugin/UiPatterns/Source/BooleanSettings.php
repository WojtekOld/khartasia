<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * A source with two checkbox settings, one of them on by default.
 */
#[Source(
  id: 'boolean_settings',
  label: new TranslatableMarkup('Boolean settings (ui_patterns_test)'),
  description: new TranslatableMarkup('Two checkbox settings, one on by default.'),
  prop_types: ['string']
)]
final class BooleanSettings extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'on_by_default' => TRUE,
      'off_by_default' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['on_by_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('On by default'),
      '#default_value' => (bool) $this->getSetting('on_by_default'),
    ];
    $form['off_by_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Off by default'),
      '#default_value' => (bool) $this->getSetting('off_by_default'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    return 'on_by_default=' . (int) $this->getSetting('on_by_default') . ' off_by_default=' . (int) $this->getSetting('off_by_default');
  }

}
