<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\ViewExecutable;

/**
 * The pager of a view display.
 */
#[Source(
  id: 'view_pager',
  label: new TranslatableMarkup('[View] Pager'),
  description: new TranslatableMarkup('The pager of the view display.'),
  prop_types: ['slot'],
  context_requirements: ['views:display'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ]
)]
class ViewPagerSource extends ViewsDisplaySourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    if (!$view->getDisplay()->renderPager()) {
      return [];
    }
    return $view->renderPager($view->getExposedInput());
  }

}
