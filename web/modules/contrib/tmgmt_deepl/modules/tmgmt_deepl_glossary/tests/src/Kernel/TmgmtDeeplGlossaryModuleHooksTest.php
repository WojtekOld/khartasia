<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_deepl_glossary\Hook\TmgmtDeeplGlossaryHooks;

/**
 * Tests the procedural hooks in tmgmt_deepl_glossary.module.
 *
 * @covers ::tmgmt_deepl_glossary_preprocess_menu_local_action
 * @covers ::tmgmt_deepl_glossary_tmgmt_deepl_checkout_settings_form_alter
 * @covers ::tmgmt_deepl_glossary_tmgmt_deepl_has_checkout_settings_alter
 * @covers ::tmgmt_deepl_glossary_tmgmt_deepl_translate_options_alter
 * @covers ::tmgmt_deepl_glossary_entity_operation
 * @group tmgmt_deepl_glossary
 */
class TmgmtDeeplGlossaryModuleHooksTest extends DeeplGlossaryKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/tmgmt_deepl_glossary.module';
  }

  /**
   * Tests the sync link gets the button class applied.
   */
  public function testPreprocessAddsButtonClassToSyncLink(): void {
    $variables = [
      'link' => [
        '#url' => Url::fromRoute('tmgmt_deepl_glossary.sync_form'),
        '#options' => ['attributes' => []],
      ],
    ];
    tmgmt_deepl_glossary_preprocess_menu_local_action($variables);
    /**
     * @var array{
     *   link: array{
     *     '#options': array{
     *       attributes: array<string, mixed>,
     *     },
     *   },
     * } $variables
     */
    $this->assertSame(['button'], $variables['link']['#options']['attributes']['class']);
  }

  /**
   * Tests an unrelated local action link is left untouched.
   */
  public function testPreprocessIgnoresOtherLinks(): void {
    $variables = [
      'link' => [
        '#url' => Url::fromRoute('<front>'),
        '#options' => ['attributes' => []],
      ],
    ];
    tmgmt_deepl_glossary_preprocess_menu_local_action($variables);
    /**
     * @var array{
     *   link: array{
     *     '#options': array{
     *       attributes: array<string, mixed>,
     *     },
     *   },
     * } $variables
     */
    $this->assertArrayNotHasKey('class', $variables['link']['#options']['attributes']);
  }

  /**
   * Replaces the glossary hooks service with a mock and returns it.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Hook\TmgmtDeeplGlossaryHooks&\PHPUnit\Framework\MockObject\MockObject
   *   The mocked hooks service.
   */
  protected function mockHooksService(): TmgmtDeeplGlossaryHooks {
    $hooks = $this->createMock(TmgmtDeeplGlossaryHooks::class);
    $this->container->set(TmgmtDeeplGlossaryHooks::class, $hooks);
    return $hooks;
  }

  /**
   * Tests the checkout settings form alter delegates to the hooks service.
   */
  public function testCheckoutSettingsFormAlterDelegates(): void {
    $job = $this->createMock(JobInterface::class);
    $form = ['existing' => TRUE];
    $this->mockHooksService()
      ->expects($this->once())
      ->method('tmgmtDeeplCheckoutSettingsFormAlter')
      ->with($form, $job);
    tmgmt_deepl_glossary_tmgmt_deepl_checkout_settings_form_alter($form, $job);
  }

  /**
   * Tests the has-checkout-settings alter delegates to the hooks service.
   */
  public function testHasCheckoutSettingsAlterDelegates(): void {
    $job = $this->createMock(JobInterface::class);
    $has = FALSE;
    $this->mockHooksService()
      ->expects($this->once())
      ->method('tmgmtDeeplHasCheckoutSettingsAlter')
      ->with($has, $job);
    tmgmt_deepl_glossary_tmgmt_deepl_has_checkout_settings_alter($has, $job);
  }

  /**
   * Tests the translate options alter delegates to the hooks service.
   */
  public function testTranslateOptionsAlterDelegates(): void {
    $job = $this->createMock(Job::class);
    $options = ['source_lang' => 'EN'];
    $this->mockHooksService()
      ->expects($this->once())
      ->method('tmgmtDeeplTranslateOptionsAlter')
      ->with($job, $options);
    tmgmt_deepl_glossary_tmgmt_deepl_translate_options_alter($job, $options);
  }

  /**
   * Tests the entity operation hook delegates to and returns from the service.
   */
  public function testEntityOperationDelegates(): void {
    $entity = $this->createMock(EntityInterface::class);
    $operations = ['sync' => ['title' => 'Sync']];
    $this->mockHooksService()
      ->expects($this->once())
      ->method('entityOperation')
      ->with($entity)
      ->willReturn($operations);
    $this->assertSame($operations, tmgmt_deepl_glossary_entity_operation($entity));
  }

}
