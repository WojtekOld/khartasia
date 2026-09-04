<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryBatchInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryFetchForm;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryFetchForm class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryFetchForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryFetchFormTest extends UnitTestCase {

  /**
   * The glossary batch service mock.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryBatchInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected DeeplMultilingualGlossaryBatchInterface&MockObject $glossaryBatch;

  /**
   * The form under test.
   *
   * @var \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryFetchForm
   */
  protected DeeplMultilingualGlossaryFetchForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->glossaryBatch = $this->createMock(DeeplMultilingualGlossaryBatchInterface::class);
    $this->form = new DeeplMultilingualGlossaryFetchForm($this->glossaryBatch);
    $this->form->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests the static ::create factory.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('tmgmt_deepl_glossary.ml.batch', $this->glossaryBatch);
    $form = DeeplMultilingualGlossaryFetchForm::create($container);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeeplMultilingualGlossaryFetchForm::class, $form);
  }

  /**
   * Tests the method ::getFormId.
   */
  public function testGetFormId(): void {
    $this->assertSame('tmgmt_deepl_ml_glossary_fetch_form', $this->form->getFormId());
  }

  /**
   * Tests the confirm form text methods.
   */
  public function testConfirmFormTexts(): void {
    $this->assertSame('Fetch DeepL glossaries', (string) $this->form->getConfirmText());
    $this->assertSame('This action will fetch all DeepL glossaries via the DeepL API.', (string) $this->form->getDescription());
    $this->assertSame('Do you want to fetch the latest DeepL glossaries via the DeepL API?', (string) $this->form->getQuestion());
  }

  /**
   * Tests the method ::getCancelUrl.
   */
  public function testGetCancelUrl(): void {
    $this->assertSame('entity.deepl_ml_glossary.collection', $this->form->getCancelUrl()->getRouteName());
  }

  /**
   * Tests the method ::submitForm.
   */
  public function testSubmitForm(): void {
    $this->glossaryBatch->expects($this->once())->method('buildBatch');
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('entity.deepl_ml_glossary.collection');
    $form = [];
    $this->form->submitForm($form, $form_state);
  }

}
