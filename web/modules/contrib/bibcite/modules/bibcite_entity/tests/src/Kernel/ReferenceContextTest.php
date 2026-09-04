<?php

namespace Drupal\Tests\bibcite_entity\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\bibcite_entity\ContextProvider\ReferenceRouteContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\bibcite_entity\Traits\EntityCreationTrait;

/**
 * @coversDefaultClass \Drupal\bibcite_entity\ContextProvider\ReferenceRouteContext
 */
#[RunTestsInSeparateProcesses]
#[Group('bibcite')]
class ReferenceContextTest extends KernelTestBase {

  use EntityCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bibcite',
    'bibcite_entity',
    'serialization',
    'user',
    'system',
    'filter',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('bibcite_contributor');
    $this->installEntitySchema('bibcite_keyword');
    $this->installEntitySchema('bibcite_reference');
    $this->installConfig('bibcite_entity');
  }

  /**
   * @covers ::getAvailableContexts
   */
  public function testGetAvailableContexts() {
    $context_repository = $this->container->get('context.repository');

    // Test bibcite_entity.bibcite_reference_route_context:bibcite_reference
    // exists.
    $contexts = $context_repository->getAvailableContexts();
    $this->assertArrayHasKey('@bibcite_entity.bibcite_reference_route_context:bibcite_reference', $contexts);
    $this->assertSame('entity:bibcite_reference', $contexts['@bibcite_entity.bibcite_reference_route_context:bibcite_reference']->getContextDefinition()
      ->getDataType());
  }

  /**
   * @covers ::getRuntimeContexts
   */
  public function testGetRuntimeContexts() {
    // Create reference entity.
    $reference = $this->createReference();

    // Create RouteMatch from bibcite reference entity.
    $url = $reference->toUrl();
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName($url->getRouteName());
    $route_match = new RouteMatch($url->getRouteName(), $route, [
      'bibcite_reference' => $reference,
    ]);

    // Initiate ReferenceRouteContext with RouteMatch.
    $provider = new ReferenceRouteContext($route_match);

    $runtime_contexts = $provider->getRuntimeContexts([]);
    $this->assertArrayHasKey('bibcite_reference', $runtime_contexts);
    $this->assertTrue($runtime_contexts['bibcite_reference']->hasContextValue());
  }

}
