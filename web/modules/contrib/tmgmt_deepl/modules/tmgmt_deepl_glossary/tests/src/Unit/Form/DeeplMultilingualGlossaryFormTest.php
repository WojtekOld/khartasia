<?php

// phpcs:ignore DrupalPractice.Objects.GlobalDrupal
namespace Drupal\tmgmt_deepl_glossary\Form;

if (!function_exists(__NAMESPACE__ . '\views_embed_view')) {

  /**
   * Shim for views_embed_view to allow unit testing of the form.
   */
  function views_embed_view(string $name, string $display_id = 'default', mixed ...$args): array {
    return [];
  }

}

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Form;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryForm;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryForm class.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryForm
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryFormTest extends UnitTestCase {

  /**
   * The glossary API service mock.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi&\PHPUnit\Framework\MockObject\MockObject
   */
  protected DeeplMultilingualGlossaryApi&MockObject $glossaryApi;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!defined('SAVED_NEW')) {
      define('SAVED_NEW', 1);
    }
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('renderer', $this->createMock(Renderer::class));
    $container->set('current_user', $this->createMock('\Drupal\Core\Session\AccountProxyInterface'));
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    \Drupal::setContainer($container);
    $this->glossaryApi = $this->createMock(DeeplMultilingualGlossaryApi::class);
  }

  /**
   * Creates the form under test with the mocked glossary API.
   *
   * @return \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryForm_Test
   *   The form instance.
   */
  protected function createForm(): DeeplMultilingualGlossaryForm_Test {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Form\DeeplMultilingualGlossaryForm_Test $form */
    /* @phpstan-ignore-next-line */
    $form = $this->getMockBuilder(DeeplMultilingualGlossaryForm_Test::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $form->setGlossaryApi($this->glossaryApi);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

  /**
   * Creates a glossary entity mock.
   *
   * @param bool $is_new
   *   Whether the glossary is new.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The glossary mock.
   */
  protected function createGlossary(bool $is_new): DeeplMultilingualGlossaryInterface&MockObject {
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('isNew')->willReturn($is_new);
    $glossary->method('getTranslator')->willReturn($this->createMock(TranslatorInterface::class));
    return $glossary;
  }

  /**
   * Tests that validation is skipped for existing glossaries.
   */
  public function testValidateNumberOfGlossariesSkipsExisting(): void {
    $this->glossaryApi->expects($this->never())->method('getMultilingualGlossaries');
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');
    $form = [];
    $this->createForm()->validateNumberOfGlossariesPublic($form, $form_state, $this->createGlossary(FALSE));
  }

  /**
   * Tests ::create.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('entity.repository', $this->createMock(EntityRepositoryInterface::class));
    $container->set('entity_type.bundle.info', $this->createMock(EntityTypeBundleInfoInterface::class));
    $container->set('datetime.time', $this->createMock(TimeInterface::class));
    $container->set('tmgmt_deepl_glossary.ml.api', $this->glossaryApi);
    $container->set('current_user', $this->createMock(AccountProxyInterface::class));
    $container->set('renderer', $this->createMock(Renderer::class));
    $form = DeeplMultilingualGlossaryForm::create($container);
    /* @phpstan-ignore-next-line */
    $this->assertInstanceOf(DeeplMultilingualGlossaryForm::class, $form);
  }

  /**
   * Tests ::save with an existing entity (default path).
   */
  public function testSaveDefault(): void {
    $translator = $this->createMock(TranslatorInterface::class);
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('label')->willReturn('My Glossary');
    $glossary->method('id')->willReturn(42);
    $glossary->method('save')->willReturn(2);
    $glossary->method('getTranslator')->willReturn($translator);
    $glossary->method('getGlossaryId')->willReturn('gl-123');

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setRedirect');

    $form = $this->createForm();
    $form->setEntity($glossary);
    $result = $form->save([], $form_state);
    $this->assertSame(2, $result);
  }

  /**
   * Tests ::save with a new entity (SAVED_NEW path).
   */
  public function testSaveNew(): void {
    $translator = $this->createMock(TranslatorInterface::class);
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('label')->willReturn('My Glossary');
    $glossary->method('id')->willReturn(42);
    $glossary->method('save')->willReturn(1);
    $glossary->method('getTranslator')->willReturn($translator);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('setRedirect')
      ->with('entity.deepl_ml_glossary_dictionary.add_form', ['deepl_ml_glossary' => 42]);

    $form = $this->createForm();
    $form->setEntity($glossary);
    $result = $form->save([], $form_state);
    $this->assertSame(1, $result);
  }

  /**
   * Tests the glossary limit error for free accounts.
   */
  public function testValidateNumberOfGlossariesFreeAccountLimit(): void {
    $this->glossaryApi->method('getMultilingualGlossaries')->willReturn(['existing-glossary']);
    $this->glossaryApi->method('isFreeAccount')->willReturn(TRUE);
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->with('tmgmt_translator');
    $form = [];
    $this->createForm()->validateNumberOfGlossariesPublic($form, $form_state, $this->createGlossary(TRUE));
  }

  /**
   * Tests that pro accounts may create multiple glossaries.
   */
  public function testValidateNumberOfGlossariesProAccount(): void {
    $this->glossaryApi->method('getMultilingualGlossaries')->willReturn(['existing-glossary']);
    $this->glossaryApi->method('isFreeAccount')->willReturn(FALSE);
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');
    $form = [];
    $this->createForm()->validateNumberOfGlossariesPublic($form, $form_state, $this->createGlossary(TRUE));
  }

  /**
   * Tests the error handling when the API is unavailable.
   */
  public function testValidateNumberOfGlossariesApiFailure(): void {
    $this->glossaryApi->method('getMultilingualGlossaries')->willThrowException(new \Exception('API down'));
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->with('tmgmt_translator');
    $form = [];
    $this->createForm()->validateNumberOfGlossariesPublic($form, $form_state, $this->createGlossary(TRUE));
  }

  /**
   * Tests getFormId() returns the entity type ID.
   */
  public function testGetFormId(): void {
    $entity_type = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $entity_type->method('hasKey')->with('bundle')->willReturn(FALSE);
    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $glossary->method('getEntityTypeId')->willReturn('deepl_ml_glossary');
    $glossary->method('getEntityType')->willReturn($entity_type);
    $form = $this->createForm();
    $form->setEntity($glossary);
    $form->setOperation('default');
    $this->assertSame('deepl_ml_glossary_form', $form->getFormId());
  }

  /**
   * Tests buildForm() for a new glossary.
   */
  public function testBuildFormNewGlossary(): void {
    $glossary = $this->createGlossary(TRUE);
    $glossary->method('id')->willReturn(NULL);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($glossary);
    $form_state->method('getFormObject')->willReturn($form_object);

    $form = $this->createForm();
    $form->setEntity($glossary);
    $form = $form->buildForm([], $form_state);
    $this->assertArrayHasKey('glossary_wrapper', $form);
    $actions = $form['actions'];
    $this->assertIsArray($actions);
    $submit = $actions['submit'];
    $this->assertIsArray($submit);
    $value = $submit['#value'];
    $this->assertInstanceOf(\Stringable::class, $value);
    $this->assertSame('Save and add entries', (string) $value);
    $this->assertArrayHasKey('cancel', $actions);
  }

  /**
   * Tests buildForm() for an existing glossary.
   */
  public function testBuildFormExistingGlossary(): void {
    $glossary = $this->createGlossary(FALSE);
    $glossary->method('id')->willReturn(42);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $renderer = $this->createMock(Renderer::class);
    $renderer->method('render')->willReturn('');

    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($glossary);
    $form_state->method('getFormObject')->willReturn($form_object);

    $form = $this->createForm();
    $form->setEntity($glossary);
    $form->setAccount($account);
    $form->setRenderer($renderer);
    $form = $form->buildForm([], $form_state);
    $this->assertArrayHasKey('glossary_wrapper', $form);
    $actions = $form['actions'];
    $this->assertIsArray($actions);
    $submit = $actions['submit'];
    $this->assertIsArray($submit);
    $value = $submit['#value'];
    $this->assertInstanceOf(\Stringable::class, $value);
    $this->assertSame('Save', (string) $value);
    $this->assertArrayHasKey('cancel', $actions);
  }

  /**
   * Tests buildForm() disables name fields for entry-only editors.
   */
  public function testBuildFormExistingGlossaryRestrictedPermissions(): void {
    $glossary = $this->createGlossary(FALSE);
    $glossary->method('id')->willReturn(42);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnMap([
      ['edit deepl_glossary glossary entries', TRUE],
      ['edit deepl_glossary entities', FALSE],
    ]);
    $renderer = $this->createMock(Renderer::class);
    $renderer->method('render')->willReturn('');

    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($glossary);
    $form_state->method('getFormObject')->willReturn($form_object);

    $form = $this->createForm();
    $form->setEntity($glossary);
    $form->setAccount($account);
    $form->setRenderer($renderer);
    $form = $form->buildForm([], $form_state);

    $label = $form['label'];
    $this->assertIsArray($label);
    $widget = $label['widget'];
    $this->assertIsArray($widget);
    $row = $widget[0];
    $this->assertIsArray($row);
    $value = $row['value'];
    $this->assertIsArray($value);
    $this->assertSame(['disabled' => TRUE], $value['#attributes']);
  }

  /**
   * Tests validateForm() for a new glossary.
   */
  public function testValidateFormNewGlossary(): void {
    $glossary = $this->createGlossary(TRUE);
    $glossary->method('getGlossaryId')->willReturn(NULL);

    $this->glossaryApi->method('getMultilingualGlossaries')->willReturn([]);
    $this->glossaryApi->method('isFreeAccount')->willReturn(FALSE);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setErrorByName');

    $entity_type = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $entity_type->method('showRevisionUi')->willReturn(FALSE);
    $glossary->method('getEntityType')->willReturn($entity_type);

    $violations = $this->createMock('\Drupal\Core\Entity\EntityConstraintViolationListInterface');
    $violations->method('getEntityViolations')->willReturn([]);
    $glossary->method('validate')->willReturn($violations);
    $glossary->method('getFieldDefinitions')->willReturn([]);

    $form_display = $this->createMock('\Drupal\Core\Entity\Entity\EntityFormDisplay');
    $form_display->method('getComponents')->willReturn([]);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($glossary);
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->method('get')->willReturn($form_display);
    $form_state->method('getValues')->willReturn([]);

    $form_array = [];
    $form = $this->createForm();
    $form->setEntity($glossary);
    $form->validateFormPublic($form_array, $form_state);
  }

}

