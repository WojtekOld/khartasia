<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;
use Drupal\security_review\CheckResult;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Contains tests for the Headers plugin.
 *
 * @group security_review
 */
class HeadersTest extends SecurityReviewTestBase {

  /**
   * Tests plugin success.
   *
   * @throws \Exception
   *
   * @dataProvider headerCaseProvider
   */
  public function testHeadersSuccess(array $headers): void {
    $mockResponse = $this->createMock(ResponseInterface::class);
    $mockResponse->method('getHeaders')->willReturn($headers);

    $mockHttpClient = $this->createMock(Client::class);
    $mockHttpClient->method('request')->willReturn($mockResponse);

    $this->container->set('http_client', $mockHttpClient);

    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('headers');

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertEmpty($results['findings']);
  }

  /**
   * Provider for different header cases.
   */
  public static function headerCaseProvider(): array {
    return [
      'lowercase' => [
        ['x-frame-options' => ['SAMEORIGIN']],
      ],
      'uppercase' => [
        ['X-Frame-Options' => ['SAMEORIGIN']],
      ],
      'mixed' => [
        ['X-FRAME-OPTIONS' => ['SAMEORIGIN']],
      ],
    ];
  }

  /**
   * Test plugin fail.
   *
   * @throws \Exception
   */
  public function testHeadersFail(): void {
    // Do not set x-frame-options.
    $mockResponse = $this->createMock(ResponseInterface::class);
    $mockResponse->method('getHeaders')->willReturn([]);
    $mockHttpClient = $this->createMock(Client::class);
    $mockHttpClient->method('request')->willReturn($mockResponse);
    $this->container->set('http_client', $mockHttpClient);
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('headers');

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertContains('X-Frame-Options', $results['findings']);
  }

  /**
   * Test plugin works looking for a custom header.
   *
   * @throws \Exception
   */
  public function testHeadersCustomHeaderConfig(): void {
    // Add a custom header to the config.
    $this->config('security_review.settings')
      ->set('headers.headers_to_check', ['My-Great-Header'])
      ->save();

    $mockResponse = $this->createMock(ResponseInterface::class);
    $mockResponse->method('getHeaders')->willReturn([
      'x-frame-options' => ['SAMEORIGIN'],
      'my-great-header' => ['My-Great-Value'],
    ]);
    $mockHttpClient = $this->createMock(Client::class);
    $mockHttpClient->method('request')->willReturn($mockResponse);
    $this->container->set('http_client', $mockHttpClient);
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('headers');

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::SUCCESS, $results['result']);
    $this->assertNotContains('X-Frame-Options', $results['findings']);
    $this->assertNotContains('My-Great-Header', $results['findings']);

    $mockResponse = $this->createMock(ResponseInterface::class);
    $mockResponse->method('getHeaders')->willReturn([
      'x-frame-options' => ['SAMEORIGIN'],
    ]);
    $mockHttpClient = $this->createMock(Client::class);
    $mockHttpClient->method('request')->willReturn($mockResponse);
    $this->container->set('http_client', $mockHttpClient);
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('headers');

    $this->plugin->doRun();
    $results = $this->plugin->getResult();

    $this->assertIsArray($results);
    $this->assertEquals(CheckResult::FAIL, $results['result']);
    $this->assertNotContains('X-Frame-Options', $results['findings']);
    $this->assertContains('My-Great-Header', $results['findings']);
  }

}
