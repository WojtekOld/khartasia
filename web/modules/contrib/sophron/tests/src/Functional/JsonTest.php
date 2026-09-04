<?php

declare(strict_types=1);

namespace Drupal\Tests\sophron\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Sophron JSON feeds.
 */
#[Group('sophron')]
#[RunTestsInSeparateProcesses]
class JsonTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sophron'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
      'access mime types json feed',
    ]));
  }

  /**
   * Tests sophron.mime_types.json.
   */
  public function testSophronMimeTypesJson(): void {
    $this->drupalGet('sophron/mime-types.json');
    $mimeTypes = Json::decode($this->getSession()->getPage()->getText());
    $this->assertEquals([
      'extensions' => ['mxu', 'm4u', 'm1u'],
      'aliases' => ['video/x-mpegurl'],
      'description' => 'Video playlist',
    ], $mimeTypes['video/vnd.mpegurl']);

    // Load the admin UI, and change arbitrarily a mapping.
    $this->drupalGet('admin/config/system/sophron');
    $edit = [
      'map_commands' => '- {method: addTypeExtensionMapping, arguments: [foo/bar, quxqux]}',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Check the cached JSON request was invalidated and re-rendered.
    $this->drupalGet('sophron/mime-types.json');
    $mimeTypes = Json::decode($this->getSession()->getPage()->getText());
    $this->assertEquals([
      'extensions' => ['quxqux'],
    ], $mimeTypes['foo/bar']);
  }

}
