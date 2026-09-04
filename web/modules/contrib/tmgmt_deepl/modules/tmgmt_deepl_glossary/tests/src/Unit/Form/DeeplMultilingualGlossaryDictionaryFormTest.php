<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryForm;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryDictionaryForm class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryDictionaryFormTest extends UnitTestCase {

  /**
   * The glossary API mock.
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
   * The form under test.
   *
   * @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDictionaryForm_Test
   */
  protected DeeplMultilingualGlossaryDictionaryForm_Test $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('current_user', $this->createMock(AccountInterface::class));
    \Drupal::setContainer($container);

    $entity_repository = $this->createMock(EntityRepositoryInterface::class);
    $entity_type_bundle_info = $this->createMock(EntityTypeBundleInfoInterface::class);
    $time = $this->createMock(TimeInterface::class);
    $this->glossaryApi = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $this->glossaryHelper = $this->createMock(DeeplMultilingualGlossaryHelperInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $this->form = new DeeplMultilingualGlossaryDictionaryForm_Test(
      $entity_repository,
      $entity_type_bundle_info,
      $time,
      $this->glossaryApi,
      $this->glossaryHelper,
      $account,
    );
    $this->form->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Tests the static ::create factory and constructor wiring.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('entity.repository', $this->createMock(EntityRepositoryInterface::class));
    $container->set('entity_type.bundle.info', $this->createMock(EntityTypeBundleInfoInterface::class));
    $container->set('datetime.time', $this->createMock(TimeInterface::class));
    $container->set('tmgmt_deepl_glossary.ml.api', $this->glossaryApi);
    $container->set('tmgmt_deepl_glossary.ml.helper', $this->glossaryHelper);
    $container->set('current_user', $this->createMock(AccountInterface::class));

    $form = DeeplMultilingualGlossaryDictionaryForm::create($container);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeeplMultilingualGlossaryDictionaryForm::class, $form);
  }

  /**
   * Tests ::buildForm wires up language options, search and a cancel link.
   */
  public function testBuildForm(): void {
    $translator = $this->createMock(Translator::class);
    $translator->method('getRemoteLanguagesMappings')->willReturn(['EN', 'DE', 'FR']);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('id')->willReturn(5);

    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary->method('isNew')->willReturn(TRUE);
    $dictionary->method('getGlossary')->willReturn($glossary);
    // A new dictionary opened from a glossary route receives its glossary_id.
    $dictionary->expects($this->once())->method('set')->with('glossary_id', '5');

    $this->glossaryHelper->method('getAllowedLanguages')->willReturn([
      'EN' => 'English',
      'DE' => 'German',
      'FR' => 'French',
    ]);
    $this->glossaryHelper->method('fixLanguageMappings')->willReturnCallback(fn(string $code): string => strtoupper($code));

    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('deepl_ml_glossary')->willReturn('5');
    \Drupal::getContainer()->set('current_route_match', $route_match);

    $this->form->setEntity($dictionary);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($dictionary);
    $form_state->method('getFormObject')->willReturn($form_object);

    $form = $this->form->buildForm([], $form_state);

    $this->assertArrayHasKey('source_lang', $form);
    $this->assertArrayHasKey('target_lang', $form);

    $entries_search = $form['entries_search'];
    $this->assertIsArray($entries_search);
    $this->assertArrayHasKey('search_input', $entries_search);
    $this->assertArrayHasKey('reset_button', $entries_search);

    $attached = $form['#attached'];
    $this->assertIsArray($attached);
    $library = $attached['library'];
    $this->assertIsArray($library);
    $this->assertContains('tmgmt_deepl_glossary/tmgmt_deepl_glossary.entries_search', $library);

    $actions = $form['actions'];
    $this->assertIsArray($actions);
    $cancel = $actions['cancel'];
    $this->assertIsArray($cancel);
    $this->assertSame('link', $cancel['#type']);
  }

  /**
   * Tests ::validateForm runs the custom validators for a new dictionary.
   */
  public function testValidateForm(): void {
    $translator = $this->createMock(TranslatorInterface::class);
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('getGlossaryId')->willReturn('gl-1');

    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary->method('isNew')->willReturn(TRUE);
    $dictionary->method('getGlossary')->willReturn($glossary);
    $dictionary->method('getSourceLanguage')->willReturn('en');
    $dictionary->method('getTargetLanguage')->willReturn('de');

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('showRevisionUi')->willReturn(FALSE);
    $dictionary->method('getEntityType')->willReturn($entity_type);
    $violations = $this->createMock('\Drupal\Core\Entity\EntityConstraintViolationListInterface');
    $violations->method('getEntityViolations')->willReturn([]);
    $dictionary->method('validate')->willReturn($violations);
    $dictionary->method('getFieldDefinitions')->willReturn([]);

    $this->form->setBuiltEntity($dictionary);

    // Valid language pair, no duplicate entries, no existing dictionary.
    $this->glossaryHelper->method('getValidSourceTargetLanguageCombinations')->willReturn([['en' => 'de']]);
    $this->glossaryHelper->method('hasMultilingualGlossaryDictionary')->willReturn(FALSE);

    $form_display = $this->createMock('\Drupal\Core\Entity\Entity\EntityFormDisplay');
    $form_display->method('getComponents')->willReturn([]);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($dictionary);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->method('get')->willReturn($form_display);
    $form_state->method('getValues')->willReturn([
      'source_lang' => [['value' => 'en']],
      'target_lang' => [['value' => 'de']],
    ]);
    $form_state->method('getUserInput')->willReturn([
      'entries' => [['subject' => 'hello', 'definition' => 'hallo']],
    ]);
    $form_state->method('getErrors')->willReturn([]);
    $form_state->expects($this->never())->method('setErrorByName');

    $this->form->setEntity($dictionary);
    $form_array = [];
    $result = $this->form->validateForm($form_array, $form_state);
    $this->assertSame($dictionary, $result);
  }

  /**
   * Tests the method ::getFormId.
   */
  public function testGetFormId(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('hasKey')->with('bundle')->willReturn(FALSE);

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('deepl_ml_glossary_dictionary');
    $entity->method('getEntityType')->willReturn($entity_type);

    $this->form->setEntity($entity);
    $this->form->setOperation('default');
    $this->assertSame('deepl_ml_glossary_dictionary_form', $this->form->getFormId());
  }

  /**
   * Tests the method ::validateSourceTargetLanguage with a valid pair.
   */
  public function testValidateSourceTargetLanguageValid(): void {
    $this->glossaryHelper->expects($this->once())
      ->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([['en' => 'de'], ['en' => 'fr']]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'source_lang' => [['value' => 'en']],
        'target_lang' => [['value' => 'de']],
      ]);
    $form_state->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateSourceTargetLanguagePublic($form, $form_state);
  }

  /**
   * Tests the method ::validateSourceTargetLanguage with an invalid pair.
   */
  public function testValidateSourceTargetLanguageInvalid(): void {
    $this->glossaryHelper->expects($this->once())
      ->method('getValidSourceTargetLanguageCombinations')
      ->willReturn([['en' => 'de'], ['en' => 'fr']]);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'source_lang' => [['value' => 'de']],
        'target_lang' => [['value' => 'en']],
      ]);
    $form_state->expects($this->exactly(2))
      ->method('setErrorByName');

    $form = [];
    $this->form->validateSourceTargetLanguagePublic($form, $form_state);
  }

  /**
   * Tests the method ::validateUniqueEntries with no duplicate entries.
   */
  public function testValidateUniqueEntriesNoDuplicates(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getUserInput')
      ->willReturn([
        'entries' => [
          ['subject' => 'hello', 'definition' => 'hallo'],
          ['subject' => 'world', 'definition' => 'welt'],
        ],
      ]);
    $form_state->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateUniqueEntriesPublic($form, $form_state);
  }

  /**
   * Tests the method ::validateUniqueEntries with duplicate entries.
   */
  public function testValidateUniqueEntriesWithDuplicates(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getUserInput')
      ->willReturn([
        'entries' => [
          ['subject' => 'hello', 'definition' => 'hallo'],
          ['subject' => 'hello', 'definition' => 'hallo'],
        ],
      ]);
    $form_state->expects($this->atLeastOnce())
      ->method('setErrorByName');

    $form = [];
    $this->form->validateUniqueEntriesPublic($form, $form_state);
  }

  /**
   * Tests the method ::validateWhitespaceInEntries with clean entries.
   */
  public function testValidateWhitespaceInEntriesNoWhitespace(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getUserInput')
      ->willReturn([
        'entries' => [
          ['subject' => 'hello', 'definition' => 'hallo'],
        ],
      ]);
    $form_state->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateWhitespaceInEntriesPublic($form, $form_state);
  }

  /**
   * Tests the method ::validateWhitespaceInEntries with a leading space.
   */
  public function testValidateWhitespaceInEntriesLeadingSpace(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getUserInput')
      ->willReturn([
        'entries' => [
          ['subject' => ' hello', 'definition' => 'hallo'],
        ],
      ]);
    $form_state->expects($this->once())
      ->method('setErrorByName');

    $form = [];
    $this->form->validateWhitespaceInEntriesPublic($form, $form_state);
  }

  /**
   * Tests the method ::validateWhitespaceInEntries with a trailing space.
   */
  public function testValidateWhitespaceInEntriesTrailingSpace(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getUserInput')
      ->willReturn([
        'entries' => [
          ['subject' => 'hello', 'definition' => 'hallo '],
        ],
      ]);
    $form_state->expects($this->once())
      ->method('setErrorByName');

    $form = [];
    $this->form->validateWhitespaceInEntriesPublic($form, $form_state);
  }

  /**
   * Tests the method ::validateUniqueDictionary when no existing dictionary.
   */
  public function testValidateUniqueDictionaryNoExisting(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $translator = $this->createMock(TranslatorInterface::class);

    $glossary->expects($this->once())->method('getTranslator')->willReturn($translator);
    $glossary->expects($this->once())->method('getGlossaryId')->willReturn('test_glossary_id');
    $dictionary->expects($this->once())->method('getGlossary')->willReturn($glossary);
    $dictionary->expects($this->once())->method('getSourceLanguage')->willReturn('en');
    $dictionary->expects($this->once())->method('getTargetLanguage')->willReturn('de');

    $this->glossaryApi->expects($this->once())->method('setTranslator')->with($translator);
    $this->glossaryHelper->expects($this->once())
      ->method('hasMultilingualGlossaryDictionary')
      ->with('test_glossary_id', 'en', 'de')
      ->willReturn(FALSE);

    $form_state->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateUniqueDictionaryPublic($form, $form_state, $dictionary);
  }

  /**
   * Tests the method ::validateUniqueDictionary when it already exists.
   */
  public function testValidateUniqueDictionaryExisting(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $translator = $this->createMock(TranslatorInterface::class);

    $glossary->expects($this->once())->method('getTranslator')->willReturn($translator);
    $glossary->expects($this->once())->method('getGlossaryId')->willReturn('test_glossary_id');
    $dictionary->expects($this->once())->method('getGlossary')->willReturn($glossary);
    $dictionary->expects($this->once())->method('getSourceLanguage')->willReturn('en');
    $dictionary->expects($this->once())->method('getTargetLanguage')->willReturn('de');

    $this->glossaryApi->expects($this->once())->method('setTranslator')->with($translator);
    $this->glossaryHelper->expects($this->once())
      ->method('hasMultilingualGlossaryDictionary')
      ->with('test_glossary_id', 'en', 'de')
      ->willReturn(TRUE);

    $form_state->expects($this->exactly(2))->method('setErrorByName');

    $form = [];
    $this->form->validateUniqueDictionaryPublic($form, $form_state, $dictionary);
  }

}

