<?php

declare(strict_types=1);

namespace Drupal\Tests\color_field\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\color_field\Hook\ColorFieldHooks;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that formatters and token hooks handle invalid color values gracefully.
 *
 * Invalid colors can reach the rendering layer when data is migrated or
 * written directly to the database without passing field validation. Each
 * formatter and the token hook must catch the ColorHex exception and degrade
 * gracefully rather than producing a PHP fatal error.
 *
 * @group color_field
 */
#[Group('color_field')]
#[RunTestsInSeparateProcesses]
class ColorFieldInvalidColorHandlingTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['color_field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The default storage format uses a 7-character column, so '#ZZZZZZ'
    // (an invalid but correctly-sized value) can be stored without truncation.
    FieldStorageConfig::create([
      'field_name' => 'field_color',
      'entity_type' => 'entity_test',
      'type' => 'color_field_type',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_color',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Creates and saves an entity whose color value fails ColorHex validation.
   *
   * 'ZZZZZZ' is not a valid hex color (Z is not a hex digit). The field's
   * preSave() will reformat it to '#ZZZZZZ', which fits the 7-character
   * storage column but still throws when passed to ColorHex. This simulates
   * data that was written directly to the database without field validation.
   */
  protected function createEntityWithInvalidColor(): EntityTest {
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->field_color->color = 'ZZZZZZ';
    $entity->field_color->opacity = 1.0;
    // save() intentionally bypasses constraint validation.
    $entity->save();
    return EntityTest::load($entity->id());
  }

  /**
   * Returns the render array for an entity field using the given formatter.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The entity to render.
   * @param string $formatter
   *   The formatter plugin ID.
   * @param array $settings
   *   Optional formatter settings.
   *
   * @return array
   *   The full entity render array (keyed by field name).
   */
  protected function buildViewDisplay(EntityTest $entity, string $formatter, array $settings = []): array {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_color', [
      'type' => $formatter,
      'label' => 'hidden',
      'settings' => $settings,
    ]);
    return $display->build($entity);
  }

  /**
   * Tests that the text formatter returns an empty string for invalid colors.
   */
  public function testTextFormatterWithInvalidColor(): void {
    $entity = $this->createEntityWithInvalidColor();
    $build = $this->buildViewDisplay($entity, 'color_field_formatter_text');

    $this->assertArrayHasKey('field_color', $build);
    $this->assertArrayHasKey(0, $build['field_color']);
    $this->assertSame('', (string) $build['field_color'][0]['#markup']);
  }

  /**
   * Tests that the swatch formatter returns empty output for invalid colors.
   */
  public function testSwatchFormatterWithInvalidColor(): void {
    $entity = $this->createEntityWithInvalidColor();
    $build = $this->buildViewDisplay($entity, 'color_field_formatter_swatch');

    $this->assertArrayHasKey('field_color', $build);
    $this->assertArrayHasKey(0, $build['field_color']);
    $this->assertSame('', $build['field_color'][0]['#color']);
  }

  /**
   * Tests that the swatch formatter omits data-color for invalid colors.
   *
   * When data_attribute is TRUE the formatter tries a second ColorHex
   * construction to get the raw hex value for the attribute. With an invalid
   * color it must skip the attribute entirely rather than crashing.
   */
  public function testSwatchFormatterDataAttributeWithInvalidColor(): void {
    $entity = $this->createEntityWithInvalidColor();
    $build = $this->buildViewDisplay($entity, 'color_field_formatter_swatch', [
      'data_attribute' => TRUE,
    ]);

    $this->assertArrayHasKey('field_color', $build);
    $this->assertArrayHasKey(0, $build['field_color']);
    $this->assertSame('', $build['field_color'][0]['#color']);
    // The data-color attribute must be absent when the color is invalid.
    $attributes = $build['field_color'][0]['#attributes'];
    $this->assertFalse(isset($attributes['data-color']));
  }

  /**
   * Tests that the swatch options formatter handles invalid colors.
   */
  public function testSwatchOptionsFormatterWithInvalidColor(): void {
    $entity = $this->createEntityWithInvalidColor();
    $build = $this->buildViewDisplay($entity, 'color_field_formatter_swatch_options');

    $this->assertArrayHasKey('field_color', $build);
    $this->assertArrayHasKey(0, $build['field_color']);
    $this->assertSame('', $build['field_color'][0]['#color']);
    $this->assertSame('', $build['field_color'][0]['#value']);
  }

  /**
   * Tests that the CSS formatter handles invalid colors without crashing.
   *
   * The color value in the generated CSS will be empty, which produces
   * syntactically invalid (but harmless) CSS rather than a PHP error.
   */
  public function testCssFormatterWithInvalidColor(): void {
    $entity = $this->createEntityWithInvalidColor();
    $build = $this->buildViewDisplay($entity, 'color_field_formatter_css');

    $this->assertArrayHasKey('field_color', $build);
    $this->assertArrayHasKey(0, $build['field_color']);
    // The formatter wraps the color value in a hidden div; with an invalid
    // color the value is empty so only the wrapper element remains.
    $this->assertStringContainsString(
      "<div class='hidden'></div>",
      (string) $build['field_color'][0]['#markup'],
    );
  }

  /**
   * Tests that hook_tokens() returns empty strings for invalid color values.
   *
   * The catch block in ColorFieldHooks::tokens() must return early so that
   * the subsequent foreach does not attempt to use an undefined $color_hex.
   */
  public function testTokensWithInvalidColor(): void {
    $entity = $this->createEntityWithInvalidColor();
    $item = $entity->field_color[0];

    $tokens = [
      'hex' => '[color_field:hex]',
      'rgb' => '[color_field:rgb]',
      'rgb:red' => '[color_field:rgb:red]',
      'rgb:opacity' => '[color_field:rgb:opacity]',
    ];

    $replacements = ColorFieldHooks::tokens(
      'color_field',
      $tokens,
      ['color_field' => $item],
      [],
      new BubbleableMetadata(),
    );

    foreach ($tokens as $original) {
      $this->assertArrayHasKey($original, $replacements);
      $this->assertSame('', $replacements[$original]);
    }
  }

}
