<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryDeleteForm;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryDictionaryDeleteForm class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryDeleteForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryDictionaryDeleteFormTest extends UnitTestCase {

  /**
   * The dictionary entity mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface
   */
  protected MockObject|DeeplMultilingualGlossaryDictionaryInterface $dictionary;

  /**
   * The glossary entity mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface
   */
  protected MockObject|DeeplMultilingualGlossaryInterface $glossary;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create/register string translation mock.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    // Mock a glossary entity.
    $this->glossary = $this->createMock(
      'Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface'
    );

    // Mock a dictionary entity.
    $this->dictionary = $this->createMock(
      'Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface'
    );
    $this->dictionary->method('getGlossary')->willReturn($this->glossary);
  }

  /**
   * Tests the static ::create factory and constructor wiring.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('entity.repository', $this->createMock(EntityRepositoryInterface::class));
    $container->set('entity_type.bundle.info', $this->createMock(EntityTypeBundleInfoInterface::class));
    $container->set('datetime.time', $this->createMock(TimeInterface::class));
    $container->set('tmgmt_deepl_glossary.ml.api', $this->createMock(DeeplMultilingualGlossaryApiInterface::class));

    $form = DeeplMultilingualGlossaryDictionaryDeleteForm::create($container);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeeplMultilingualGlossaryDictionaryDeleteForm::class, $form);
  }

  /**
   * Tests the confirmation question.
   *
   * Tests the method ::getQuestion.
   */
  public function testGetQuestion(): void {
    $this->dictionary
      ->expects($this->once())
      ->method('label')
      ->willReturn('Test Dictionary');

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDictionaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();

    $class->setEntity($this->dictionary);

    $question = $class->getQuestion();
    $this->assertEquals(
      'Are you sure you want to delete the dictionary "<em class="placeholder">Test Dictionary</em>" and all its entries?',
      $question->render()
    );
  }

  /**
   * Tests the cancel URL.
   *
   * Tests the method ::getCancelUrl.
   */
  public function testGetCancelUrl(): void {
    // Mock the entity to return a test URL.
    $url = $this->createMock(Url::class);
    $this->dictionary->expects($this->once())->method('toUrl')->willReturn($url);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDictionaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setEntity($this->dictionary);

    $cancel_url = $class->getCancelUrl();
    $this->assertSame($url, $cancel_url);
  }

  /**
   * Tests the method ::getFormId.
   */
  public function testGetFormId(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('hasKey')->with('bundle')->willReturn(FALSE);
    $this->dictionary->method('getEntityTypeId')->willReturn('deepl_ml_glossary_dictionary');
    $this->dictionary->method('getEntityType')->willReturn($entity_type);
    $class = $this->createClassMock();
    $class->setEntity($this->dictionary);
    $class->setOperation('delete');
    $this->assertSame('deepl_ml_glossary_dictionary_delete_form', $class->getFormId());
  }

  /**
   * Tests the method ::submitForm.
   */
  public function testSubmitForm(): void {
    // Mock glossary ID and translator.
    $glossary_id = '12345';
    $this->glossary->method('getGlossaryId')->willReturn($glossary_id);
    $translator = $this->createMock(TranslatorInterface::class);
    $this->glossary->method('getTranslator')->willReturn($translator);
    $source_language = 'EN';
    $target_language = 'DE';
    $this->dictionary->method('getSourceLanguage')->willReturn($source_language);
    $this->dictionary->method('getTargetLanguage')->willReturn($target_language);

    // Ensure the form is submitted as expected.
    $this->dictionary->expects($this->exactly(1))->method('delete');

    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApi::class);
    // Verify if deleteMultilingualGlossaryDictionary was called once with
    // the right argument.
    $glossary_api
      ->expects($this->exactly(1))
      ->method('deleteMultilingualGlossaryDictionary')
      ->with($this->equalTo($glossary_id), $this->equalTo($source_language), $this->equalTo($target_language));
    // Check for translator.
    $glossary_api
      ->expects($this->once())
      ->method('setTranslator')
      ->with($this->equalTo($translator));

    // Simulate form submission.
    $form_state = $this->createMock(FormStateInterface::class);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDictionaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setEntity($this->dictionary);
    $class->setGlossaryApi($glossary_api);

    $form = [];
    $class->submitForm($form, $form_state);
  }

  /**
   * Creates and returns a test class mock.
   *
   * @param list<non-empty-string> $only_methods
   *   An array of names for methods to be configurable.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDictionaryDeleteForm_Test|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked class.
   */
  protected function createClassMock(
    array $only_methods = [],
  ): DeeplMultilingualGlossaryDictionaryDeleteForm_Test|MockObject {
    return $this->getMockBuilder(DeeplMultilingualGlossaryDictionaryDeleteForm_Test::class)
      ->disableOriginalConstructor()
      ->onlyMethods($only_methods)
      ->getMock();
  }

}

// @codingStandardsIgnoreStart

/**
 * Mocked DeeplMultilingualGlossaryDictionaryDeleteForm class for tests.
 */
class DeeplMultilingualGlossaryDictionaryDeleteForm_Test extends DeeplMultilingualGlossaryDictionaryDeleteForm {

  /**
   * Setter for the glossaryApi property to allow manual setting in tests.
   *
   * @param \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface $glossaryApi
   *  The DeeplMultilingualGlossaryApiInterface.
   */
  public function setGlossaryApi(DeeplMultilingualGlossaryApiInterface $glossaryApi): void {
    $this->glossaryApi = $glossaryApi;
  }

}
// @codingStandardsIgnoreEnd
