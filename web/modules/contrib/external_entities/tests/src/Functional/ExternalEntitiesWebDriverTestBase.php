<?php

namespace Drupal\Tests\external_entities\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Has some additional helper methods to make test code more readable.
 */
abstract class ExternalEntitiesWebDriverTestBase extends WebDriverTestBase {

  use ExternalEntitiesUiHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'external_entities_test',
    'filter',
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