// @codingStandardsIgnoreStart
class DeeplMultilingualGlossaryDictionaryForm_Test extends DeeplMultilingualGlossaryDictionaryForm {

  /**
   * The entity returned by the overridden ::buildEntity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $builtEntity;

  /**
   * Sets the entity that the stubbed ::buildEntity returns.
   */
  public function setBuiltEntity(\Drupal\Core\Entity\ContentEntityInterface $entity): void {
    $this->builtEntity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state): void {
    $form_state->set('entity_form_initialized', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form['#entity'] = $this->entity;
    $form['source_lang'] = ['widget' => []];
    $form['target_lang'] = ['widget' => []];
    $form['entries'] = ['#weight' => 5];
    $form['actions'] = ['submit' => []];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    return $this->builtEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSourceTargetLanguagePublic(array &$form, FormStateInterface $form_state): void {
    $this->validateSourceTargetLanguage($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateUniqueEntriesPublic(array &$form, FormStateInterface $form_state): void {
    $this->validateUniqueEntries($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateWhitespaceInEntriesPublic(array &$form, FormStateInterface $form_state): void {
    $this->validateWhitespaceInEntries($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateUniqueDictionaryPublic(array &$form, FormStateInterface $form_state, DeeplMultilingualGlossaryDictionaryInterface $dictionary): void {
    $this->validateUniqueDictionary($form, $form_state, $dictionary);
  }

}
// @codingStandardsIgnoreEnd
