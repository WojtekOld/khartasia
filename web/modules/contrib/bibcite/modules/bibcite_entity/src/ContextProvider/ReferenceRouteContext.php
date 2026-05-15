<?php

namespace Drupal\bibcite_entity\ContextProvider;

use Drupal\bibcite_entity\Entity\Reference;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sets the current "bibcite_reference" as a context on reference routes.
 */
class ReferenceRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new ReferenceRouteContext instance.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];
    $context_definition = EntityContextDefinition::create('bibcite_reference')->setRequired(FALSE);
    $value = NULL;
    if ($route_object = $this->routeMatch->getRouteObject()) {
      $route_parameters = $route_object->getOption('parameters');

      if (isset($route_parameters['bibcite_reference']) && $reference = $this->routeMatch->getParameter('bibcite_reference')) {
        $value = $reference;
      }
      elseif ($this->routeMatch->getRouteName() === 'entity.bibcite_reference.add_form') {
        $type = $this->routeMatch->getParameter('bibcite_reference_type');
        $value = Reference::create(['type' => $type->id()]);
      }
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);

    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);
    $result['bibcite_reference'] = $context;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = EntityContext::fromEntityTypeId('bibcite_reference', $this->t('Reference from URL'));
    return ['bibcite_reference' => $context];
  }

}