// @codingStandardsIgnoreStart
/**
 * Testable DeeplMultilingualGlossaryForm exposing protected members.
 */
class DeeplMultilingualGlossaryForm_Test extends DeeplMultilingualGlossaryForm {

  /**
   * Setter for the glossaryApi property.
   *
   * @param \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi $glossaryApi
   *   The glossary API service.
   */
  public function setGlossaryApi(DeeplMultilingualGlossaryApi $glossaryApi): void {
    $this->glossaryApi = $glossaryApi;
  }

  /**
   * Sets the entity on the form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function setEntity(EntityInterface $entity): static {
    // @phpstan-ignore-next-line
    $this->entity = $entity;
    return $this;
  }

  /**
   * Sets the operation on the form.
   *
   * @param string $operation
   *   The operation.
   */
  public function setOperation($operation): static {
    $this->operation = $operation;
    return $this;
  }

  /**
   * Sets the account on the form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  public function setAccount(AccountInterface $account): void {
    $this->account = $account;
  }

  /**
   * Sets the renderer on the form.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function setRenderer(Renderer $renderer): void {
    $this->renderer = $renderer;
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
    $form['tmgmt_translator'] = ['widget' => []];
    $form['label'] = ['widget' => [0 => ['value' => []]]];
    $form['actions'] = ['submit' => []];
    return $form;
  }

  /**
   * Public wrapper for ::validateForm.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateFormPublic(array &$form, FormStateInterface $form_state): void {
    $this->validateForm($form, $form_state);
  }

  /**
   * Public wrapper for ::validateNumberOfGlossaries.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary
   *   The glossary entity.
   */
  public function validateNumberOfGlossariesPublic(array &$form, FormStateInterface $form_state, DeeplMultilingualGlossaryInterface $glossary): void {
    $this->validateNumberOfGlossaries($form, $form_state, $glossary);
  }

}
// @codingStandardsIgnoreEnd
