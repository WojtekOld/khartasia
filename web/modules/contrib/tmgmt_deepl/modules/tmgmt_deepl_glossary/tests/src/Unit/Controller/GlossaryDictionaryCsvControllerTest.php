<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\Controller\GlossaryDictionaryCsvController;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;

/**
 * Tests the GlossaryDictionaryCsvController.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Controller\GlossaryDictionaryCsvController
 * @group tmgmt_deepl_glossary
 */
class GlossaryDictionaryCsvControllerTest extends UnitTestCase {

  /**
   * Creates the controller under test.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\Controller\GlossaryDictionaryCsvController_Test
   *   The controller instance.
   */
  protected function createController(): GlossaryDictionaryCsvController_Test {
    return new GlossaryDictionaryCsvController_Test();
  }

  /**
   * Tests ::generateCsvContent with multiple entries.
   */
  public function testGenerateCsvContent(): void {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->method('getEntries')->willReturn([
      'Hello' => 'Hallo',
      'Goodbye' => 'Tschüss',
      'Thank you' => 'Danke',
    ]);

    $csv = $this->createController()->generateCsvContentPublic($entity);
    $expected = "Hello,Hallo\nGoodbye,Tschüss\nThank you,Danke";
    $this->assertSame($expected, $csv);
  }

  /**
   * Tests ::generateCsvContent with a single entry.
   */
  public function testGenerateCsvContentSingleEntry(): void {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->method('getEntries')->willReturn([
      'Hello' => 'Hallo',
    ]);

    $csv = $this->createController()->generateCsvContentPublic($entity);
    $this->assertSame('Hello,Hallo', $csv);
  }

  /**
   * Tests ::generateCsvContent with empty entries.
   */
  public function testGenerateCsvContentEmpty(): void {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->method('getEntries')->willReturn([]);

    $csv = $this->createController()->generateCsvContentPublic($entity);
    $this->assertSame('', $csv);
  }

  /**
   * Tests ::generateFileName format.
   */
  public function testGenerateFileName(): void {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->method('getSourceLanguage')->willReturn('en');
    $entity->method('getTargetLanguage')->willReturn('de');

    $filename = $this->createController()->generateFileNamePublic($entity);
    $this->assertStringStartsWith('dictionary_en_de_', $filename);
    $this->assertStringEndsWith('.csv', $filename);
    $this->assertMatchesRegularExpression('/^dictionary_en_de_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/', $filename);
  }

  /**
   * Tests ::generateFileName with different language codes.
   */
  public function testGenerateFileNameDifferentLanguages(): void {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->method('getSourceLanguage')->willReturn('fr');
    $entity->method('getTargetLanguage')->willReturn('pt-PT');

    $filename = $this->createController()->generateFileNamePublic($entity);
    $this->assertStringStartsWith('dictionary_fr_pt-PT_', $filename);
    $this->assertStringEndsWith('.csv', $filename);
  }

  /**
   * Tests ::download throws when the temporary file cannot be created.
   */
  public function testDownloadTempFileFailure(): void {
    $entity = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $entity->method('getEntries')->willReturn(['Hello' => 'Hallo']);
    $entity->method('getSourceLanguage')->willReturn('en');
    $entity->method('getTargetLanguage')->willReturn('de');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('tempnam')->willReturn(FALSE);

    $controller = $this->createController();
    $controller->setFileSystem($file_system);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to create temporary file for CSV download.');
    $controller->download($entity);
  }

}

// @codingStandardsIgnoreStart

/**
 * Testable GlossaryDictionaryCsvController exposing protected members.
 */
class GlossaryDictionaryCsvController_Test extends GlossaryDictionaryCsvController {

  /**
   * Public wrapper for ::generateCsvContent.
   */
  public function generateCsvContentPublic(DeeplMultilingualGlossaryDictionaryInterface $entity): string {
    return $this->generateCsvContent($entity);
  }

  /**
   * Public wrapper for ::generateFileName.
   */
  public function generateFileNamePublic(DeeplMultilingualGlossaryDictionaryInterface $entity): string {
    return $this->generateFileName($entity);
  }

  /**
   * Sets the file system service.
   */
  public function setFileSystem(FileSystemInterface $file_system): void {
    $this->fileSystem = $file_system;
  }

}
// @codingStandardsIgnoreEnd
