<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourcePluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how sources receive and pass along their contexts.
 *
 * @internal
 */
#[CoversClass(SourcePluginBase::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class DefinedContextValuesTest extends SourcePluginsTestBase {

  use UserCreationTrait;

  /**
   * Field contexts of a formatter pass through a source without definitions.
   */
  public function testFieldContextsReachSourcesNestedInSourceWithoutContextDefinitions(): void {
    $node = $this->createTestContentNode('page', ['field_text_1' => ['value' => 'value_text_1']]);
    $contexts = [
      'entity' => EntityContext::fromEntity($node),
      'bundle' => new Context(new ContextDefinition('string'), 'page'),
      'field_name' => new Context(new ContextDefinition('string'), 'field_text_1'),
      'ui_patterns:field:index' => new Context(new ContextDefinition('integer'), 0),
    ];
    $contexts = RequirementsContext::addToContext(['field_granularity:item'], $contexts);
    $configuration = SourcePluginBase::buildConfiguration('slot', [], [
      'source' => [
        'component' => ['component_id' => 'ui_patterns_test:test-component'],
      ],
    ], $contexts);

    $source = $this->sourcePluginManager()->createInstance('component', $configuration);
    self::assertInstanceOf(SourcePluginBase::class, $source);
    $embedded = $source->getPropValue();

    self::assertSame($contexts, $embedded['#source_contexts']);
    // With these contexts, the inner prop is offered the item field source.
    $offered = $this->sourcePluginManager()->getDefinitionsForPropType('string', $embedded['#source_contexts']);
    self::assertArrayHasKey('field_property:node:field_text_1:value', $offered);
    // Without the item requirement, it is not.
    $without_requirement = RequirementsContext::removeFromContext(['field_granularity:item'], $contexts);
    $offered = $this->sourcePluginManager()->getDefinitionsForPropType('string', $without_requirement);
    self::assertArrayNotHasKey('field_property:node:field_text_1:value', $offered);
  }

  /**
   * A context mapping resolves a context from the repository.
   */
  public function testSourceWithContextDefinitionsUsesContextMapping(): void {
    $user = $this->setUpCurrentUser();
    $settings = [
      'source' => [
        'context_mapping' => ['entity' => '@user.current_user_context:current_user'],
      ],
    ];
    $configuration = SourcePluginBase::buildConfiguration('prop_id', [], $settings, []);

    $source = $this->sourcePluginManager()->createInstance('context_foo', $configuration);
    self::assertInstanceOf(SourcePluginBase::class, $source);

    self::assertSame($user->id(), $source->getContextValue('entity')->id());
  }

}
