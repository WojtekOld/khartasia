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
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDeleteForm;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplGlossaryDeleteForm class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDeleteForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryDeleteFormTest extends UnitTestCase {

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

    $form = DeeplMultilingualGlossaryDeleteForm::create($container);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeeplMultilingualGlossaryDeleteForm::class, $form);
  }

  /**
   * Tests the confirmation question.
   *
   * Tests the method ::getQuestion.
   */
  public function testGetQuestion(): void {
    $this->glossary
      ->expects($this->once())
      ->method('label')
      ->willReturn('Test Glossary');

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();

    $class->setEntity($this->glossary);

    $question = $class->getQuestion();
    $this->assertEquals(
      'Are you sure you want to delete the DeepL glossary "<em class="placeholder">Test Glossary</em>"?',
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
    $this->glossary->expects($this->once())->method('toUrl')->willReturn($url);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setEntity($this->glossary);

    $cancel_url = $class->getCancelUrl();
    $this->assertSame($url, $cancel_url);
  }

  /**
   * Tests the method ::getFormId.
   */
  public function testGetFormId(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('hasKey')->with('bundle')->willReturn(FALSE);
    $this->glossary->method('getEntityTypeId')->willReturn('deepl_ml_glossary');
    $this->glossary->method('getEntityType')->willReturn($entity_type);
    $class = $this->createClassMock();
    $class->setEntity($this->glossary);
    $class->setOperation('delete');
    $this->assertSame('deepl_ml_glossary_delete_form', $class->getFormId());
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

    // Ensure the form is submitted as expected.
    $this->glossary->expects($this->exactly(1))->method('delete');

    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApi::class);
    // Verify if deleteMultilingualGlossary was called once with right argument.
    $glossary_api
      ->expects($this->exactly(1))
      ->method('deleteMultilingualGlossary')
      ->with($this->equalTo($glossary_id));
    // Check for translator.
    $glossary_api
      ->expects($this->once())
      ->method('setTranslator')
      ->with($this->equalTo($translator));

    // Simulate form submission.
    $form_state = $this->createMock(FormStateInterface::class);

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setEntity($this->glossary);
    $class->setGlossaryApi($glossary_api);

    $form = [];
    $class->submitForm($form, $form_state);
  }

  /**
   * The data provider for testDeleteDeeplGlossaryNoAction.
   *
   * @return array
   *   Array of test data.
   */
  public static function dataProviderTestDeleteDeeplGlossaryNoAction(): array {
    return [
      'no glossary_id defined' => [
        NULL,
        TranslatorInterface::class,
      ],
      'no translator defined' => ['12345', NULL],
      'no translator and no glossary_id defined' => [NULL, NULL],
    ];
  }

  /**
   * Tests the method ::deleteDeeplGlossary.
   *
   * @dataProvider dataProviderTestDeleteDeeplGlossaryNoAction
   */
  public function testDeleteDeeplGlossaryNoAction(
    ?string $glossary_id,
    ?string $translatorClass,
  ): void {

    // Mock glossary methods.
    $this->glossary->method('getGlossaryId')->willReturn($glossary_id);
    // @phpstan-ignore-next-line.
    $translator = $translatorClass !== NULL ? $this->createMock($translatorClass) : NULL;
    $this->glossary->method('getTranslator')->willReturn($translator);

    // Mock the glossary API.
    $glossary_api = $this->createMock(DeeplMultilingualGlossaryApiInterface::class);
    $glossary_api->expects($this->never())->method('deleteMultilingualGlossary');

    // Mock the class.
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDeleteForm_Test&MockObject $class */
    $class = $this->createClassMock();
    $class->setGlossaryApi($glossary_api);
    $class->setEntity($this->glossary);

    // Mock the form state.
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];
    $class->submitForm($form, $form_state);
  }

  /**
   * Creates and returns a test class mock.
   *
   * @param list<non-empty-string> $only_methods
   *   An array of names for methods to be configurable.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryDeleteForm_Test|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked class.
   */
  protected function createClassMock(
    array $only_methods = [],
  ): DeeplMultilingualGlossaryDeleteForm_Test|MockObject {
    return $this->getMockBuilder(DeeplMultilingualGlossaryDeleteForm_Test::class)
      ->disableOriginalConstructor()
      ->onlyMethods($only_methods)
      ->getMock();
  }

}

// @codingStandardsIgnoreStart

/**
 * Mocked DeeplGlossaryDeleteForm class for tests.
 */
class DeeplMultilingualGlossaryDeleteForm_Test extends DeeplMultilingualGlossaryDeleteForm {

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
