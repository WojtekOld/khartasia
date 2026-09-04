<?php

declare(strict_types=1);

namespace Drupal\Tests\sophron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ensuring MimeTypeMapLoadedEvent has effect on the Sophron map.
 *
 * Installing the 'file_test' module allows DummyMimeTypeMapLoadedSubscriber
 * to execute and add some mappings. We check here that they are.
 */
#[Group('sophron')]
#[RunTestsInSeparateProcesses]
class MimeTypeMapLoadedEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sophron',
    'sophron_guesser',
    'sophron_guesser_test',
    'system',
    'file_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['sophron', 'system']);
  }

  /**
   * Tests mapping of mimetypes from filenames.
   */
  public function testMimeTypeMapLoadedEvent(): void {
    /** @var \Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser $extension_guesser */
    $extension_guesser = \Drupal::service('file.mime_type.guesser.extension');

    $test_case = [
      'test.alnitak' => 'constellation/orion-belt',
      'test.jar' => 'application/java-archive',
      'test.jpeg' => 'image/jpeg',
      'test.jpg' => 'image/jpeg',
      'test.jar.jpg' => 'image/jpeg',
      'test.jpg.jar' => 'application/java-archive',
      'test.pcf.z' => 'application/x-font',
      'test.garply.waldo' => 'application/x-garply-waldo',
      'pcf.z' => 'application/x-compress',
      'jar' => NULL,
      'garply.waldo' => NULL,
      'some.junk' => NULL,
      'foo.file_test_1' => 'made_up/file_test_1',
      'foo.file_test_2' => 'made_up/file_test_2',
      'foo.doc' => 'made_up/doc',
      'test.ogg' => 'audio/ogg',
      'foobar.z' => 'application/x-compress',
      'foobar.tar' => 'application/x-tar',
      'foobar.tar.z' => 'application/x-tarz',
    ];

    foreach ($test_case as $input => $expected) {
      $this->assertSame($expected, $extension_guesser->guessMimeType($input), 'Failed for extension ' . $input);
    }
  }

}
