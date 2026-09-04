<?php

namespace Drupal\Tests\tmgmt_deepl\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl\Hook\TmgmtDeeplHooks;

/**
 * Tests the procedural hooks in tmgmt_deepl.module and the service class.
 *
 * @covers ::tmgmt_deepl_file_download
 * @covers \Drupal\tmgmt_deepl\Hook\TmgmtDeeplHooks
 * @group tmgmt_deepl
 */
class TmgmtDeeplModuleHooksTest extends KernelTestBase {

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
   * The TmgmtDeeplHooks service under test.
   */
  protected TmgmtDeeplHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl.module';
    $this->hooks = $this->container->get(TmgmtDeeplHooks::class);
  }

  /**
   * Builds a job mock with the given translator plugin id.
   *
   * @param string|null $plugin_id
   *   The translator plugin id, or NULL for a job without a translator.
   * @param int $job_id
   *   The job id.
   *
   * @return \Drupal\tmgmt\JobInterface
   *   The mocked job.
   */
  protected function mockJob(?string $plugin_id, int $job_id = 42): JobInterface {
    $job = $this->createMock(JobInterface::class);
    $job->method('id')->willReturn($job_id);
    if ($plugin_id === NULL) {
      $job->method('hasTranslator')->willReturn(FALSE);
      return $job;
    }
    $job->method('hasTranslator')->willReturn(TRUE);
    $translator = $this->createMock(TranslatorInterface::class);
    $translator->method('getPluginId')->willReturn($plugin_id);
    $job->method('getTranslator')->willReturn($translator);
    return $job;
  }

  /**
   * Creates a file with tmgmt_deepl usage registered against job 42.
   *
   * @return \Drupal\file\Entity\File
   *   The created file with usage registered.
   */
  protected function seedDeeplFileUsage(): File {
    $file = File::create(['uri' => 'public://deepl-test.txt', 'status' => 1]);
    $file->save();
    $this->container->get('file.usage')->add($file, 'tmgmt_deepl', 'tmgmt_job', '42');
    return $file;
  }

  /**
   * Tests the hook returns early for a job without a translator.
   */
  public function testNoTranslatorIsIgnored(): void {
    $file = $this->seedDeeplFileUsage();
    $this->hooks->tmgmtJobDelete($this->mockJob(NULL));
    $this->assertArrayHasKey('tmgmt_deepl', $this->container->get('file.usage')->listUsage($file));
  }

  /**
   * Tests the hook returns early for a non-DeepL translator.
   */
  public function testNonDeeplTranslatorIsIgnored(): void {
    $file = $this->seedDeeplFileUsage();
    $this->hooks->tmgmtJobDelete($this->mockJob('dummy'));
    $this->assertArrayHasKey('tmgmt_deepl', $this->container->get('file.usage')->listUsage($file));
  }

  /**
   * Tests the hook removes file usage registered by tmgmt_deepl.
   */
  public function testDeeplJobDeletionRemovesFileUsage(): void {
    $file = $this->seedDeeplFileUsage();
    $file_usage = $this->container->get('file.usage');
    $this->assertArrayHasKey('tmgmt_deepl', $file_usage->listUsage($file));

    $this->hooks->tmgmtJobDelete($this->mockJob('deepl_api', 42));

    $this->assertArrayNotHasKey('tmgmt_deepl', $file_usage->listUsage($file));
  }

  /**
   * Tests hook_file_download delegates to the TmgmtDeeplHooks service.
   */
  public function testFileDownloadDelegatesToService(): void {
    $hooks = $this->createMock(TmgmtDeeplHooks::class);
    $hooks->method('fileDownload')->with('public://x.txt')->willReturn(['X-Test' => 1]);
    $this->container->set(TmgmtDeeplHooks::class, $hooks);

    $this->assertSame(['X-Test' => 1], tmgmt_deepl_file_download('public://x.txt'));
  }

}
