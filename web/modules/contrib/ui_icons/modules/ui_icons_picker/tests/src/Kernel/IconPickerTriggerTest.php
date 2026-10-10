<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_picker\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_picker\Element\IconPickerTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the picker attached to the autocomplete element preview.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPickerTrigger::class)]
#[Group('ui_icons')]
class IconPickerTriggerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_picker',
    'ui_icons_test',
  ];

  /**
   * Builds a form holding one element and returns that element.
   *
   * @param array $element
   *   The element to build, without its title.
   *
   * @return array
   *   The processed element.
   */
  private function buildElement(array $element): array {
    $request = Request::create('/');
    // The form builder reads the session to build the form token.
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $form_object = new class($element) implements FormInterface {

      /**
       * Holds the element under test.
       *
       * @param array $element
       *   The element to put in the form.
       */
      public function __construct(private readonly array $element) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'ui_icons_picker_trigger_test';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['icon'] = ['#title' => 'Icon'] + $this->element;
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function validateForm(array &$form, FormStateInterface $form_state): void {}

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

    };

    // FormBuilder takes the state by reference.
    $form_state = new FormState();
    $form = $this->container->get('form_builder')
      ->buildForm($form_object, $form_state);

    return $form['icon'];
  }

  /**
   * Tests the autocomplete input carries what the dialog needs.
   */
  public function testAutocompleteGetsThePicker(): void {
    $element = $this->buildElement(['#type' => 'icon_autocomplete']);

    $attributes = $element['icon_id']['#attributes'];
    // The marker js/preview.trigger.js selects on. The dialog attributes are
    // shared with 'icon_picker', so they cannot carry the intent themselves.
    $this->assertSame('true', $attributes['data-icon-preview-trigger']);
    $this->assertSame('/ui-icons/ajax/picker-icon-library', $attributes['data-dialog-url']);
    // Set by IconAutocomplete, the dialog reports the picked icon back to it.
    $this->assertNotEmpty($attributes['data-wrapper-id']);
    $this->assertContains('ui_icons_picker/preview_trigger', $element['icon_id']['#attached']['library']);

    // The autocomplete must survive, it is the point of triggering from the
    // preview rather than from the input the way icon_picker does.
    $this->assertSame('ui_icons.autocomplete', $element['icon_id']['#autocomplete_route_name']);
    $this->assertNotContains('form-icon-dialog', $attributes['class'] ?? []);
  }

  /**
   * Tests the pack limit of the element is passed on to the dialog.
   */
  public function testAllowedIconPackIsPassedOn(): void {
    $element = $this->buildElement([
      '#type' => 'icon_autocomplete',
      '#allowed_icon_pack' => ['test_path', 'test_svg'],
    ]);

    $this->assertSame('test_path+test_svg', $element['icon_id']['#attributes']['data-allowed-icon-pack']);
  }

  /**
   * Tests '#show_picker' FALSE leaves a plain autocomplete.
   */
  public function testShowPickerFalseOptsOut(): void {
    $element = $this->buildElement([
      '#type' => 'icon_autocomplete',
      '#show_picker' => FALSE,
    ]);

    $this->assertArrayNotHasKey('data-icon-preview-trigger', $element['icon_id']['#attributes']);
    $this->assertArrayNotHasKey('data-dialog-url', $element['icon_id']['#attributes']);
    $this->assertNotContains(
      'ui_icons_picker/preview_trigger',
      $element['icon_id']['#attached']['library'] ?? [],
    );
  }

  /**
   * Tests the icon_picker element is left untouched.
   *
   * It already opens the dialog from the input, and it is a separate element
   * type, so the alter on icon_autocomplete must not reach it.
   */
  public function testIconPickerElementIsNotAltered(): void {
    $element = $this->buildElement(['#type' => 'icon_picker']);

    $this->assertContains('form-icon-dialog', $element['icon_id']['#attributes']['class']);
    // The shared dialog attributes are there, the marker is not, which is what
    // keeps the preview trigger off this element on a page holding both.
    $this->assertArrayHasKey('data-dialog-url', $element['icon_id']['#attributes']);
    $this->assertArrayNotHasKey('data-icon-preview-trigger', $element['icon_id']['#attributes']);
    $this->assertNotContains(
      'ui_icons_picker/preview_trigger',
      $element['icon_id']['#attached']['library'] ?? [],
    );

    $process = $this->container->get('plugin.manager.element_info')->getInfo('icon_picker')['#process'];
    foreach ($process as $callback) {
      $this->assertNotSame(IconPickerTrigger::class, $callback[0] ?? NULL);
    }
  }

}
