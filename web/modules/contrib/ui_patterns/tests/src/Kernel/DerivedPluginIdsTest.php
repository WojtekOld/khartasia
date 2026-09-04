<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\ui_patterns\DerivableContextPluginManager;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldPropertySource;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\SourceTags;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pins the derived plugin IDs and their definition contract.
 *
 * Derived IDs are stored in site config and in content (ui_patterns_field),
 * so they must never change. A refactor that alters an ID, a context, a tag
 * or a metadata value must fail here.
 *
 * To bless an intentional change, run once with
 * UPDATE_DERIVED_PLUGIN_IDS_FIXTURE=1 and review the fixture diff.
 *
 * @internal
 */
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class DerivedPluginIdsTest extends SourcePluginsTestBase {

  /**
   * Where the recorded derivative IDs live.
   */
  private const FIXTURE_PATH = __DIR__ . '/../../fixtures/ExpectedDerivativeIds.yml';

  /**
   * Only IDs involving these fields are compared against the fixture.
   *
   * Keeps the lists stable when core adds field types.
   */
  private const SCOPED_FIELDS = [
    'field_shared',
    'field_reference_single',
    'field_reference_multi',
  ];

  /**
   * The source plugin manager.
   */
  private SourcePluginManager $sourceManager;

  /**
   * The derivable context plugin manager.
   */
  private DerivableContextPluginManager $derivableContextManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Second bundle: entity reference target, and separates bundle-scoped
    // from no-bundle IDs.
    NodeType::create(['type' => 'other', 'name' => 'Other'])->save();

    // A plain field on both bundles.
    $this->createFieldWithSettings('field_shared', 'string', 1, [], ['page', 'other']);
    // An entity reference with one target bundle.
    $this->createFieldWithSettings('field_reference_single', 'entity_reference', 1, [
      'handler' => 'default:node',
      'handler_settings' => ['target_bundles' => ['page' => 'page']],
    ], ['page']);
    // Two target bundles: produces the trailing-empty "- All -" ID variant.
    $this->createFieldWithSettings('field_reference_multi', 'entity_reference', -1, [
      'handler' => 'default:node',
      'handler_settings' => ['target_bundles' => ['page' => 'page', 'other' => 'other']],
    ], ['page']);

    $this->sourceManager = $this->container->get(SourcePluginManager::class);
    $this->derivableContextManager = $this->container->get(DerivableContextPluginManager::class);
    $this->sourceManager->clearCachedDefinitions();
    $this->derivableContextManager->clearCachedDefinitions();
  }

  /**
   * Creates a field storage and its instances with explicit settings.
   *
   * @param string $field_name
   *   The field name.
   * @param string $field_type
   *   The field type plugin ID.
   * @param int $cardinality
   *   The storage cardinality.
   * @param array $instance_settings
   *   The field instance settings (e.g. handler settings).
   * @param array $bundles
   *   The node bundles to instantiate the field on.
   */
  private function createFieldWithSettings(string $field_name, string $field_type, int $cardinality, array $instance_settings, array $bundles): void {
    $storage_settings = ($field_type === 'entity_reference') ? ['target_type' => 'node'] : [];
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $field_type,
      'settings' => $storage_settings,
      'cardinality' => $cardinality,
    ])->save();
    foreach ($bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $field_name,
        'settings' => $instance_settings,
      ])->save();
    }
  }

  /**
   * Collects the actual derivative IDs, keyed by fixture family.
   *
   * @return array<string, string[]>
   *   Sorted ID lists, keyed by family name.
   */
  private function collectActualIds(): array {
    $source_ids = \array_keys($this->sourceManager->getDefinitions());
    $derivable_context_ids = \array_keys($this->derivableContextManager->getDefinitions());
    $families = [
      'field_property' => [$source_ids, 'field_property:'],
      'field_formatter' => [$source_ids, 'field_formatter:'],
      'entity:field_property' => [$source_ids, 'entity:field_property:'],
      'field' => [$derivable_context_ids, 'field:'],
      'entity_reference' => [$derivable_context_ids, 'entity_reference:'],
    ];
    $actual = [];
    foreach ($families as $family => [$ids, $prefix]) {
      $matches = \array_filter($ids, function (string $id) use ($prefix): bool {
        return \str_starts_with($id, $prefix) && $this->isScopedId($id);
      });
      \sort($matches);
      $actual[$family] = $matches;
    }
    return $actual;
  }

  /**
   * Whether an ID involves one of the scoped fields.
   */
  private function isScopedId(string $id): bool {
    foreach (\explode(':', $id) as $segment) {
      if (\in_array($segment, self::SCOPED_FIELDS, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The exact derived ID lists match the recorded fixture.
   */
  public function testDerivedIdsMatchFixture(): void {
    $actual = $this->collectActualIds();
    if (\getenv('UPDATE_DERIVED_PLUGIN_IDS_FIXTURE')) {
      \file_put_contents(self::FIXTURE_PATH, Yaml::encode(['exact' => $actual]));
      self::markTestIncomplete('Fixture regenerated at ' . self::FIXTURE_PATH . '. Review the diff and re-run without UPDATE_DERIVED_PLUGIN_IDS_FIXTURE.');
    }
    self::assertFileExists(self::FIXTURE_PATH, 'Run once with UPDATE_DERIVED_PLUGIN_IDS_FIXTURE=1 to record the fixture.');
    $expected = Yaml::decode((string) \file_get_contents(self::FIXTURE_PATH));
    foreach ($expected['exact'] as $family => $expected_ids) {
      self::assertSame($expected_ids, $actual[$family] ?? [], \sprintf('Derived IDs changed for the "%s" family. If intentional, regenerate the fixture and mention the BC impact in the change record.', $family));
    }
    self::assertSame(\array_keys($expected['exact']), \array_keys($actual), 'Deriver families changed.');
  }

  /**
   * Spot checks on fields this test does not control.
   *
   * Node base fields and the per-field-type test fields vary with core:
   * membership checks, not exact lists.
   */
  public function testKnownDerivedIdsExist(): void {
    $source_ids = \array_keys($this->sourceManager->getDefinitions());
    $derivable_context_ids = \array_keys($this->derivableContextManager->getDefinitions());
    $expected_source_ids = [
      'field_property:node:title:value',
      'field_formatter:node:page:title',
      'field_formatter:node::title',
      'entity:field_property:node:uid:target_id',
      // One field created by TestContentCreationTrait for each field type.
      'field_property:node:field_text_1:value',
    ];
    foreach ($expected_source_ids as $id) {
      self::assertContains($id, $source_ids, \sprintf('Missing source derivative "%s".', $id));
    }
    $expected_derivable_context_ids = [
      'field:node:page:title',
      'field:node::title',
      'entity_reference:node:page:uid:user:user',
    ];
    foreach ($expected_derivable_context_ids as $id) {
      self::assertContains($id, $derivable_context_ids, \sprintf('Missing derivable context derivative "%s".', $id));
    }
  }

  /**
   * Definition contract of one representative derivative per family.
   */
  public function testDefinitionContracts(): void {
    // field_property: property-level source derivative.
    $definition = $this->sourceManager->getDefinition('field_property:node:field_shared:value');
    self::assertSame(FieldPropertySource::class, $definition['class']);
    self::assertSame([SourceTags::Field->value], $definition['tags']);
    self::assertContains('field_granularity:item', $definition['context_requirements']);
    $context_definitions = $definition['context_definitions'];
    self::assertInstanceOf(EntityContextDefinition::class, $context_definitions['entity']);
    self::assertSame('field_shared', $context_definitions['field_name']->getDefaultValue());
    $field_name_choices = $context_definitions['field_name']->getConstraints()['AllowedValues']['choices'];
    self::assertSame(['field_shared'], $field_name_choices);
    $bundle_choices = $context_definitions['bundle']->getConstraints()['AllowedValues']['choices'];
    self::assertContains('', $bundle_choices, 'Property derivatives accept the no-bundle case.');
    self::assertContains('page', $bundle_choices);

    // field_formatter: bundle-level slot derivative.
    $definition = $this->sourceManager->getDefinition('field_formatter:node:page:field_shared');
    self::assertSame(['slot'], $definition['prop_types']);
    self::assertSame(['page'], $definition['context_definitions']['bundle']->getConstraints()['AllowedValues']['choices']);
    self::assertSame([SourceTags::Field->value], $definition['tags']);

    // field: derivable context loses the field_name context.
    $definition = $this->derivableContextManager->getDefinition('field:node:page:field_shared');
    self::assertArrayNotHasKey('field_name', $definition['context_definitions']);
    self::assertSame([SourceTags::Field->value], $definition['tags']);

    // entity_reference: tag surgery replaces "field" with "entity_referenced".
    $definition = $this->derivableContextManager->getDefinition('entity_reference:node:page:field_reference_multi:node:other');
    self::assertContains(SourceTags::EntityReferenced->value, $definition['tags']);
    self::assertNotContains(SourceTags::Field->value, $definition['tags']);

    // entity:field_property: context switcher on the reference property.
    $definition = $this->sourceManager->getDefinition('entity:field_property:node:field_reference_single:target_id');
    self::assertContains(SourceTags::ContextSwitcher->value, $definition['tags']);
    self::assertContains('field_granularity:item', $definition['context_requirements']);
  }

  /**
   * Metadata contract: the keys the runtime reads, with their values.
   *
   * Raw string keys on purpose: they pin the stored key names, so renaming
   * a SourceMetadataKey case value fails here. Array access keeps the test
   * working should metadata become an \ArrayAccess object.
   */
  public function testMetadataContract(): void {
    // Property-level source derivative (storage branch).
    $metadata = $this->sourceManager->getDefinition('field_property:node:field_shared:value')['metadata'];
    self::assertSame('field_shared', $metadata['field_name']);
    self::assertSame('value', $metadata['property']);
    self::assertSame('field', $metadata['provider'], 'Metadata provider is the field storage provider.');
    self::assertSame('string', $metadata['field']['type']);
    self::assertSame(1, $metadata['field']['cardinality']);

    // Bundle-level source derivative.
    $metadata = $this->sourceManager->getDefinition('field_formatter:node:page:field_reference_multi')['metadata'];
    self::assertSame('field_reference_multi', $metadata['field_name']);
    self::assertSame(-1, $metadata['field']['cardinality']);

    // Storage-level derivable context: cardinality drives the
    // field_granularity:item requirement in EntityFieldDerivableContext.
    $metadata = $this->derivableContextManager->getDefinition('field:node::field_reference_single')['metadata'];
    self::assertSame('entity_reference', $metadata['field']['type']);
    self::assertSame(1, $metadata['field']['cardinality']);
  }

  /**
   * A "field:" derivable context maps to a "field_formatter:" source.
   *
   * EntityFieldSource::getChoiceSettings() rebuilds the field_formatter ID
   * from the field ID segments, so the pair must exist together.
   */
  public function testFieldToFieldFormatterIdContract(): void {
    $derivable_context_ids = \array_keys($this->derivableContextManager->getDefinitions());
    $checked = 0;
    foreach ($derivable_context_ids as $id) {
      if (!\str_starts_with($id, 'field:') || !$this->isScopedId($id)) {
        continue;
      }
      $formatter_id = 'field_formatter' . \substr($id, \strlen('field'));
      self::assertTrue($this->sourceManager->hasDefinition($formatter_id), \sprintf('"%s" has no "%s" counterpart.', $id, $formatter_id));
      ++$checked;
    }
    self::assertGreaterThan(0, $checked);
  }

  /**
   * The positional parsing of entity_reference IDs keeps its meaning.
   *
   * EntityReferencedDerivableContext::parsePluginId() decodes its plugin ID
   * by position: reordering segments breaks entity loading.
   */
  public function testEntityReferencedContextIdParsing(): void {
    $plugin = $this->derivableContextManager->createInstance('entity_reference:node:page:field_reference_multi:node:other', []);
    $method = new \ReflectionMethod($plugin, 'parsePluginId');
    $metadata = $method->invoke($plugin);
    self::assertSame('node', $metadata['entity_type_id'], 'Target entity type ID.');
    self::assertSame('other', $metadata['bundle'], 'Target bundle.');
    self::assertSame('field_reference_multi', $metadata['field_name'], 'Referencing field.');
    self::assertSame('node', $metadata['parent_entity_type_id'], 'Host entity type ID.');
  }

  /**
   * A source_id stored in config resolves to a working plugin.
   *
   * The round-trip a site does on every render: a renamed derivative throws
   * or, worse, silently renders nothing.
   */
  public function testStoredSourceIdResolves(): void {
    $node = $this->createTestContentNode('page', ['field_shared' => ['value' => 'stored value']]);
    $contexts = [
      'entity' => EntityContext::fromEntity($node),
      'bundle' => new Context(new ContextDefinition('any'), 'page'),
      'field_name' => new Context(new ContextDefinition('any'), 'field_shared'),
    ];
    $source = $this->sourceManager->getSource('prop', [], ['source_id' => 'field_property:node:field_shared:value'], $contexts);
    self::assertInstanceOf(FieldPropertySource::class, $source);
    self::assertSame('stored value', $source->getPropValue());
  }

}
