<?php

declare(strict_types=1);

namespace Drupal\Tests\simplenews\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the body field created in code for the simplenews_issue content type.
 *
 * The body field is not shipped as configuration because its type must match
 * the shared body storage, which differs between sites. These tests cover
 * _simplenews_add_body_field() for each kind of site.
 *
 * @group simplenews
 */
class SimplenewsBodyFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'views',
    'node',
    'simplenews',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system']);

    // The content type and email view modes normally come from the module's
    // optional and default configuration.
    NodeType::create(['type' => 'simplenews_issue', 'name' => 'Newsletter Issue'])->save();
    foreach (['email_html', 'email_plain', 'teaser'] as $mode) {
      EntityViewMode::create([
        'id' => 'node.' . $mode,
        'targetEntityType' => 'node',
        'label' => $mode,
      ])->save();
    }
  }

  /**
   * Creates the shared body field storage.
   */
  protected function createBodyStorage(string $type): FieldStorageConfig {
    $storage = FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => $type,
      'persist_with_no_fields' => TRUE,
    ]);
    $storage->save();
    return $storage;
  }

  /**
   * Asserts the displays have the expected body components.
   */
  protected function assertDisplays(string $widget_type): void {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repository */
    $repository = \Drupal::service('entity_display.repository');

    $form_component = $repository->getFormDisplay('node', 'simplenews_issue')->getComponent('body');
    $this->assertNotNull($form_component);
    $this->assertEquals($widget_type, $form_component['type']);

    foreach (['default', 'email_html', 'email_plain'] as $view_mode) {
      $component = $repository->getViewDisplay('node', 'simplenews_issue', $view_mode)->getComponent('body');
      $this->assertNotNull($component, "Body is displayed in the $view_mode view mode.");
      $this->assertEquals('text_default', $component['type']);
    }
    $teaser = $repository->getViewDisplay('node', 'simplenews_issue', 'teaser')->getComponent('body');
    $this->assertNotNull($teaser);
    $this->assertEquals('text_trimmed', $teaser['type']);
    $this->assertEquals(600, $teaser['settings']['trim_length']);
  }

  /**
   * On a site with a text_long body storage, the field inherits text_long.
   */
  public function testTextLongStorage(): void {
    $this->createBodyStorage('text_long');
    _simplenews_add_body_field();

    $field = FieldConfig::loadByName('node', 'simplenews_issue', 'body');
    $this->assertNotNull($field);
    $this->assertEquals('text_long', $field->getType());
    $this->assertDisplays('text_textarea');
  }

  /**
   * With a text_with_summary storage, the field matches and hides the summary.
   */
  public function testTextWithSummaryStorage(): void {
    $this->createBodyStorage('text_with_summary');
    _simplenews_add_body_field();

    $field = FieldConfig::loadByName('node', 'simplenews_issue', 'body');
    $this->assertNotNull($field);
    $this->assertEquals('text_with_summary', $field->getType());
    $this->assertFalse($field->getSetting('display_summary'));
    $this->assertDisplays('text_textarea_with_summary');
  }

  /**
   * Without any body storage, a text_long storage is created.
   */
  public function testMissingStorage(): void {
    _simplenews_add_body_field();

    $storage = FieldStorageConfig::loadByName('node', 'body');
    $this->assertNotNull($storage);
    $this->assertEquals('text_long', $storage->getType());
    $field = FieldConfig::loadByName('node', 'simplenews_issue', 'body');
    $this->assertNotNull($field);
    $this->assertEquals('text_long', $field->getType());
  }

  /**
   * A body storage repurposed to a non-text type is left alone.
   */
  public function testForeignStorage(): void {
    $this->createBodyStorage('string');
    _simplenews_add_body_field();

    $this->assertNull(FieldConfig::loadByName('node', 'simplenews_issue', 'body'));
  }

  /**
   * Running again does not touch an existing body field.
   */
  public function testIdempotent(): void {
    $this->createBodyStorage('text_long');
    _simplenews_add_body_field();

    // A site builder customizes the field; a rerun must not revert it.
    $field = FieldConfig::loadByName('node', 'simplenews_issue', 'body');
    $field->setLabel('Customized')->save();
    _simplenews_add_body_field();

    $field = FieldConfig::loadByName('node', 'simplenews_issue', 'body');
    $this->assertEquals('Customized', $field->label());
  }

  /**
   * Without the simplenews_issue content type nothing is created.
   */
  public function testMissingContentType(): void {
    NodeType::load('simplenews_issue')->delete();
    $this->createBodyStorage('text_long');
    _simplenews_add_body_field();

    $this->assertNull(FieldConfig::loadByName('node', 'simplenews_issue', 'body'));
  }

}
