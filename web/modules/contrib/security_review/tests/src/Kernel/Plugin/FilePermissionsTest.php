<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\security_review\SecurityReviewData;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the FilePermissions plugin.
 *
 * @group security_review
 */
class FilePermissionsTest extends SecurityReviewTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('file_permissions');
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   */
  public function testFilePermissionsPluginSuccess(): void {
    $mockSettings = $this->createMock(SecurityReviewData::class);
    $mockSettings
      ->method('findWritableFiles')
      ->willReturn([]);
    $reflection = new \ReflectionProperty($this->plugin, 'securitySettings');
    $reflection->setValue($this->plugin, $mockSettings);

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      // Setting $cli to TRUE. Not sure possible to test or mock fopen().
      $finished = $this->plugin->run(TRUE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertArrayHasKey('findings', $results);
    $this->assertArrayHasKey('hushed', $results);
    $this->assertEmpty($results['findings']);
    $this->assertEmpty($results['hushed']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   */
  public function testFilePermissionsPluginFailure(): void {
    $mockSettings = $this->createMock(SecurityReviewData::class);
    $mockSettings
      ->method('findWritableFiles')
      ->willReturn([
        'core/lib/Drupal.php',
        'modules/system/system.module',
      ]);
    $reflection = new \ReflectionProperty($this->plugin, 'securitySettings');
    $reflection->setValue($this->plugin, $mockSettings);

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertArrayHasKey('findings', $results);
    $this->assertNotEmpty($results['findings']);
    $this->assertEquals('core/lib/Drupal.php', $results['findings'][0]);
    $this->assertEquals('modules/system/system.module', $results['findings'][1]);
    $this->assertArrayHasKey('hushed', $results);
    $this->assertEmpty($results['hushed']);
  }

  /**
   * Tests the hushed setting.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   */
  public function testFilePermissionsPluginHushedSetting(): void {
    $this->config('security_review.settings')
      ->set('file_permissions.hushed_files', [
        'core/lib/Drupal.php',
      ])
      ->save();

    $mockSettings = $this->createMock(SecurityReviewData::class);
    $mockSettings
      ->method('findWritableFiles')
      ->willReturn([
        'modules/system/system.module',
      ]);
    $reflection = new \ReflectionProperty($this->plugin, 'securitySettings');
    $reflection->setValue($this->plugin, $mockSettings);

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertArrayHasKey('findings', $results);
    $this->assertNotEmpty($results['findings']);
    $this->assertEquals('modules/system/system.module', $results['findings'][0]);
    $this->assertArrayHasKey('hushed', $results);
    $this->assertNotEmpty($results['hushed']);
    $this->stringContains('core/lib/Drupal.php', $results['hushed'][0]);
  }

}
