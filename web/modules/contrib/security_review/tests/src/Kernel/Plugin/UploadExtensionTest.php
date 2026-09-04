<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\security_review\CheckResult;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the UploadExtension plugin.
 *
 * @group security_review
 */
class UploadExtensionTest extends SecurityReviewTestBase {

  use ContentTypeCreationTrait;
  use FileFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'user',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['node', 'user', 'text', 'system']);

    $this->createContentType(['type' => 'article'], FALSE);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('upload_extensions');
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \ReflectionException
   */
  public function testUploadExtensionsSuccess(): void {
    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertEmpty($results['findings']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \ReflectionException
   */
  public function testUploadExtensionsFailure(): void {
    $this->createFileField('field_file', 'node', 'article', [], ['file_extensions' => 'swf exe']);

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
    $this->assertNotEmpty($results['findings']);
    $this->assertEquals('swf', $results['findings']['node.article.field_file'][0]);
    $this->assertEquals('exe', $results['findings']['node.article.field_file'][1]);
  }

  /**
   * Tests the plugin configuration.
   *
   * @throws \ReflectionException
   */
  public function testUploadExtensionsConfiguration(): void {
    $this->createFileField('field_file', 'node', 'article', [], ['file_extensions' => 'pdf']);

    $this->config('security_review.settings')
      ->set('upload_extensions.hush_upload_extensions', [
        'pdf',
      ])->save();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertEmpty($results['findings']);
  }

}
