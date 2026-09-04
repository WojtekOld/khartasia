<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Hook\TmgmtDeeplGlossaryHooks;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the TmgmtDeeplGlossaryHooks class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Hook\TmgmtDeeplGlossaryHooks
 * @group tmgmt_deepl_glossary
 */
class TmgmtDeeplGlossaryHooksTest extends UnitTestCase {

  /**
   * The glossary helper mock.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected DeeplMultilingualGlossaryHelperInterface&MockObject $glossaryHelper;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface&MockObject $entityTypeManager;

  /**
   * The glossary storage mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityStorageInterface&MockObject $glossaryStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->glossaryHelper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $this->glossaryStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')->with('deepl_ml_glossary')->willReturn($this->glossaryStorage);
  }

  /**
   * Tests the static ::create factory.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('tmgmt_deepl_glossary.ml.helper', $this->glossaryHelper);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $hooks = TmgmtDeeplGlossaryHooks::create($container);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(TmgmtDeeplGlossaryHooks::class, $hooks);
  }

  /**
   * Creates the hook class under test.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Hook\TmgmtDeeplGlossaryHooks
   *   The hooks object.
   */
  protected function createHooks(): TmgmtDeeplGlossaryHooks {
    $hooks = new TmgmtDeeplGlossaryHooks($this->glossaryHelper, $this->entityTypeManager);
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

  /**
   * Creates a job mock with translator and language methods.
   *
   * @return \Drupal\tmgmt\JobInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The job mock.
   */
  protected function createJobMock(): JobInterface&MockObject {
    $job = $this->createMock(JobInterface::class);
    $job->method('getTranslatorId')->willReturn('deepl_api');
    $job->method('getRemoteSourceLanguage')->willReturn('EN');
    $job->method('getRemoteTargetLanguage')->willReturn('DE');
    return $job;
  }

  /**
   * Tests ::tmgmtDeeplCheckoutSettingsFormAlter with multiple glossaries.
   */
  public function testCheckoutSettingsFormAlterMultipleGlossaries(): void {
    $this->glossaryHelper->method('getMatchingGlossaries')
      ->with('deepl_api', 'EN', 'DE')
      ->willReturn([1 => 'Glossary A', 2 => 'Glossary B']);
    $job = $this->createJobMock();
    $job->method('getSetting')->with('glossary_id')->willReturn('2');

    $form = [];
    $this->createHooks()->tmgmtDeeplCheckoutSettingsFormAlter($form, $job);

    $this->assertArrayHasKey('glossary_id', $form);
    /** @var array<string, mixed> $glossary_element */
    $glossary_element = $form['glossary_id'];
    $this->assertSame('select', $glossary_element['#type']);
    $this->assertTrue((bool) $glossary_element['#required']);
    $this->assertSame([1 => 'Glossary A', 2 => 'Glossary B'], $glossary_element['#options']);
    $this->assertSame('2', $glossary_element['#default_value']);
  }

  /**
   * Tests ::tmgmtDeeplCheckoutSettingsFormAlter with a single glossary.
   */
  public function testCheckoutSettingsFormAlterSingleGlossary(): void {
    $this->glossaryHelper->method('getMatchingGlossaries')->willReturn([1 => 'Glossary A']);
    $job = $this->createJobMock();

    $form = [];
    $this->createHooks()->tmgmtDeeplCheckoutSettingsFormAlter($form, $job);
    $this->assertSame([], $form);
  }

  /**
   * The data provider for testHasCheckoutSettingsAlter.
   *
   * @return array<string, array{array<int, string>, bool}>
   *   Array of test data.
   */
  public static function dataProviderHasCheckoutSettingsAlter(): array {
    return [
      'no glossaries' => [[], FALSE],
      'one glossary' => [[1 => 'Glossary A'], FALSE],
      'two glossaries' => [[1 => 'Glossary A', 2 => 'Glossary B'], TRUE],
    ];
  }

  /**
   * Tests ::tmgmtDeeplHasCheckoutSettingsAlter.
   *
   * @dataProvider dataProviderHasCheckoutSettingsAlter
   */
  public function testHasCheckoutSettingsAlter(array $glossaries, bool $expected): void {
    $this->glossaryHelper->method('getMatchingGlossaries')->willReturn($glossaries);
    $has_checkout_settings = FALSE;
    $this->createHooks()->tmgmtDeeplHasCheckoutSettingsAlter($has_checkout_settings, $this->createJobMock());
    $this->assertSame($expected, $has_checkout_settings);
  }

  /**
   * Tests ::tmgmtDeeplTranslateOptionsAlter with a selected glossary.
   */
  public function testTranslateOptionsAlterWithSelectedGlossary(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('deepl-uuid-5');
    $this->glossaryStorage->method('load')->with('5')->willReturn($glossary);

    $job = $this->createMock(Job::class);
    $job->method('getSetting')->with('glossary_id')->willReturn('5');

    $options = [];
    $this->createHooks()->tmgmtDeeplTranslateOptionsAlter($job, $options);
    $this->assertSame('deepl-uuid-5', $options['glossary']);
  }

  /**
   * Tests ::tmgmtDeeplTranslateOptionsAlter auto-selecting a glossary.
   */
  public function testTranslateOptionsAlterAutoSelect(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('deepl-uuid-7');
    $this->glossaryStorage->method('load')->with(7)->willReturn($glossary);
    $this->glossaryHelper->method('getMatchingGlossaries')->willReturn([7 => 'Glossary G']);

    $job = $this->createMock(Job::class);
    $job->method('getSetting')->with('glossary_id')->willReturn('');
    $job->method('getTranslatorId')->willReturn('deepl_api');
    $job->method('getRemoteSourceLanguage')->willReturn('EN');
    $job->method('getRemoteTargetLanguage')->willReturn('DE');

    $options = [];
    $this->createHooks()->tmgmtDeeplTranslateOptionsAlter($job, $options);
    $this->assertSame('deepl-uuid-7', $options['glossary']);
  }

  /**
   * Tests ::tmgmtDeeplTranslateOptionsAlter without any matching glossary.
   */
  public function testTranslateOptionsAlterNoMatch(): void {
    $this->glossaryHelper->method('getMatchingGlossaries')->willReturn([]);
    $job = $this->createMock(Job::class);
    $job->method('getSetting')->with('glossary_id')->willReturn('');
    $job->method('getTranslatorId')->willReturn('deepl_api');
    $job->method('getRemoteSourceLanguage')->willReturn('EN');
    $job->method('getRemoteTargetLanguage')->willReturn('DE');

    $options = [];
    $this->createHooks()->tmgmtDeeplTranslateOptionsAlter($job, $options);
    $this->assertSame([], $options);
  }

  /**
   * Tests ::entityOperation for a glossary dictionary entity.
   */
  public function testEntityOperationDictionary(): void {
    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary->method('id')->willReturn(3);

    $operations = $this->createHooks()->entityOperation($dictionary);
    $this->assertArrayHasKey('csv_download', $operations);
    /** @var array<string, mixed> $csv_operation */
    $csv_operation = $operations['csv_download'];
    $this->assertInstanceOf(Url::class, $csv_operation['url']);
    /** @var \Drupal\Core\Url $url */
    $url = $csv_operation['url'];
    $this->assertSame('tmgmt_deepl_glossary.csv_download', $url->getRouteName());
    $this->assertSame(50, $csv_operation['weight']);
  }

  /**
   * Tests ::entityOperation for an unrelated entity.
   */
  public function testEntityOperationOtherEntity(): void {
    $entity = $this->createMock(EntityInterface::class);
    $this->assertSame([], $this->createHooks()->entityOperation($entity));
  }

}
