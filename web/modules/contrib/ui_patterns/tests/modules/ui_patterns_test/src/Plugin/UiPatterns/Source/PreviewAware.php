<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\PreviewAwarePluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\SlotPropType;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Renders a different text in preview and in a real display.
 */
#[Source(
  id: 'preview_aware',
  label: new TranslatableMarkup('Preview aware (ui_patterns_test)'),
  description: new TranslatableMarkup('Tells whether it was built in preview.'),
  prop_types: ['string', 'slot'],
)]
final class PreviewAware extends SourcePluginBase implements PreviewAwarePluginInterface {

  public const PREVIEW_OUTPUT = 'preview render';

  public const REAL_OUTPUT = 'real render';

  /**
   * Whether the source is built for a preview.
   */
  protected bool $inPreview = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setInPreview(bool $in_preview): void {
    $this->inPreview = $in_preview;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $output = $this->inPreview ? self::PREVIEW_OUTPUT : self::REAL_OUTPUT;
    if ($this->propDefinition['ui_patterns']['type_definition'] instanceof SlotPropType) {
      return ['#markup' => $output];
    }
    return $output;
  }

}
