<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for Deepl Glossary kernel tests.
 */
abstract class DeeplGlossaryKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'key',
    'options',
    'tmgmt',
    'tmgmt_deepl',
    'tmgmt_deepl_glossary',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('deepl_ml_glossary');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['field', 'options']);

  }

}
