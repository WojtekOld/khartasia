<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\security_review\CheckResult;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Contains tests for the ExecutablePhp plugin.
 *
 * @group security_review
 */
class ExecutablePhpTest extends SecurityReviewTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('executable_php');
  }

  /**
   * Test for failure when php can be executed.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function testExecutablePhpFail(): void {
    $mockClient = $this->createMock(Client::class);
    $message = 'Security review test ' . date('Ymdhis');
    $mockClient
      ->method('request')
      ->willReturn(new Response(200, [], $message));
    $this->container->set('http_client', $mockClient);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('executable_php');

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertIsArray($result);
    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);
    $this->assertContains('executable_php', $result['findings']);
  }

  /**
   * Tests for success when php can't be executed.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function testExecutablePhpSuccess(): void {
    $mockClient = $this->createMock(Client::class);
    $message = 'Security review test ' . date('Ymdhis');
    $mockClient
      ->method('request')
      ->willReturn(new Response(403, [], $message));
    $this->container->set('http_client', $mockClient);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('executable_php');

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertSame(CheckResult::SUCCESS, $result['result']);
    $this->assertEmpty($result['findings']);
  }

  /**
   * Test .htaccess checks.
   */
  public function testHtaccessChecks(): void {
    // Force Apache to trigger htaccess check.
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4';

    $htaccess_path = PublicStream::basePath() . '/.htaccess';

    if (file_exists($htaccess_path)) {
      unlink($htaccess_path);
    }

    // Run the check.
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);
    $this->assertContains('missing_htaccess', $result['findings']);

    file_put_contents($htaccess_path, 'incorrect content');
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::FAIL, $result['result']);
    $this->assertNotEmpty($result['findings']);
    $this->assertContains('incorrect_htaccess', $result['findings']);

    $expected = FileSecurity::htaccessLines(FALSE);
    file_put_contents($htaccess_path, $expected);
    chmod($htaccess_path, 0666);
    $this->plugin->doRun();
    $result = $this->plugin->getResult();

    $this->assertEquals(CheckResult::WARN, $result['result']);
    $this->assertNotEmpty($result['findings']);
    $this->assertContains('writable_htaccess', $result['findings']);
  }

}
