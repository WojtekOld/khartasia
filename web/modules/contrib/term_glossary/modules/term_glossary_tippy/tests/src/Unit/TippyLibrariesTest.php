<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary_tippy\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\term_glossary_tippy\TippyLibraries;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TippyLibraries external libraries checker.
 *
 * @group term_glossary
 */
#[CoversClass(TippyLibraries::class)]
#[Group('term_glossary')]
class TippyLibrariesTest extends UnitTestCase {

  /**
   * Tests that no file is reported when all the libraries are available.
   *
   * Builds a virtual web root containing the three required library
   * files (popper.js, tippy.umd.js and tippy.css) at their expected
   * locations and verifies that getMissingFiles() returns an empty
   * array, meaning the module considers the libraries as installed.
   */
  public function testAllFilesPresent(): void {
    vfsStream::setup('webroot', NULL, [
      'libraries' => [
        'popperjs' => [
          'dist' => [
            'umd' => [
              'popper.js' => '// popper.js',
            ],
          ],
        ],
        'tippyjs' => [
          'dist' => [
            'tippy.umd.js' => '// tippy.js',
            'tippy.css' => '/* tippy.css */',
          ],
        ],
      ],
    ]);
    $this->assertEquals([], TippyLibraries::getMissingFiles(vfsStream::url('webroot')));
  }

  /**
   * Tests that only the absent files are reported as missing.
   *
   * Builds a virtual web root containing only the popper.js library and
   * verifies that getMissingFiles() returns exactly the two tippy.js
   * files, in the declaration order of the required files list, without
   * reporting the popper.js file that is present.
   */
  public function testSomeFilesMissing(): void {
    vfsStream::setup('webroot', NULL, [
      'libraries' => [
        'popperjs' => [
          'dist' => [
            'umd' => [
              'popper.js' => '// popper.js',
            ],
          ],
        ],
      ],
    ]);
    $this->assertEquals([
      '/libraries/tippyjs/dist/tippy.umd.js',
      '/libraries/tippyjs/dist/tippy.css',
    ], TippyLibraries::getMissingFiles(vfsStream::url('webroot')));
  }

  /**
   * Tests that all the required files are reported on an empty web root.
   *
   * Builds a virtual web root with no libraries folder at all and
   * verifies that getMissingFiles() returns the complete required files
   * list, which is the situation right after enabling the module on a
   * site where the libraries were never downloaded.
   */
  public function testAllFilesMissing(): void {
    vfsStream::setup('webroot');
    $this->assertEquals([
      '/libraries/popperjs/dist/umd/popper.js',
      '/libraries/tippyjs/dist/tippy.umd.js',
      '/libraries/tippyjs/dist/tippy.css',
    ], TippyLibraries::getMissingFiles(vfsStream::url('webroot')));
  }

  /**
   * Tests the warning message built for a list of missing files.
   *
   * Builds the warning message for two missing files and verifies that
   * the rendered result contains the file paths and the two download
   * links (popper.js and tippy.js), so administrators always know what
   * to download and where the files are expected.
   */
  public function testBuildWarningMessage(): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $message = (string) TippyLibraries::buildWarningMessage([
      '/libraries/popperjs/dist/umd/popper.js',
      '/libraries/tippyjs/dist/tippy.css',
    ]);
    $this->assertStringContainsString('/libraries/popperjs/dist/umd/popper.js', $message);
    $this->assertStringContainsString('/libraries/tippyjs/dist/tippy.css', $message);
    $this->assertStringContainsString(TippyLibraries::POPPERJS_URL, $message);
    $this->assertStringContainsString(TippyLibraries::TIPPYJS_URL, $message);
  }

}
