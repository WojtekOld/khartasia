<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Form;

use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt\Entity\Translator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryCsvUploadForm;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryCsvUploadForm class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryCsvUploadForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryCsvUploadFormTest extends UnitTestCase {

  /**
   * The glossary API service mock.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected DeeplMultilingualGlossaryApiInterface&MockObject $glossaryApi;

  /**
   * The glossary helper mock.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected DeeplMultilingualGlossaryHelperInterface&MockObject $glossaryHelper;

  /**
   * The messenger mock.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected MessengerInterface&MockObject $messenger;

  /**
   * The form under test.
   *
   * @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryCsvUploadForm_Test
   */
  protected DeeplMultilingualGlossaryCsvUploadForm_Test $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());

    $this->messenger = $this->createMock(MessengerInterface::class);
    $container->set('messenger', $this->messenger);

    $route_match = $this->createMock(RouteMatchInterface::class);
    $container->set('current_route_match', $route_match);

    $request_stack = $this->createMock(RequestStack::class);
    $request = new Request();
    $request->files = new FileBag();
    $request_stack->method('getCurrentRequest')->willReturn($request);
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);

    $this->glossaryApi = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $this->glossaryHelper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);

    $entity_repository = $this->createMock(EntityRepositoryInterface::class);
    $entity_type_bundle_info = $this->createMock(EntityTypeBundleInfoInterface::class);
    $current_user = $this->createMock(AccountInterface::class);

    $this->form = new DeeplMultilingualGlossaryCsvUploadForm_Test(
      $entity_repository,
      $entity_type_bundle_info,
      $this->glossaryApi,
      $this->glossaryHelper,
      $current_user,
    );
    $this->form->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests ::create.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('entity.repository', $this->createMock(EntityRepositoryInterface::class));
    $container->set('entity_type.bundle.info', $this->createMock(EntityTypeBundleInfoInterface::class));
    $container->set('tmgmt_deepl_glossary.ml.api', $this->glossaryApi);
    $container->set('tmgmt_deepl_glossary.ml.helper', $this->glossaryHelper);
    $container->set('current_user', $this->createMock(AccountInterface::class));
    $form = DeeplMultilingualGlossaryCsvUploadForm::create($container);
    /* @phpstan-ignore-next-line */
    $this->assertInstanceOf(DeeplMultilingualGlossaryCsvUploadForm::class, $form);
  }

  /**
   * Tests ::getFormId.
   */
  public function testGetFormId(): void {
    $this->assertSame('deepl_multilingual_glossary_csv_upload_form', $this->form->getFormId());
  }

  /**
   * Tests ::buildForm.
   */
  public function testBuildForm(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('id')->willReturn(1);

    $translator = $this->createMock(Translator::class);
    $translator->method('getRemoteLanguagesMappings')->willReturn(['en', 'de', 'fr']);
    $glossary->method('getTranslator')->willReturn($translator);

    $this->glossaryHelper->method('getAllowedLanguages')->willReturn([
      'EN' => 'English',
      'DE' => 'German',
      'FR' => 'French',
    ]);
    $this->glossaryHelper->method('fixLanguageMappings')->willReturnCallback(fn(string $code): string => strtoupper($code));
    $this->glossaryApi->method('setTranslator')->with($translator);

    $route_match = \Drupal::service('current_route_match');
    // @phpstan-ignore-next-line
    $route_match->method('getParameter')->with('deepl_ml_glossary')->willReturn($glossary);

    $form_state = $this->createMock(FormStateInterface::class);
    $form = $this->form->buildForm([], $form_state);

    $this->assertArrayHasKey('source_lang', $form);
    $this->assertArrayHasKey('target_lang', $form);
    $this->assertArrayHasKey('csv_file', $form);
    $this->assertArrayHasKey('allow_overwrite', $form);
    $this->assertArrayHasKey('actions', $form);
    $actions = $form['actions'];
    $this->assertIsArray($actions);
    $this->assertArrayHasKey('submit', $actions);
    $this->assertArrayHasKey('cancel', $actions);
    $submit = $actions['submit'];
    $this->assertIsArray($submit);
    $value = $submit['#value'];
    $this->assertInstanceOf(\Stringable::class, $value);
    $this->assertSame('Upload CSV', (string) $value);
  }

  /**
   * Tests ::buildForm when the route does not provide a valid glossary.
   */
  public function testBuildFormNoGlossary(): void {
    $route_match = \Drupal::service('current_route_match');
    // @phpstan-ignore-next-line
    $route_match->method('getParameter')->with('deepl_ml_glossary')->willReturn(NULL);

    $this->messenger->expects($this->once())->method('addError')
      ->with($this->callback(fn($message): bool => $message instanceof \Stringable && (string) $message === 'Glossary not found.'));
    $this->glossaryApi->expects($this->never())->method('setTranslator');

    $form_state = $this->createMock(FormStateInterface::class);
    $form = $this->form->buildForm(['existing' => 'value'], $form_state);

    $this->assertSame(['existing' => 'value'], $form);
    $this->assertArrayNotHasKey('source_lang', $form);
  }

  /**
   * Tests ::buildForm when the glossary has no valid translator.
   */
  public function testBuildFormNoTranslator(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn(NULL);

    $route_match = \Drupal::service('current_route_match');
    // @phpstan-ignore-next-line
    $route_match->method('getParameter')->with('deepl_ml_glossary')->willReturn($glossary);

    $this->messenger->expects($this->once())->method('addError')
      ->with($this->callback(fn($message): bool => $message instanceof \Stringable && (string) $message === 'Translator not found for this glossary.'));
    $this->glossaryApi->expects($this->never())->method('setTranslator');

    $form_state = $this->createMock(FormStateInterface::class);
    $form = $this->form->buildForm(['existing' => 'value'], $form_state);

    $this->assertSame(['existing' => 'value'], $form);
    $this->assertArrayNotHasKey('source_lang', $form);
  }

  /**
   * Tests ::validateLanguagePair with a valid pair.
   */
  public function testValidateLanguagePairValid(): void {
    $this->glossaryHelper->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([
        ['en' => 'de'],
        ['en' => 'fr'],
        ['de' => 'en'],
      ]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');
    $this->form->validateLanguagePairPublic($form_state, 'en', 'de');
  }

  /**
   * Tests ::validateLanguagePair with an invalid pair.
   */
  public function testValidateLanguagePairInvalid(): void {
    $this->glossaryHelper->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([
        ['en' => 'de'],
        ['en' => 'fr'],
      ]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->exactly(2))
      ->method('setErrorByName')
      ->with($this->anything(), $this->anything());
    $this->form->validateLanguagePairPublic($form_state, 'de', 'en');
  }

  /**
   * Tests ::validateNoExistingDictionary when no dictionary exists.
   */
  public function testValidateNoExistingDictionaryNoMatch(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('glossary-uuid');
    $this->form->setGlossary($glossary);

    $this->glossaryHelper->method('hasMultilingualGlossaryDictionary')
      ->with('glossary-uuid', 'en', 'de')
      ->willReturn(FALSE);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');
    $this->form->validateNoExistingDictionaryPublic($form_state, 'en', 'de');
  }

  /**
   * Tests ::validateNoExistingDictionary when a dictionary already exists.
   */
  public function testValidateNoExistingDictionaryMatch(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn('glossary-uuid');
    $this->form->setGlossary($glossary);

    $this->glossaryHelper->method('hasMultilingualGlossaryDictionary')
      ->with('glossary-uuid', 'en', 'de')
      ->willReturn(TRUE);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->exactly(2))
      ->method('setErrorByName')
      ->with($this->anything(), $this->anything());
    $this->form->validateNoExistingDictionaryPublic($form_state, 'en', 'de');
  }

  /**
   * Tests ::validateNoExistingDictionary when glossary is not set.
   */
  public function testValidateNoExistingDictionaryNoGlossary(): void {
    $this->form->setGlossary(NULL);
    $this->glossaryHelper->expects($this->never())->method('hasMultilingualGlossaryDictionary');
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');
    $this->form->validateNoExistingDictionaryPublic($form_state, 'en', 'de');
  }

  /**
   * Tests ::validateNoExistingDictionary when glossary_id is not a string.
   */
  public function testValidateNoExistingDictionaryNoGlossaryId(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getGlossaryId')->willReturn(NULL);
    $this->form->setGlossary($glossary);

    $this->glossaryHelper->expects($this->never())->method('hasMultilingualGlossaryDictionary');
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');
    $this->form->validateNoExistingDictionaryPublic($form_state, 'en', 'de');
  }

  /**
   * Tests ::submitForm with a successful submission.
   */
  public function testSubmitFormSuccess(): void {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('id')->willReturn(1);
    $this->form->setGlossary($glossary);
    $this->form->setCsvEntries(['Hello' => 'Hallo']);

    $this->glossaryHelper->method('createOrUpdateDictionaryFromEntries')
      ->willReturn($this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class));

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('setRedirect')
      ->with('entity.deepl_ml_glossary.edit_form', ['deepl_ml_glossary' => 1]);

    $form = [];
    $this->form->submitForm($form, $form_state);
  }

  /**
   * Tests ::submitForm when glossary is not set.
   */
  public function testSubmitFormNoGlossary(): void {
    $this->form->setGlossary(NULL);
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setRedirect');
    $form = [];
    $this->form->submitForm($form, $form_state);
  }

  /**
   * Tests ::submitForm when CSV entries are not set.
   */
  public function testSubmitFormNoCsvEntries(): void {
    $this->form->setGlossary($this->createMock(DeeplMultilingualGlossaryInterface::class));
    $this->form->setCsvEntries(NULL);
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setRedirect');
    $form = [];
    $this->form->submitForm($form, $form_state);
  }

  /**
   * Tests ::validateForm with same source and target language.
   */
  public function testValidateFormSameLanguage(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturnMap([
      ['source_lang', 'en'],
      ['target_lang', 'en'],
      ['allow_overwrite', 0],
    ]);
    $form_state->method('getErrors')->willReturn(['target_lang' => 'error']);
    $form_state->expects($this->atLeastOnce())->method('setErrorByName');
    $form = [];
    $this->form->validateFormPublic($form, $form_state);
  }

  /**
   * Tests ::validateAndParseCsvFile with existing form errors.
   */
  public function testValidateAndParseCsvFileWithExistingErrors(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('getErrors')->willReturn(['source_lang' => 'error']);
    $this->glossaryHelper->expects($this->never())->method('parseCsvContent');
    $form = [];
    $this->form->validateAndParseCsvFilePublic($form, $form_state);
  }

  /**
   * Tests ::validateAndParseCsvFile when the uploaded file has no real path.
   */
  public function testValidateAndParseCsvFileUnreadablePath(): void {
    $uploaded = $this->createMock(UploadedFile::class);
    $uploaded->method('isValid')->willReturn(TRUE);
    $uploaded->method('getRealPath')->willReturn(FALSE);

    $request = \Drupal::service('request_stack')->getCurrentRequest();
    // @phpstan-ignore-next-line
    $request->files->set('files', ['csv_file' => $uploaded]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('getErrors')->willReturn([]);
    $form_state->expects($this->once())->method('setErrorByName')
      ->with('csv_file', $this->anything());
    $this->glossaryHelper->expects($this->never())->method('parseCsvContent');

    $form = [];
    $this->form->validateAndParseCsvFilePublic($form, $form_state);
  }

  /**
   * Tests ::validateAndParseCsvFile with no file uploaded.
   */
  public function testValidateAndParseCsvFileNoFile(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('getErrors')->willReturn([]);
    $form_state->expects($this->once())->method('setErrorByName')
      ->with('csv_file', $this->anything());
    $this->glossaryHelper->expects($this->never())->method('parseCsvContent');
    $form = [];
    $this->form->validateAndParseCsvFilePublic($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

}

// @codingStandardsIgnoreStart

/**
 * Testable DeeplMultilingualGlossaryCsvUploadForm exposing protected members.
 */
class DeeplMultilingualGlossaryCsvUploadForm_Test extends DeeplMultilingualGlossaryCsvUploadForm {

  /**
   * {@inheritdoc}
   */
  public function setGlossary(?DeeplMultilingualGlossaryInterface $glossary): void {
    $this->glossary = $glossary;
  }

  /**
   * Sets the CSV entries.
   */
  public function setCsvEntries(?array $entries): void {
    $this->csvEntries = $entries;
  }

  /**
   * Public wrapper for ::validateForm.
   */
  public function validateFormPublic(array &$form, FormStateInterface $form_state): void {
    $this->validateForm($form, $form_state);
  }

  /**
   * Public wrapper for ::validateLanguagePair.
   */
  public function validateLanguagePairPublic(FormStateInterface $form_state, string $source_lang, string $target_lang): void {
    $this->validateLanguagePair($form_state, $source_lang, $target_lang);
  }

  /**
   * Public wrapper for ::validateNoExistingDictionary.
   */
  public function validateNoExistingDictionaryPublic(FormStateInterface $form_state, string $source_lang, string $target_lang): void {
    $this->validateNoExistingDictionary($form_state, $source_lang, $target_lang);
  }

  /**
   * Public wrapper for ::validateAndParseCsvFile.
   */
  public function validateAndParseCsvFilePublic(array &$form, FormStateInterface $form_state): void {
    $this->validateAndParseCsvFile($form, $form_state);
  }

}
// @codingStandardsIgnoreEnd
