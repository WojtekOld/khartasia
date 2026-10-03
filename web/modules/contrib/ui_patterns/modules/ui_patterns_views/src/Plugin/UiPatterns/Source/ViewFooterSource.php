<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\ViewExecutable;

/**
 * The footer area of a view display.
 */
#[Source(
  id: 'view_footer',
  label: new TranslatableMarkup('[View] Footer'),
  description: new TranslatableMarkup('The footer area of the view display.'),
  prop_types: ['slot'],
  context_requirements: ['views:display'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ]
)]
class ViewFooterSource extends ViewsDisplaySourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    return self::renderArea($view, 'footer', empty($view->result));
  }

}
