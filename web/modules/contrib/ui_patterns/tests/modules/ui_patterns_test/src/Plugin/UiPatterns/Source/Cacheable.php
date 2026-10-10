<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\SlotPropType;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Adds its own cache metadata while computing its value.
 */
#[Source(
  id: 'cacheable',
  label: new TranslatableMarkup('Cacheable (ui_patterns_test)'),
  description: new TranslatableMarkup('Adds a cache context, a cache tag and a max-age.'),
  prop_types: ['string', 'slot'],
)]
final class Cacheable extends SourcePluginBase {

  public const OUTPUT = 'cacheable value';

  public const CONTEXT = 'url.query_args:cacheable';

  public const TAG = 'ui_patterns_test:cacheable';

  public const MAX_AGE = 600;

  /**
   * Tag of the render array given to a string prop.
   */
  public const RENDER_ARRAY_TAG = 'ui_patterns_test:cacheable_render_array';

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $this->addCacheContexts([self::CONTEXT]);
    $this->addCacheTags([self::TAG]);
    $this->mergeCacheMaxAge(self::MAX_AGE);
    if ($this->propDefinition['ui_patterns']['type_definition'] instanceof SlotPropType) {
      return ['#markup' => self::OUTPUT];
    }
    if ($this->getSetting('render_array')) {
      // Flattened to a string by the prop type.
      return ['#markup' => self::OUTPUT, '#cache' => ['tags' => [self::RENDER_ARRAY_TAG]]];
    }
    return self::OUTPUT;
  }

}
