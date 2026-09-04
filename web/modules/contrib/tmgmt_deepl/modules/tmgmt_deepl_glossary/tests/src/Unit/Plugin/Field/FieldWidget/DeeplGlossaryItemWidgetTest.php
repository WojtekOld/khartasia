<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldWidget\DeeplGlossaryItemWidget;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplGlossaryItemWidget.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldWidget\DeeplGlossaryItemWidget
 * @group tmgmt_deepl_glossary
 */
class DeeplGlossaryItemWidgetTest extends UnitTestCase {

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
   * Creates a widget instance without invoking the heavy parent constructor.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldWidget\DeeplGlossaryItemWidget
   *   The widget instance.
   */
  protected function createWidget(): DeeplGlossaryItemWidget {
    /** @var \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldWidget\DeeplGlossaryItemWidget $widget */
    $widget = (new \ReflectionClass(DeeplGlossaryItemWidget::class))->newInstanceWithoutConstructor();
    $widget->setStringTranslation($this->getStringTranslationStub());
    return $widget;
  }

  /**
   * Tests ::formElement populates default values from the field item.
   */
  public function testFormElement(): void {
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getValue')->willReturn(['subject' => 'Hello', 'definition' => 'Hallo']);

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('offsetGet')->with(0)->willReturn($item);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $element = $this->createWidget()->formElement($items, 0, ['#base' => 1], $form, $form_state);

    $subject = $element['subject'];
    $this->assertIsArray($subject);
    $this->assertSame('textfield', $subject['#type']);
    $this->assertSame('Hello', $subject['#default_value']);
    $this->assertSame(255, $subject['#maxlength']);
    $definition = $element['definition'];
    $this->assertIsArray($definition);
    $this->assertSame('Hallo', $definition['#default_value']);
    // The original element data is preserved.
    $this->assertSame(1, $element['#base']);
  }

  /**
   * Tests ::formElement with no stored value falls back to NULL defaults.
   */
  public function testFormElementEmpty(): void {
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('offsetGet')->with(0)->willReturn(NULL);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $element = $this->createWidget()->formElement($items, 0, [], $form, $form_state);

    $subject = $element['subject'];
    $this->assertIsArray($subject);
    $this->assertNull($subject['#default_value']);
    $definition = $element['definition'];
    $this->assertIsArray($definition);
    $this->assertNull($definition['#default_value']);
  }

}
