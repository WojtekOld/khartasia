<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_patterns\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the config schema of the icon UI Patterns sources.
 *
 * @internal
 */
#[CoversNothing]
#[Group('ui_icons')]
#[Group('ui_icons_patterns')]
class IconSourceConfigSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'system',
    'ui_icons',
    'ui_icons_patterns',
    'ui_icons_patterns_test',
    'ui_icons_test',
    'ui_patterns',
    'ui_patterns_field_formatters',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'entity_test'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Test the schema of the icon source values, used on a prop.
   *
   * Saving fails when the schema is missing or does not match the value, as
   * kernel tests check the config schema strictly.
   */
  #[DataProvider('providerIconSourceValue')]
  public function testIconSourceSchema(mixed $value): void {
    $settings = $this->saveDisplay([
      'props' => [
        'icon1' => [
          'source_id' => 'icon',
          'source' => ['value' => $value],
        ],
      ],
    ]);

    $this->assertSame($value, $settings['props']['icon1']['source']['value']);
  }

  /**
   * Test the schema of the icon renderable source values, used on a slot.
   */
  #[DataProvider('providerIconSourceValue')]
  public function testIconRenderableSourceSchema(mixed $value): void {
    $settings = $this->saveDisplay([
      'slots' => [
        'label' => [
          'sources' => [
            [
              'source_id' => 'icon_renderable',
              'source' => ['value' => $value],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame($value, $settings['slots']['label']['sources'][0]['source']['value']);
  }

  /**
   * Save a display with the test component, return its UI Patterns settings.
   */
  private function saveDisplay(array $ui_patterns): array {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_reference', [
      'type' => 'ui_patterns_component',
      'settings' => [
        'ui_patterns' => [
          'component_id' => 'ui_icons_patterns_test:icon_test',
        ] + $ui_patterns,
      ],
    ]);
    $display->save();

    return $display->getComponent('field_reference')['settings']['ui_patterns'];
  }

  /**
   * Provides icon source values.
   */
  public static function providerIconSourceValue(): array {
    return [
      // The icon autocomplete element sets the value to NULL when nothing is
      // selected.
      'no icon selected' => [NULL],
      'icon with pack settings' => [
        [
          'target_id' => 'test_settings:foo',
          'settings' => [
            'test_settings' => [
              'width' => 32,
              'height' => 33,
              'title' => 'Test title',
            ],
          ],
        ],
      ],
    ];
  }

}
