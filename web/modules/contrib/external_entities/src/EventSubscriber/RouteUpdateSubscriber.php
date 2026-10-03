<?php

namespace Drupal\external_entities\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Check conflicts between existing routes and external entities routes.
 */
class RouteUpdateSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs a new RouteUpdateSubscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(
    MessengerInterface $messenger,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    RouteProviderInterface $route_provider,
  ) {
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeProvider = $route_provider;
  }

  /**
   * Check for conflicting routes with external entity ones.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The event.
   */
  public function onFinishedRouteEvent(Event $event) {
    $route_collection = $this->routeProvider->getAllRoutes();
    $external_entity_base_paths = $this->getExternalEntityBasePaths();

    foreach ($route_collection as $route_name => $route) {
      $route_path = trim($route->getPath(), '/');
      if (!empty($external_entity_base_paths[$route_path])
          && ($external_entity_base_paths[$route_path] !== $route_name)
      ) {
        $controller = $route->getDefault('_controller');
        $module = 'unknown';
        if (is_string($controller) && strpos($controller, '::') !== FALSE) {
          [$class] = explode('::', $controller, 2);
          if (class_exists($class)) {
            $reflection = new \ReflectionClass($class);
            $namespace = $reflection->getNamespaceName();
            $module = explode('\\', $namespace)[1] ?? 'unknown';
          }
        }
        $message = $this->t(
          'Route conflict detected: The path "/@path" is already used by an ExternalEntityType. The new route "@route_name" (module: @module) may mask the corresponding external entities.',
          [
            '@path' => $route_path,
            '@route_name' => $route_name,
            '@module' => $module,
          ]
        );
        $this->messenger->addWarning($message);
        $this->logger->warning($message);
      }
    }
  }

  /**
   * Get all base paths from ExternalEntityType entities.
   *
   * @return array
   *   An array of base paths.
   */
  protected function getExternalEntityBasePaths() {
    $base_paths = [];
    $external_entity_types = $this->entityTypeManager
      ->getStorage('external_entity_type')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($external_entity_types)) {
      $entities = $this->entityTypeManager
        ->getStorage('external_entity_type')
        ->loadMultiple($external_entity_types);
      foreach ($entities as $entity) {
        $base_path = $entity->getBasePath();
        if (!empty($base_path)) {
          $base_paths[$base_path] = 'entity.' . $entity->id() . '.collection';
        }
      }
    }

    return $base_paths;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RoutingEvents::FINISHED => ['onFinishedRouteEvent'],
    ];
  }

}
