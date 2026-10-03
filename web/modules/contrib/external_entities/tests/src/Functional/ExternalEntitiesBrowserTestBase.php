<?php

namespace Drupal\Tests\external_entities\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Has some additional helper methods to make test code more readable.
 */
abstract class ExternalEntitiesBrowserTestBase extends BrowserTestBase {

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
