<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the PrivateFiles plugin.
 *
 * @group security_review
 */
class PrivateFilesTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('private_files');
  }

  /**
   * Test when the private path is not yet configured.
   */
  public function testPrivatePathNotConfigured(): void {
    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::INFO, $results['result']);
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \ReflectionException
   */
  public function testPrivateFilesSuccess(): void {
    $private_file_directory = DRUPAL_ROOT . '/../private_success_test';
    $this->setPrivatePath($private_file_directory);

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertNotEmpty($results['findings']);
    $this->assertEquals($private_file_directory, $results['findings']['path']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \ReflectionException
   */
  public function testPrivatePathFailure(): void {
    $private_file_directory = DRUPAL_ROOT . '/web/private_success_test';
    $this->setPrivatePath($private_file_directory);

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertNotEmpty($results['findings']);
    $this->assertEquals($private_file_directory, $results['findings']['path']);
  }

  /**
   * Helper function to set the private path.
   *
   * @param string $private_file_directory
   *   The path to set.
   *
   * @throws \ReflectionException
   * @throws \Exception
   */
  protected function setPrivatePath(string $private_file_directory): void {
    if (!is_dir($private_file_directory)) {
      $this->container->get('file_system')->mkdir($private_file_directory, NULL, TRUE);
    }

    // Mock PrivateStream.
    $mockWrapper = $this->createMock(PrivateStream::class);
    $mockWrapper->method('realPath')->willReturn($private_file_directory);

    // Mock StreamWrapperManagerInterface.
    $mockManager = $this->createMock(StreamWrapperManagerInterface::class);
    $mockManager->method('getViaScheme')->with('private')->willReturn($mockWrapper);

    $reflection = new \ReflectionProperty($this->plugin, 'streamWrapperManager');
    $reflection->setValue($this->plugin, $mockManager);
  }

}
