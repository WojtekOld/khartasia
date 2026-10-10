<?php

namespace Drupal\term_glossary\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The TermGlossary controller.
 */
class TermGlossaryController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The term_glossary module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $glossaryConfig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configManager = $container->get('config.manager');
    $instance->requestStack = $container->get('request_stack');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Api Search Per Letter.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response.
   */
  public function apiSearchPerLetter($letter) {
    $results = ['message' => $this->t('No results found for @letter', ['@letter' => $letter])];
    $status = 200;
    if (!empty($letter) && preg_match("/^[a-zA-Z]$/", strtoupper($letter))) {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $query = $term_storage->getQuery();
      $vocabularies = $this->getVocabularies();
      $query->condition('name', $letter, 'STARTS_WITH');
      $query->condition('vid', $vocabularies, 'IN');
      $query->condition('status', 1);
      $query->condition('langcode', $this->languageManager()->getCurrentLanguage()->getId());
      $query->accessCheck();
      $entity_ids = $query->execute();
      if (count($entity_ids) !== 0) {
        $terms = $this->filterViewableTerms($term_storage->loadMultiple($entity_ids));
        if (count($terms) !== 0) {
          $results = [];
          $view_mode = $this->getConfig('view_mode');
          $view_builder = $this->entityTypeManager->getViewBuilder('taxonomy_term');
          foreach ($terms as $term) {
            $results[] = $this->buildTermResult($term, $view_mode, $view_builder);
          }
          $this->moduleHandler->invokeAll('term_glossary_alter_results', [
            &$results,
            $terms,
            $letter,
          ]);
        }
      }
    }
    return new JsonResponse($results, $status);
  }

  /**
   * Helper to get term by id.
   */
  public function apiGetTermById($tid) {
    $results = ['message' => $this->t('No results found for @tid', ['@tid' => $tid])];
    $status = 200;
    $term = $this->loadGlossaryTerm($tid);
    if ($term !== NULL) {
      $view_mode = $this->getConfig('view_mode');
      $view_builder = $this->entityTypeManager->getViewBuilder('taxonomy_term');
      $results = $this->buildTermResult($term, $view_mode, $view_builder);
    }
    $this->moduleHandler->invokeAll('term_glossary_alter_result', [
      &$results,
      $term,
      $tid,
    ]);

    return $this->buildCachedJsonResponse($results, $status);
  }

  /**
   * Helper to search per term.
   */
  public function apiSearchPerTerm() {
    $request = $this->requestStack->getCurrentRequest();
    // 200 because js is not checking for error.
    $status = 200;
    $results = ['message' => $this->t('error bad request')];
    if (!empty($request->get('t'))) {
      // @todo make sure this is "safe" here.
      $term = Html::escape(trim($request->get('t')));
      if (!empty($term)) {
        $results = [];
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $query = $term_storage->getQuery();
        $vocabularies = $this->getVocabularies();
        $query->condition('name', $term, 'CONTAINS');
        $query->condition('vid', $vocabularies, 'IN');
        $query->condition('status', 1);
        $query->condition('langcode', $this->languageManager()->getCurrentLanguage()->getId());
        $query->accessCheck();
        $entity_ids = $query->execute();

        if (count($entity_ids) == 0) {
          $query = $term_storage->getQuery();
          $query->condition('name', "%" . $this->database->escapeLike($term) . "%", 'LIKE');
          $query->condition('vid', $vocabularies, 'IN');
          $query->condition('status', 1);
          $query->condition('langcode', $this->languageManager()->getCurrentLanguage()->getId());
          $query->accessCheck();
          $entity_ids = $query->execute();
        }
        $terms = $this->filterViewableTerms($term_storage->loadMultiple($entity_ids));
        if (count($terms) == 0) {
          $results = ['message' => $this->t('No results found for search term "@term"', ['@term' => $term])];
        }
        else {
          $view_mode = $this->getConfig('view_mode');
          $view_builder = $this->entityTypeManager->getViewBuilder('taxonomy_term');
          foreach ($terms as $glossary_term) {
            $results[$glossary_term->id()] = $this->buildTermResult($glossary_term, $view_mode, $view_builder);
            // Perhaps fire hook to include more.
          }
          $this->moduleHandler->invokeAll('term_glossary_alter_results', [
            &$results,
            $terms,
            $term,
          ]);
        }

      }
    }
    return new JsonResponse($results, $status);
  }

  /**
   * Keeps the terms the current user is allowed to view.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms
   *   The terms loaded from the taxonomy term storage.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The viewable terms, in the current content language, keyed by
   *   term id.
   */
  protected function filterViewableTerms(array $terms): array {
    $viewable_terms = [];
    foreach ($terms as $term) {
      $viewable_term = $this->getViewableTranslation($term);
      if ($viewable_term !== NULL) {
        $viewable_terms[$viewable_term->id()] = $viewable_term;
      }
    }
    return $viewable_terms;
  }

  /**
   * Loads a glossary term to serve for the given term id.
   *
   * Only the vocabularies the glossary is configured for are served.
   * The glossary also lists published terms only, whoever is asking,
   * the way TermGlossaryManager does for the terms it highlights in
   * content. The search endpoints get both from their query; this
   * path has no query, so the loaded term is checked here.
   *
   * @param string|int $tid
   *   The term id taken from the request path.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The term to serve, or NULL when it does not exist, is not a
   *   glossary term, is unpublished, or may not be viewed.
   */
  protected function loadGlossaryTerm($tid): ?TermInterface {
    if (!is_numeric($tid)) {
      return NULL;
    }
    $term = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($tid);
    if (!$term instanceof TermInterface) {
      return NULL;
    }
    // A term of another vocabulary is not glossary content.
    if (!in_array($term->bundle(), $this->getConfiguredVocabularies(), TRUE)) {
      return NULL;
    }
    $term = $this->getViewableTranslation($term);
    return ($term !== NULL && $term->isPublished()) ? $term : NULL;
  }

  /**
   * Gets the term translation the current user is allowed to view.
   *
   * The taxonomy term entity query access check is only an extension
   * point, it does not filter unpublished terms on its own, so every
   * term is checked here before it reaches a response.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The loaded term.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The term in the current content language, or NULL when the
   *   current user may not view it.
   */
  protected function getViewableTranslation(TermInterface $term): ?TermInterface {
    $translation = $this->entityRepository->getTranslationFromContext($term);
    return $translation->access('view') ? $translation : NULL;
  }

  /**
   * Function to get the vocab name form config.
   *
   * @return string[]
   *   the vocab name from config.
   */
  private function getVocabularies() {
    $vocabularies = $this->getConfiguredVocabularies();
    if (empty($vocabularies)) {
      throw new \Exception('glossary has not been configured yet');
    }
    return $vocabularies;
  }

  /**
   * Gets the vocabularies the glossary is configured for.
   *
   * @return string[]
   *   The configured vocabulary ids, empty when the glossary has not
   *   been configured yet.
   */
  private function getConfiguredVocabularies(): array {
    $vocabularies_list = $this->getConfig('vocab');
    if (empty($vocabularies_list)) {
      return [];
    }
    return explode(',', $vocabularies_list);
  }

  /**
   * Gets the term_glossary module configuration.
   */
  protected function getGlossaryConfig() {
    if (!isset($this->glossaryConfig)) {
      $config_factory = $this->configManager->getConfigFactory();
      $this->glossaryConfig = $config_factory->get('term_glossary.glossaryconfig');
    }
    return $this->glossaryConfig;
  }

  /**
   * Gets a configuration item from the term_glossary config module.
   */
  protected function getConfig(string $name) {
    return $this->getGlossaryConfig()->get($name);
  }

  /**
   * Builds a term data array for the JSON response.
   */
  protected function buildTermResult(EntityInterface $term, string $view_mode, EntityViewBuilderInterface $view_builder) {
    $result = [
      'tid' => $term->id(),
      'lang' => $term->language()->getId(),
      'name' => $term->getName(),
      'description' => $term->getDescription(),
    ];
    if (empty($view_mode)) {
      $html_output = "<p><strong>" . Html::escape($term->getName()) . "</strong></p>";
      $html_output .= Xss::filter($term->getDescription() ?? '');
    }
    else {
      $build = $view_builder->view($term, $view_mode);
      $html_output = $this->renderer->render($build);
    }
    $result['html'] = $html_output;
    return $result;
  }

  /**
   * Adds default cache headers to the JSON response.
   *
   * The payload depends on what the current user is allowed to view,
   * so only an anonymous response may be cached. An authenticated one
   * is left without cache headers, which Drupal turns into
   * 'Cache-Control: no-cache, private', so that a shared cache such
   * as a reverse proxy or a CDN cannot hand it to another visitor.
   *
   * The response stays a plain JsonResponse on purpose: core replaces
   * a CacheableJsonResponse's Cache-Control whenever the site leaves
   * system.performance cache.page.max_age at its default of 0, which
   * would silently drop this caching altogether.
   */
  protected function buildCachedJsonResponse(mixed $results, int $status) {
    $response = new JsonResponse($results, $status);
    $max_age = $this->getConfig('json_term_cache');
    if (!is_numeric($max_age)) {
      // Default to 1-hour cache if not defined or empty.
      $max_age = 1 * 60 * 60;
    }
    if ($max_age > 0 && $this->currentUser()->isAnonymous()) {
      $response->setPublic();
      $response->setMaxAge($max_age);
      $response->setSharedMaxAge($max_age);
      $expires = new \DateTime();
      $expires->setTimestamp(time() + $max_age);
      $response->setExpires($expires);
    }
    return $response;
  }

}
