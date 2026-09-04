<?php

namespace Drupal\Tests\tmgmt_deepl\Kernel;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\tmgmt_deepl\DeeplTranslatorApiInterface;

/**
 * Tests tmgmt_deepl_requirements() and the backing getUsageData() method.
 *
 * @covers ::tmgmt_deepl_requirements
 * @covers \Drupal\tmgmt_deepl\DeeplTranslatorApi
 * @group tmgmt_deepl
 */
class TmgmtDeeplRequirementsTest extends KernelTestBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'key',
    'options',
    'tmgmt',
    'tmgmt_deepl',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // hook_requirements relies on the REQUIREMENT_* constants from install.inc
    // and the procedural function definitions from the .module/.install files.
    require_once \Drupal::root() . '/core/includes/install.inc';
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl.install';
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl.module';
  }

  /**
   * Seeds a DeepL tmgmt translator config in the active storage.
   */
  protected function seedDeeplTranslator(string $id = 'api'): void {
    $this->container->get('config.storage')->write("tmgmt.translator.$id", [
      'name' => $id,
      'label' => 'DeepL API',
      'plugin' => 'deepl_api',
      'settings' => [],
    ]);
  }

  /**
   * Replaces the DeepL API service with a usage-data double.
   *
   * Builds a minimal requirements-format array with the given severity and
   * description so that tmgmt_deepl_requirements() sees the expected shape.
   *
   * @param int $severity
   *   REQUIREMENT_OK, REQUIREMENT_WARNING, or REQUIREMENT_ERROR.
   * @param string $description
   *   The description string for the first translator found.
   */
  protected function mockApiUsage(int $severity, string $description): void {
    $api = $this->createMock(DeeplTranslatorApiInterface::class);
    $api->method('getUsageData')->willReturn([
      'tmgmt_deepl_api' => [
        'title' => $this->t('TMGMT DeepL (:name)', [':name' => 'DeepL API']),
        'value' => $this->t('Usage information'),
        'description' => $this->t('@desc', ['@desc' => $description]),
        'severity' => $severity,
      ],
    ]);
    $this->container->set('tmgmt_deepl.api', $api);
  }

  /**
   * Tests that requirements only run during the runtime phase.
   */
  public function testInstallPhaseReturnsNothing(): void {
    $this->assertSame([], tmgmt_deepl_requirements('install'));
  }

  /**
   * Tests the "no providers configured" branch.
   */
  public function testRuntimeWithoutProviders(): void {
    /** @var array<string, array<string, mixed>> $requirements */
    $requirements = tmgmt_deepl_requirements('runtime');
    $this->assertArrayHasKey('tmgmt_deepl', $requirements);
    $this->assertSame(REQUIREMENT_WARNING, $requirements['tmgmt_deepl']['severity']);
  }

  /**
   * Tests usage data with a healthy quota maps to REQUIREMENT_OK.
   */
  public function testRuntimeUsageOk(): void {
    $this->seedDeeplTranslator();
    $this->mockApiUsage(REQUIREMENT_OK, '100 of 500000 characters');

    /** @var array<string, array<string, mixed>> $requirements */
    $requirements = tmgmt_deepl_requirements('runtime');
    $this->assertArrayHasKey('tmgmt_deepl_api', $requirements);
    $this->assertSame(REQUIREMENT_OK, $requirements['tmgmt_deepl_api']['severity']);
    $description = $requirements['tmgmt_deepl_api']['description'];
    $this->assertInstanceOf(\Stringable::class, $description);
    $this->assertSame('100 of 500000 characters', (string) $description);
  }

  /**
   * Tests a reached quota maps to REQUIREMENT_WARNING.
   */
  public function testRuntimeUsageLimitReached(): void {
    $this->seedDeeplTranslator();
    $this->mockApiUsage(REQUIREMENT_WARNING, '500000 of 500000 characters');

    /** @var array<string, array<string, mixed>> $requirements */
    $requirements = tmgmt_deepl_requirements('runtime');
    $this->assertSame(REQUIREMENT_WARNING, $requirements['tmgmt_deepl_api']['severity']);
  }

  /**
   * Tests a connectivity failure maps to REQUIREMENT_ERROR.
   */
  public function testRuntimeUsageError(): void {
    $this->seedDeeplTranslator();
    $this->mockApiUsage(REQUIREMENT_ERROR, 'Cannot retrieve usage information due to connectivity issues.');

    /** @var array<string, array<string, mixed>> $requirements */
    $requirements = tmgmt_deepl_requirements('runtime');
    $this->assertSame(REQUIREMENT_ERROR, $requirements['tmgmt_deepl_api']['severity']);
  }

}
