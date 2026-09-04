<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ckeditor5\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * A text format with the component_embed filter and its editor.
 */
abstract class CKEditor5KernelTestBase extends KernelTestBase {

  protected const FORMAT = 'component_test';

  protected const COMPONENT = 'ui_patterns_test:test-component';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'editor',
    'ckeditor5',
    'node',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installConfig(['system', 'user', 'filter', 'node', 'ui_patterns', 'ui_patterns_test']);
    // Editor validation discovers libraries: avoid needing a theme.
    $this->config('system.theme')->delete();
    FilterFormat::create([
      'format' => self::FORMAT,
      'name' => 'Component test',
      'filters' => ['component_embed' => ['status' => TRUE, 'weight' => 100]],
    ])->save();
    Editor::create([
      'format' => self::FORMAT,
      'editor' => 'ckeditor5',
      'settings' => ['toolbar' => ['items' => ['drupalComponent']], 'plugins' => []],
      'image_upload' => ['status' => FALSE],
    ])->save();
  }

  /**
   * Creates the page content type with a body field.
   */
  protected function createPageType(): NodeType {
    $type = NodeType::create(['type' => 'page', 'name' => 'Basic page']);
    $type->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
    ])->save();
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page')
      ->setComponent('body', ['type' => 'text_default'])
      ->save();
    return $type;
  }

  /**
   * The tag the editor saves for a component.
   *
   * @param string $component_id
   *   The component.
   * @param array $settings
   *   The component configuration, without component_id.
   */
  protected function componentTag(string $component_id, array $settings = []): string {
    $settings += ['component_id' => $component_id];
    return '<drupal-component data-component-id="' . $component_id . '" data-component-settings="' . \htmlspecialchars(Json::encode($settings), \ENT_QUOTES) . '"></drupal-component>';
  }

  /**
   * The configuration of a prop taking its value from a source.
   */
  protected function propSource(string $source_id, array $source): array {
    return ['source_id' => $source_id, 'source' => $source];
  }

}
