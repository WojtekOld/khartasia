<?php

namespace Drupal\Tests\bibcite_bibtex\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\bibcite_bibtex\Encoder\BibtexEncoder;
use Drupal\Tests\bibcite_import\Kernel\FormatDecoderTestBase;

/**
 * @coversDefaultClass \Drupal\bibcite_bibtex\Encoder\BibtexEncoder
 */
#[Group('bibcite')]
#[RunTestsInSeparateProcesses]
class BibtexCaseDecodeTest extends FormatDecoderTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'serialization',
    'bibcite',
    'bibcite_entity',
    'bibcite_bibtex',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'system',
      'user',
      'serialization',
      'bibcite',
      'bibcite_entity',
      'bibcite_bibtex',
    ]);

    $this->encoder = new BibtexEncoder();
    $this->format = 'bibtex';
    $this->resultDir = __DIR__ . '/../../data/decoded/case';
    $this->inputDir = __DIR__ . '/../../data/encoded/case';
  }

}
