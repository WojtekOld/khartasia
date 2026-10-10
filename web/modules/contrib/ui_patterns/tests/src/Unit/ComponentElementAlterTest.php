<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\Markup;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\Element\ComponentElementAlter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the Component alter.
 *
 * @internal
 */
#[CoversClass(ComponentElementAlter::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentElementAlterTest extends UnitTestCase {

  /**
   * Test the method ::canonicalize().
   */
  #[DataProvider('provideSlots')]
  public function testIsSlotEmpty(array $slot, bool $isEmpty): void {
    self::assertEquals($isEmpty, ComponentElementAlter::isSlotEmpty($slot));
  }

  /**
   * Provide data for testCanonicalize.
   */
  public static function provideSlots(): array {
    return [
      [
        ['#markup' => ''], TRUE,
      ],
      [
        ['#markup' => Markup::create('')], TRUE,
      ],
      [
        ['#markup' => Markup::create('test')], FALSE,
      ],
      [
        ['#cache' => ['tags' => ['tag1']]], TRUE,
      ],
      [
        ['#cache' => ['tags' => ['tag2']], '#markup' => NULL], TRUE,
      ],
      [
        ['#cache' => ['tags' => ['tag2']], 'children' => [['#markup' => '']]], TRUE,
      ],
      [
        ['#plain_text' => ''], TRUE,
      ],
      [
        ['#plain_text' => '', '#preprocess' => 'dummy'], FALSE,
      ],
      [
        [
          'children' => [
            ['#markup' => ''],
            ['#markup' => ''],
          ],
        ], TRUE,
      ],
      [
        [
          'children' => [
            ['#markup' => ''],
            ['#markup' => 'TEST'],
          ],
        ], FALSE,
      ],
      [
        ['children' => ['#markup' => 'some']], FALSE,
      ],
      [
        ['#markup' => 'some data'], FALSE,
      ],
      [
        ['#theme' => 'my_theme'], FALSE,
      ],
      [
        ['#weight' => 0], TRUE,
      ],
      [
        ['#theme' => 'my_theme', '#access' => FALSE], TRUE,
      ],
    ];
  }

  /**
   * A removed empty slot leaves its cache tags on the component.
   *
   * Without them, the component is not refreshed when the slot gets content.
   */
  #[DataProvider('provideEmptySlotsWithMetadata')]
  public function testEmptySlotMetadataKept(array $slot): void {
    $alter = new ComponentElementAlter();
    $element = $alter->normalizeSlots(['#slots' => ['slot' => $slot]]);
    self::assertArrayNotHasKey('slot', $element['#slots']);
    self::assertSame(['empty_slot'], $element['#cache']['tags'] ?? []);
  }

  /**
   * Empty slots carrying a cache tag at different places.
   */
  public static function provideEmptySlotsWithMetadata(): array {
    return [
      'on the slot' => [['#cache' => ['tags' => ['empty_slot']]]],
      'on a nested child' => [[['child' => ['#cache' => ['tags' => ['empty_slot']]]]]],
      'on the access of a hidden child' => [
        [['#markup' => 'Hidden', '#access' => AccessResult::forbidden()->addCacheTags(['empty_slot'])]],
      ],
    ];
  }

  /**
   * A child hidden by a boolean leaks no settings to the page.
   */
  public function testHiddenChildMetadataDropped(): void {
    $alter = new ComponentElementAlter();
    $hidden = ['#markup' => 'Hidden', '#access' => FALSE, '#attached' => ['drupalSettings' => ['hidden' => TRUE]]];
    $element = $alter->normalizeSlots(['#slots' => ['slot' => [$hidden]]]);
    self::assertArrayNotHasKey('slot', $element['#slots']);
    self::assertEmpty($element['#attached'] ?? []);
  }

}
