<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit;

use Drupal\tmgmt_deepl_glossary\Controller\GlossaryDictionaryCsvController;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the GlossaryDictionaryCsvController class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Controller\GlossaryDictionaryCsvController
 * @group tmgmt_deepl_glossary
 */
class GlossaryDictionaryCsvControllerTest extends UnitTestCase {

  /**
   * Tests the generateCsvContent method.
   */
  public function testGenerateCsvContent(): void {
    // Create mock entity.
    $entity = $this->createMockEntityWithEntries([
      'hello' => 'hallo',
      'world' => 'welt',
      'test' => 'prüfen',
    ]);

    // Create controller with mocked dependencies.
    $controller = $this->createControllerWithMockedFileSystem();

    // Use reflection to call protected method.
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('generateCsvContent');
    $csv_content = $method->invoke($controller, $entity);
    assert(is_string($csv_content));

    // Verify CSV content - entries only (no header row in generateCsvContent).
    $lines = explode("\n", trim($csv_content));
    $this->assertCount(3, $lines);
    $this->assertEquals('hello,hallo', $lines[0]);
    $this->assertEquals('world,welt', $lines[1]);
    $this->assertEquals('test,prüfen', $lines[2]);
  }

  /**
   * Tests the generateCsvContent method with empty entries.
   */
  public function testGenerateCsvContentEmpty(): void {
    // Create mock entity with no entries.
    $entity = $this->createMockEntityWithEntries([]);

    // Create controller.
    $controller = $this->createControllerWithMockedFileSystem();

    // Use reflection to call protected method.
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('generateCsvContent');

    $csv_content = $method->invoke($controller, $entity);

    // Verify CSV content is empty when no entries.
    $this->assertEquals('', $csv_content);
  }

  /**
   * Data provider for testGenerateFileName.
   *
   * @return array
   *   Array of test data.
   */
  public static function dataProviderTestGenerateFileName(): array {
    return [
      ['EN', 'DE', '/^dictionary_EN_DE_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv/'],
      ['FR', 'IT', '/^dictionary_FR_IT_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv/'],
      ['EN-US', 'DE-DE', '/^dictionary_EN-US_DE-DE_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv/'],
    ];
  }

  /**
   * Tests the generateFileName method.
   *
   * @dataProvider dataProviderTestGenerateFileName
   */
  public function testGenerateFileName(string $source_lang, string $target_lang, string $expected_pattern): void {
    // Create mock entity.
    $entity = $this->createMockEntityWithLanguages($source_lang, $target_lang);

    // Create controller.
    $controller = $this->createControllerWithMockedFileSystem();

    // Use reflection to call protected method.
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('generateFileName');

    $filename = $method->invoke($controller, $entity);
    assert(is_string($filename));

    // Verify filename format.
    $this->assertMatchesRegularExpression($expected_pattern, $filename);
  }

  /**
   * Creates a mock entity with specified entries.
   *
   * @param array $entries
   *   The entries to mock.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked entity.
   */
  protected function createMockEntityWithEntries(array $entries): DeeplMultilingualGlossaryDictionaryInterface|MockObject {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->expects($this->once())
      ->method('getEntries')
      ->willReturn($entries);
    $entity->expects($this->any())
      ->method('getSourceLanguage')
      ->willReturn('EN');
    $entity->expects($this->any())
      ->method('getTargetLanguage')
      ->willReturn('DE');

    return $entity;
  }

  /**
   * Creates a mock entity with specified languages.
   *
   * @param string $source_lang
   *   The source language.
   * @param string $target_lang
   *   The target language.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked entity.
   */
  protected function createMockEntityWithLanguages(string $source_lang, string $target_lang): DeeplMultilingualGlossaryDictionaryInterface|MockObject {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->expects($this->any())
      ->method('getSourceLanguage')
      ->willReturn($source_lang);
    $entity->expects($this->any())
      ->method('getTargetLanguage')
      ->willReturn($target_lang);

    return $entity;
  }

  /**
   * Creates the controller with mocked file system.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Controller\GlossaryDictionaryCsvController
   *   The controller instance.
   */
  protected function createControllerWithMockedFileSystem(): GlossaryDictionaryCsvController {
    // The protected methods we're testing don't require the container.
    // Create controller instance directly.
    $controller = new GlossaryDictionaryCsvController();

    return $controller;
  }

}
