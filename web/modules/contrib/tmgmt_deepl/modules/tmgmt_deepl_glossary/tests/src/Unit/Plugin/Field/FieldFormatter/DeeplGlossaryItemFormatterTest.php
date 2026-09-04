<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldFormatter\DeeplGlossaryItemFormatter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplGlossaryItemFormatter.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldFormatter\DeeplGlossaryItemFormatter
 * @group tmgmt_deepl_glossary
 */
class DeeplGlossaryItemFormatterTest extends UnitTestCase {

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
   * Builds a field item list double backed by the given items.
   *
   * @param \Drupal\Core\Field\FieldItemInterface[] $list
   *   The field items.
   *
   * @return \Drupal\Core\Field\FieldItemList
   *   The field item list double.
   */
  protected function createItemList(array $list): FieldItemList {
    $items = $this->getMockBuilder(FieldItemList::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $property = new \ReflectionProperty(FieldItemList::class, 'list');
    $property->setValue($items, $list);
    return $items;
  }

  /**
   * Tests ::viewElements renders a paragraph per field item.
   */
  public function testViewElements(): void {
    $item0 = $this->createMock(FieldItemInterface::class);
    $item0->method('getValue')->willReturn(['subject' => 'Hello', 'definition' => 'Hallo']);
    // An item with missing keys must fall back to empty strings.
    $item1 = $this->createMock(FieldItemInterface::class);
    $item1->method('getValue')->willReturn([]);

    $items = $this->createItemList([$item0, $item1]);

    /** @var \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldFormatter\DeeplGlossaryItemFormatter $formatter */
    $formatter = (new \ReflectionClass(DeeplGlossaryItemFormatter::class))->newInstanceWithoutConstructor();
    $formatter->setStringTranslation($this->getStringTranslationStub());

    $elements = $formatter->viewElements($items, 'en');

    $this->assertCount(2, $elements);
    $first = $elements[0];
    $this->assertSame('html_tag', $first['#type']);
    $this->assertSame('p', $first['#tag']);
    $first_value = $first['#value'];
    $this->assertInstanceOf(\Stringable::class, $first_value);
    $this->assertSame('Source text: Hello - Target text: Hallo', (string) $first_value);

    $second = $elements[1];
    $second_value = $second['#value'];
    $this->assertInstanceOf(\Stringable::class, $second_value);
    $this->assertSame('Source text:  - Target text: ', (string) $second_value);
  }

  /**
   * Tests ::viewElements returns an empty array for an empty field list.
   */
  public function testViewElementsEmpty(): void {
    $items = $this->createItemList([]);

    /** @var \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldFormatter\DeeplGlossaryItemFormatter $formatter */
    $formatter = (new \ReflectionClass(DeeplGlossaryItemFormatter::class))->newInstanceWithoutConstructor();
    $formatter->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame([], $formatter->viewElements($items, 'en'));
  }

}
