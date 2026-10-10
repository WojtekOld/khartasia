<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ui\Kernel;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ui_patterns\Traits\ConfigImporterTrait;
use Drupal\Tests\ui_patterns_ui\Traits\ComponentFormDisplayCreationTrait;

/**
 * Modules, displays and the form harness the kernel tests share.
 */
abstract class UiPatternsUiKernelTestBase extends KernelTestBase {

  use ComponentFormDisplayCreationTrait;
  use ConfigImporterTrait;

  protected const COMPONENT = 'ui_patterns_test:test-component';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'ui_patterns', 'ui_patterns_test']);
  }

  /**
   * Builds a form around the element and returns the element built.
   */
  protected function buildElement(array $element, ?FormStateInterface $form_state = NULL): array {
    $form_state ??= new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->formObject($element), $form_state);
    return $form['element'];
  }

  /**
   * Submits the element with these values and returns its value.
   */
  protected function submitElement(array $element, array $values): array {
    $form_state = (new FormState())->setValues(['element' => $values]);
    $this->container->get('form_builder')->submitForm($this->formObject($element), $form_state);
    self::assertSame([], $form_state->getErrors());
    return $form_state->getValue('element');
  }

  /**
   * The value must fit ui_patterns_component, as a host stores it.
   */
  protected function assertComponentSchema(array $value): void {
    $name = 'block.block.schema_test_block';
    $config = $this->loadConfigFixture($this->container->get('extension.list.module')->getPath('ui_patterns') . '/tests/fixtures/config/' . $name . '.yml');
    $config['settings']['ui_patterns'] = $value;
    $typed = $this->container->get(TypedConfigManagerInterface::class);
    $this->assertConfigSchema($typed, $name, $config);
    $violations = [];
    foreach ($typed->createFromNameAndData($name, $config)->validate() as $violation) {
      if (\str_starts_with($violation->getPropertyPath(), 'settings.ui_patterns')) {
        $violations[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
      }
    }
    self::assertSame([], $violations);
  }

  /**
   * A form holding the element under 'element', nested like hosts do.
   */
  private function formObject(array $element): FormInterface {
    return new class($element + ['#tree' => TRUE]) extends FormBase {

      public function __construct(
        protected readonly array $element,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_patterns_ui_test_element_form';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['element'] = $this->element;
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

    };
  }

}
