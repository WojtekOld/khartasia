<?php

namespace Drupal\Tests\bibcite_bibtex\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\bibcite_bibtex\Encoder\BibtexEncoder;
use Drupal\Tests\bibcite_export\Kernel\FormatEncoderTestBase;

/**
 * @coversDefaultClass \Drupal\bibcite_bibtex\Encoder\BibtexEncoder
 */
#[Group('bibcite')]
#[RunTestsInSeparateProcesses]
class BibtexEncodeTest extends FormatEncoderTestBase {

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
    $this->encodedExtension = 'bib';
    $this->resultDir = __DIR__ . '/../../data/encoded';
    $this->inputDir = __DIR__ . '/../../data/decoded';
  }

}
