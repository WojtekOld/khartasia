<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\ViewExecutable;

/**
 * The more link of a view display.
 */
#[Source(
  id: 'view_more',
  label: new TranslatableMarkup('[View] More'),
  description: new TranslatableMarkup('The more link of the view display.'),
  prop_types: ['slot'],
  context_requirements: ['views:display'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ]
)]
class ViewMoreSource extends ViewsDisplaySourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    return $view->getDisplay()->renderMoreLink();
  }

}
