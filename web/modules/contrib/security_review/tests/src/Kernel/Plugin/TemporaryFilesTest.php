<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the TemporaryFiles plugin.
 *
 * @group security_review
 */
class TemporaryFilesTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('temporary_files');
  }

  /**
   * Tests the plugin succeeds correctly.
   */
  public function testTemporaryFilesSuccess(): void {
    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertEmpty($results['findings']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \Exception
   */
  public function testTemporaryFilesFailure(): void {
    $site_path = $this->container->get('security_review.data')->sitePath();

    if (!is_dir($site_path)) {
      mkdir($site_path, 0777, TRUE);
    }

    $content = $this->randomString(2048);

    file_put_contents($site_path . '/foo.bak', $content);
    file_put_contents($site_path . '/backup.save', $this->randomString(2048));

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertNotEmpty($results['findings']);
    $this->assertStringContainsString('backup.save', $results['findings'][0]);
    $this->assertStringContainsString('foo.bak', $results['findings'][1]);
  }

}
