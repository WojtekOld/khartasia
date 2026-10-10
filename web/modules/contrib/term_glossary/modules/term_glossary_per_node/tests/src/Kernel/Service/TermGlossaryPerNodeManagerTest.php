<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary_per_node\Kernel\Service;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_glossary_per_node\Service\TermGlossaryPerNodeManager;
use Drupal\Tests\term_glossary\Traits\GlossaryTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the TermGlossaryPerNodeManager service.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryPerNodeManager::class)]
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class TermGlossaryPerNodeManagerTest extends KernelTestBase {

  use GlossaryTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'text',
    'field',
    'filter',
    'term_glossary',
    'term_glossary_per_node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installGlossaryTestDependencies();
    $this->installEntitySchema('node');
    $this->installConfig(['node', 'field']);

    // Create a node type.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    $this->config('term_glossary.glossaryconfig')
      ->set('vocab', 'glossary')
      ->set('integration_type', 'link')
      ->save();
  }

  /**
   * Tests that the service is an instance of PerNodeManager.
   *
   * Verifies that when term_glossary_per_node is enabled, the
   * 'term_glossary.manager' service is decorated and resolves
   * to TermGlossaryPerNodeManager instead of the base class.
   */
  public function testServiceOverride(): void {
    $manager = $this->container->get('term_glossary.manager');
    $this->assertInstanceOf(
      TermGlossaryPerNodeManager::class,
      $manager,
    );
  }

  /**
   * Tests vocabulary resolution with per-node override active.
   *
   * Exercises the full getVocabulariesFromFieldPreprocessVariables()
   * code path: per-node active=1, override=1, and node
   * glossary active. Creates a configurable text field with
   * glossary third-party settings enabled in the entity view
   * display (base fields like title are unsuitable because
   * collectRenderDisplay overwrites their third_party_settings
   * with base field definition defaults). Creates a node with
   * term_glossary_vocabulary='tags'. Verifies the per-node
   * vocabulary ('tags') is returned instead of the site-wide
   * vocabulary ('glossary') from term_glossary.glossaryconfig.
   * Without this test, a regression in the per-node vocabulary
   * override logic would go undetected.
   */
  public function testGetVocabulariesNodeActiveWithOverride(): void {
    // Create a second vocabulary for the per-node override.
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    // Add a configurable text field to the article node type.
    // Base fields (like title) have built-in display defaults
    // that collectRenderDisplay overwrites, losing our
    // third_party_settings. Configurable fields don't.
    FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();

    // Set up entity view display with glossary enabled on
    // the configurable field.
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_body', [
      'type' => 'text_default',
      'third_party_settings' => [
        'term_glossary' => [
          'glossary' => TRUE,
          'glossary_vocabulary' => [],
        ],
      ],
    ])->save();

    // Enable per-node with override.
    $node_type = NodeType::load('article');
    $node_type->setThirdPartySetting(
      'term_glossary_per_node', 'active', 1,
    );
    $node_type->setThirdPartySetting(
      'term_glossary_per_node', 'override', 1,
    );
    $node_type->setThirdPartySetting(
      'term_glossary_per_node', 'override_default', 1,
    );
    $node_type->save();

    // Create a node with glossary active and custom vocab.
    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    $node = $node_storage->create([
      'type' => 'article',
      'title' => 'Test',
      'uid' => 0,
      'term_glossary_active' => 1,
      'term_glossary_vocabulary' => 'tags',
    ]);
    $node->save();

    $manager = $this->container->get('term_glossary.manager');

    // Build the $variables structure as preprocessField does.
    $variables = [
      'element' => [
        '#object' => $node,
        '#view_mode' => 'default',
        '#field_name' => 'field_body',
      ],
    ];

    $vocabularies = $manager
      ->getVocabulariesFromFieldPreprocessVariables($variables);

    // The node's per-node vocabulary overrides global config.
    $this->assertIsArray($vocabularies);
    $this->assertEquals(['tags'], $vocabularies);
  }

  /**
   * Creates mock formatter and field definition for testing.
   *
   * @param string $entity_type_id
   *   The target entity type ID.
   * @param string $bundle
   *   The target bundle.
   * @param string $field_name
   *   The field name (optional, omit for tests that don't
   *   need it).
   *
   * @return array
   *   Array with 'plugin' and 'field_definition' mocks.
   */
  protected function createFormatterMocks(
    string $entity_type_id,
    string $bundle,
    string $field_name = '',
  ): array {
    $plugin = $this->createMock(
      '\Drupal\Core\Field\FormatterInterface',
    );
    $plugin->method('getThirdPartySetting')
      ->willReturn(NULL);

    $field_definition = $this->createMock(
      '\Drupal\Core\Field\FieldDefinitionInterface',
    );
    $field_definition->method('getTargetEntityTypeId')
      ->willReturn($entity_type_id);
    $field_definition->method('getTargetBundle')
      ->willReturn($bundle);
    if ($field_name !== '') {
      $field_definition->method('getName')
        ->willReturn($field_name);
    }

    return [
      'plugin' => $plugin,
      'field_definition' => $field_definition,
    ];
  }

  /**
   * Tests getFieldFormatterThirdPartySettingsElements when inactive.
   *
   * Sets per-node glossary active=0 on the article node type.
   * Calls getFieldFormatterThirdPartySettingsElements() with
   * a mock formatter targeting node/article. Verifies an
   * empty array is returned — the form elements are hidden
   * when per-node is inactive for that bundle.
   */
  public function testGetFieldFormatterThirdPartySettingsInactive(): void {
    // Node type with glossary NOT active.
    $node_type = NodeType::load('article');
    $node_type->setThirdPartySetting(
      'term_glossary_per_node', 'active', 0,
    );
    $node_type->save();

    $manager = $this->container->get('term_glossary.manager');
    $mocks = $this->createFormatterMocks('node', 'article');

    // When per-node glossary is inactive for this bundle,
    // the method should return an empty array — no glossary
    // form elements are shown on the field formatter config.
    $form_elements = $manager->getFieldFormatterThirdPartySettingsElements(
      $mocks['plugin'], $mocks['field_definition'],
    );
    $this->assertEmpty($form_elements);
  }

  /**
   * Tests getFieldFormatterThirdPartySettingsElements when active.
   *
   * Sets per-node glossary active=1 on the article node type.
   * Calls getFieldFormatterThirdPartySettingsElements() with
   * a mock formatter targeting node/article/body. Verifies
   * the result contains both 'glossary' and
   * 'glossary_vocabulary' form elements.
   */
  public function testGetFieldFormatterThirdPartySettingsActive(): void {
    $node_type = NodeType::load('article');
    $node_type->setThirdPartySetting(
      'term_glossary_per_node', 'active', 1,
    );
    $node_type->save();

    $manager = $this->container->get('term_glossary.manager');
    $mocks = $this->createFormatterMocks(
      'node', 'article', 'body',
    );

    $form_elements = $manager->getFieldFormatterThirdPartySettingsElements(
      $mocks['plugin'], $mocks['field_definition'],
    );
    $this->assertArrayHasKey('glossary', $form_elements);
    $this->assertArrayHasKey('glossary_vocabulary', $form_elements);
  }

  /**
   * Tests non-node entity gets parent behavior.
   *
   * Calls getFieldFormatterThirdPartySettingsElements() with
   * a field targeting taxonomy_term/glossary. Since the entity
   * type is not 'node', the per-node override is skipped and
   * the parent (base manager) behavior applies, returning
   * the standard 'glossary' form element.
   */
  public function testGetFieldFormatterThirdPartySettingsNonNode(): void {
    $manager = $this->container->get('term_glossary.manager');
    $mocks = $this->createFormatterMocks(
      'taxonomy_term', 'glossary', 'description',
    );

    $form_elements = $manager->getFieldFormatterThirdPartySettingsElements(
      $mocks['plugin'], $mocks['field_definition'],
    );
    // Non-node entities get parent behavior — elements returned.
    $this->assertArrayHasKey('glossary', $form_elements);
  }

}
