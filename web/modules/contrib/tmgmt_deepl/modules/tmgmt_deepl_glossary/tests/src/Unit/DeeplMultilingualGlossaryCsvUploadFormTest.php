<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryCsvUploadForm;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the CSV upload form.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryCsvUploadForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryCsvUploadFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests validateLanguagePair with an invalid pair — both fields get errors.
   */
  public function testValidateLanguagePairInvalidPair(): void {
    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())
      ->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([['EN' => 'DE']]);

    $form = $this->createForm(glossary_helper: $glossary_helper);

    $error_fields = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->exactly(2))
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $form->validateLanguagePairPublic($form_state, 'EN', 'FR');

    $this->assertEquals(['source_lang', 'target_lang'], $error_fields);
  }

  /**
   * Tests validateNoExistingDictionary when a dictionary already exists.
   */
  public function testValidateNoExistingDictionaryFound(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('glossary-uuid-123');

    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())
      ->method('hasMultilingualGlossaryDictionary')
      ->willReturn(TRUE);

    $form = $this->createForm(glossary_helper: $glossary_helper);
    $form->setGlossaryForTest($glossary);

    $error_fields = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->exactly(2))
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $form->validateNoExistingDictionaryPublic($form_state, 'EN', 'DE');

    $this->assertEquals(['source_lang', 'target_lang'], $error_fields);
  }

  /**
   * Tests validateAndParseCsvFile when no file is uploaded.
   */
  public function testValidateAndParseCsvFileNoFile(): void {
    $form = $this->createForm();
    $form->setTestRequest(new Request());

    $error_fields = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $form->validateAndParseCsvFilePublic([], $form_state);

    $this->assertEquals(['csv_file'], $error_fields);
    $this->assertNull($form->getCsvEntries());
  }

  /**
   * Tests validateAndParseCsvFile when the upload failed.
   */
  public function testValidateAndParseCsvFileInvalidFile(): void {
    $form = $this->createForm();

    $tmp = tempnam(sys_get_temp_dir(), 'csv_test_');
    $request = new Request();
    $request->files->set('files', ['csv_file' => new UploadedFile($tmp, 'test.csv', 'text/csv', UPLOAD_ERR_NO_FILE, TRUE)]);
    $form->setTestRequest($request);

    $error_fields = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $form->validateAndParseCsvFilePublic([], $form_state);

    $this->assertEquals(['csv_file'], $error_fields);
    unlink($tmp);
  }

  /**
   * Tests validateAndParseCsvFile when the CSV has no valid entries.
   */
  public function testValidateAndParseCsvFileEmptyCsv(): void {
    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())->method('parseCsvContent')->willReturn([]);

    $form = $this->createForm(glossary_helper: $glossary_helper);

    $tmp = $this->createTempCsvFile('');
    $request = new Request();
    $request->files->set('files', ['csv_file' => new UploadedFile($tmp, 'test.csv', 'text/csv', UPLOAD_ERR_OK, TRUE)]);
    $form->setTestRequest($request);

    $error_fields = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $form->validateAndParseCsvFilePublic([], $form_state);

    $this->assertEquals(['csv_file'], $error_fields);
    $this->assertNull($form->getCsvEntries());
    unlink($tmp);
  }

  /**
   * Tests validateAndParseCsvFile when parseCsvContent throws an exception.
   */
  public function testValidateAndParseCsvFileParseException(): void {
    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())
      ->method('parseCsvContent')
      ->willThrowException(new \InvalidArgumentException('Line 2: Duplicate source text "hello".'));

    $form = $this->createForm(glossary_helper: $glossary_helper);

    $tmp = $this->createTempCsvFile("hello,world\nhello,welt");
    $request = new Request();
    $request->files->set('files', ['csv_file' => new UploadedFile($tmp, 'test.csv', 'text/csv', UPLOAD_ERR_OK, TRUE)]);
    $form->setTestRequest($request);

    $error_fields = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->willReturnCallback(function (string $field) use (&$error_fields) {
        $error_fields[] = $field;
      });

    $form->validateAndParseCsvFilePublic([], $form_state);

    $this->assertEquals(['csv_file'], $error_fields);
    $this->assertNull($form->getCsvEntries());
    unlink($tmp);
  }

  /**
   * Tests validateAndParseCsvFile with a valid CSV file.
   */
  public function testValidateAndParseCsvFileValid(): void {
    $expected_entries = ['hello' => 'hallo', 'world' => 'welt'];

    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())
      ->method('parseCsvContent')
      ->with("hello,hallo\nworld,welt")
      ->willReturn($expected_entries);
    $glossary_helper->expects($this->once())
      ->method('validateCsvEntries')
      ->with($expected_entries, $this->anything(), 'csv_file');

    $form = $this->createForm(glossary_helper: $glossary_helper);

    $tmp = $this->createTempCsvFile("hello,hallo\nworld,welt");
    $request = new Request();
    $request->files->set('files', ['csv_file' => new UploadedFile($tmp, 'test.csv', 'text/csv', UPLOAD_ERR_OK, TRUE)]);
    $form->setTestRequest($request);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->never())->method('setErrorByName');

    $form->validateAndParseCsvFilePublic([], $form_state);

    $this->assertEquals($expected_entries, $form->getCsvEntries());
    unlink($tmp);
  }

  /**
   * Tests submitForm on successful dictionary creation.
   */
  public function testSubmitFormCreateSuccess(): void {
    $entries = ['hello' => 'hallo', 'world' => 'welt'];
    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('id')->willReturn('1');

    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())
      ->method('createOrUpdateDictionaryFromEntries')
      ->with($glossary, 'EN', 'DE', $entries)
      ->willReturn($dictionary);

    $form = $this->createForm(glossary_helper: $glossary_helper);
    $form->setGlossaryForTest($glossary);
    $form->setCsvEntriesForTest($entries);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addMessage');
    $form->setMessenger($messenger);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnCallback(fn(string $key) => match ($key) {
        'source_lang' => 'EN',
        'target_lang' => 'DE',
        default => NULL,
      });
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('entity.deepl_ml_glossary.edit_form', ['deepl_ml_glossary' => '1']);

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

  /**
   * Tests submitForm when dictionary creation fails.
   */
  public function testSubmitFormCreateFailed(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('id')->willReturn('1');

    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->expects($this->once())
      ->method('createOrUpdateDictionaryFromEntries')
      ->willReturn(NULL);

    $form = $this->createForm(glossary_helper: $glossary_helper);
    $form->setGlossaryForTest($glossary);
    $form->setCsvEntriesForTest(['hello' => 'hallo']);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addError');
    $messenger->expects($this->never())->method('addMessage');
    $form->setMessenger($messenger);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnCallback(fn(string $key) => match ($key) {
        'source_lang' => 'EN',
        'target_lang' => 'DE',
        default => NULL,
      });
    $form_state->expects($this->never())->method('setRedirect');

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

  /**
   * Tests validateForm skips dictionary check when overwrite is allowed.
   */
  public function testValidateFormAllowOverwriteSkipsExistingDictionaryCheck(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('glossary-uuid-123');

    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([['EN' => 'DE']]);
    $glossary_helper->expects($this->never())->method('hasMultilingualGlossaryDictionary');

    $form = $this->createForm(glossary_helper: $glossary_helper);
    $form->setGlossaryForTest($glossary);
    $form->setTestRequest(new Request());

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnCallback(fn(string $key) => match ($key) {
        'source_lang' => 'EN',
        'target_lang' => 'DE',
        'allow_overwrite' => TRUE,
        default => NULL,
      });
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->atLeastOnce())->method('setErrorByName');

    $form_array = [];
    $form->validateFormPublic($form_array, $form_state);
  }

  /**
   * Tests validateForm enforces dictionary check when overwrite is disabled.
   */
  public function testValidateFormNoOverwriteChecksExistingDictionary(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('glossary-uuid-123');

    $glossary_helper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $glossary_helper->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([['EN' => 'DE']]);
    $glossary_helper->expects($this->once())
      ->method('hasMultilingualGlossaryDictionary')
      ->with('glossary-uuid-123', 'EN', 'DE')
      ->willReturn(FALSE);

    $form = $this->createForm(glossary_helper: $glossary_helper);
    $form->setGlossaryForTest($glossary);
    $form->setTestRequest(new Request());

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnCallback(fn(string $key) => match ($key) {
        'source_lang' => 'EN',
        'target_lang' => 'DE',
        'allow_overwrite' => FALSE,
        default => NULL,
      });
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->atLeastOnce())->method('setErrorByName');

    $form_array = [];
    $form->validateFormPublic($form_array, $form_state);
  }

  /**
   * Creates a form instance for testing.
   */
  protected function createForm(
    ?DeeplMultilingualGlossaryApiInterface $glossary_api = NULL,
    ?DeeplMultilingualGlossaryHelperInterface $glossary_helper = NULL,
  ): DeeplMultilingualGlossaryCsvUploadForm_Test {
    return new DeeplMultilingualGlossaryCsvUploadForm_Test(
      $this->createMock('Drupal\Core\Entity\EntityRepositoryInterface'),
      $this->createMock('Drupal\Core\Entity\EntityTypeBundleInfoInterface'),
      $glossary_api ?? $this->createMock(DeeplMultilingualGlossaryApiInterface::class),
      $glossary_helper ?? $this->createMock(DeeplMultilingualGlossaryHelperInterface::class),
      $this->createMock(AccountInterface::class),
    );
  }

  /**
   * Creates a temporary CSV file with the given content.
   */
  protected function createTempCsvFile(string $content): string {
    $path = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($path, $content);
    return $path;
  }

}

