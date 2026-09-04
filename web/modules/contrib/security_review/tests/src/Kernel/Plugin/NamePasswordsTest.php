<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\user\Entity\User;

/**
 * Contains tests for the NamePasswords plugin.
 *
 * @group security_review
 */
class NamePasswordsTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('name_passwords');
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNamePasswordsPluginSuccess(): void {
    User::create(['name' => 'test', 'pass' => 'success'])->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);

    $this->assertArrayHasKey('findings', $sandbox);
    $this->assertEmpty($sandbox['findings']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNamePasswordsPluginFailure(): void {
    User::create(['name' => 'test', 'pass' => 'test'])->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);

    $this->assertArrayHasKey('findings', $sandbox);
    $this->assertNotEmpty($sandbox['findings']);
    $this->assertEquals('test', $sandbox['findings']['0']);
  }

}
