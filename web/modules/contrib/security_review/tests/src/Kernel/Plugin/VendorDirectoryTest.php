<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the VendorDirectory plugin.
 *
 * @group security_review
 */
class VendorDirectoryTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('vendor_directory');
  }

  /**
   * Tests the plugin succeeds correctly.
   */
  public function testVendorDirectorySuccess(): void {
    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertNotEmpty($results['findings']);
    $this->assertTrue($results['findings']['vendor_directory_location']);
  }

  /**
   * Tests the plugin fails correctly.
   */
  public function testVendorDirectoryFailure(): void {
    $vendor_directory = DRUPAL_ROOT . '/vendor';

    if (!is_dir($vendor_directory)) {
      mkdir($vendor_directory);
      rename($vendor_directory . '/../autoload.php', DRUPAL_ROOT . '/vendor/autoload.php');
    }

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertNotEmpty($results['findings']);
    $this->assertFalse($results['findings']['vendor_directory_location']);

    // Now put back.
    rename(DRUPAL_ROOT . '/vendor/autoload.php', $vendor_directory . '/../autoload.php');
  }

}