// @codingStandardsIgnoreStart

/**
 * Test subclass exposing protected members and overriding getRequest().
 */
class DeeplMultilingualGlossaryCsvUploadForm_Test extends DeeplMultilingualGlossaryCsvUploadForm {

  protected ?Request $testRequest = NULL;

  public function setTestRequest(Request $request): void {
    $this->testRequest = $request;
  }

  public function getRequest(): Request {
    return $this->testRequest ?? parent::getRequest();
  }

  public function validateLanguagePairPublic(FormStateInterface $form_state, string $source_lang, string $target_lang): void {
    $this->validateLanguagePair($form_state, $source_lang, $target_lang);
  }

  public function validateNoExistingDictionaryPublic(FormStateInterface $form_state, string $source_lang, string $target_lang): void {
    $this->validateNoExistingDictionary($form_state, $source_lang, $target_lang);
  }

  public function validateAndParseCsvFilePublic(array $form, FormStateInterface $form_state): void {
    $this->validateAndParseCsvFile($form, $form_state);
  }

  public function validateFormPublic(array &$form, FormStateInterface $form_state): void {
    $this->validateForm($form, $form_state);
  }

  public function setGlossaryForTest(?DeeplMultilingualGlossaryInterface $glossary): void {
    $this->glossary = $glossary;
  }

  public function getCsvEntries(): ?array {
    return $this->csvEntries;
  }

  public function setCsvEntriesForTest(?array $entries): void {
    $this->csvEntries = $entries;
  }

}

// @codingStandardsIgnoreEnd
