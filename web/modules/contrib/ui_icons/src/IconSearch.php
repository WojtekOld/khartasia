<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle an Icon search.
 *
 * Main entrypoint to search and filter icons based on name or pack.
 */
class IconSearch implements ContainerInjectionInterface {

  // Minimum trigger for search, must match with js/icon.autocomplete.js
  // setting.
  public const SEARCH_MIN_LENGTH = 2;
  // Default autocomplete result length. Multiple of 12 to match grid format.
  // @see css/icon.autocomplete.css
  public const SEARCH_RESULT = 24;
  // Maximum autocomplete result length.
  public const SEARCH_RESULT_MAX = 132;
  public const ICON_PREVIEW_SIZE = 32;

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly RendererInterface $renderer,
    private CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('renderer'),
      $container->get('cache.default'),
    );
  }

  /**
   * Find an icon based on search string.
   *
   * @param string $query
   *   The query to search for.
   * @param array $allowed_icon_pack
   *   Restrict to an icon pack list.
   * @param int $max_result
   *   Maximum result to return.
   * @param callable|null $result_callback
   *   A callable to process each result.
   *
   * @return array
   *   The icons matching the search, loaded and formatted if a callback is set.
   */
  public function search(
    string $query,
    array $allowed_icon_pack = [],
    int $max_result = self::SEARCH_RESULT,
    ?callable $result_callback = NULL,
  ): array {
    if (empty($query) || mb_strlen($query) < self::SEARCH_MIN_LENGTH) {
      return [];
    }

    $cache_key = $query . implode('', $allowed_icon_pack);
    if (NULL !== $result_callback && is_array($result_callback)) {
      $cache_key .= implode('', $result_callback);
    }
    $cache_key = hash('xxh3', $cache_key);

    $cache_data = [];

    if ($cache = $this->cache->get('icon_search')) {
      $cache_data = $cache->data;
      if (isset($cache_data[$cache_key])) {
        return $cache_data[$cache_key];
      }
    }

    $result = $this->doSearch($query, $allowed_icon_pack, $max_result);
    if (empty($result)) {
      return [];
    }

    if ($result_callback) {
      $result_with_callback = [];
      foreach ($result as $icon_full_id) {
        $result_with_callback[] = $this->createResultEntry($icon_full_id, $result_callback);
      }
      $result = $result_with_callback;
    }

    $cache_data[$cache_key] = $result;
    $this->cache->set(
      'icon_search',
      $cache_data,
      CacheBackendInterface::CACHE_PERMANENT,
      ['icon_pack_plugin', 'icon_pack_collector']
    );

    return $result;
  }

  /**
   * Do a search and return icon id as result.
   *
   * The search try to be fuzzy on words with a priority:
   *  - Words in order
   *  - Words in any order
   *  - Any parts of words.
   *
   * @param string $query
   *   The query to search for.
   * @param array $allowed_icon_pack
   *   Restrict to an icon pack list.
   * @param int $max_result
   *   Maximum result to return.
   *
   * @return array
   *   The icons matching the search.
   */
  private function doSearch(string $query, array $allowed_icon_pack, int $max_result): array {
    $icons = $this->pluginManagerIconPack->getIcons($allowed_icon_pack);
    if (empty($icons)) {
      return [];
    }

    // If the search is an exact icon full id let return faster.
    if (isset($icons[$query])) {
      return [$query];
    }

    // Prepare multi words search by removing unwanted characters.
    $search_terms = preg_split('/\s+/', trim(preg_replace('/[^ \w-]/', ' ', mb_strtolower($query))));
    if (empty($search_terms)) {
      return [];
    }

    $patterns = $this->buildSearchPatterns($search_terms);

    // Search with a priority.
    $matches_priority = array_fill_keys(array_keys($patterns), []);
    foreach (array_keys($icons) as $icon_full_id) {
      $icon_data = IconDefinition::getIconDataFromId($icon_full_id);

      // Priority search is on id and then pack for order.
      $icon_search = $icon_data['icon_id'] . ' ' . $icon_data['pack_id'];

      $priority = $this->matchPriority($icon_search, $patterns, $matches_priority, $max_result);
      if (NULL !== $priority) {
        $matches_priority[$priority][$icon_full_id] = $icon_full_id;
      }
    }

    return array_slice(array_merge(...array_values($matches_priority)), 0, $max_result);
  }

  /**
   * Builds the search patterns, ordered from the most to the least specific.
   *
   * @param array $search_terms
   *   The sanitized, lowercased search words.
   *
   * @return array
   *   PCRE patterns keyed by priority: exact order, any order, any part, any
   *   part in any order.
   */
  private function buildSearchPatterns(array $search_terms): array {
    $any_order_pattern = '/\b(' . implode('|', $search_terms) . ')\b.*\b(' . implode('|', $search_terms) . ')\b/i';
    $any_order_pattern = preg_replace_callback('/\((.*?)\)/', function ($match) {
      return '(' . implode('|', array_map(function ($word) {
        return preg_quote($word, '/');
      }, explode('|', $match[1]))) . ')';
    }, $any_order_pattern);

    return [
      '/\b' . implode('\b.*\b', $search_terms) . '\b/i',
      $any_order_pattern,
      '/' . implode('.*', array_map('preg_quote', $search_terms)) . '/i',
      '/\b(?:' . implode('|', array_map(function ($word) {
        return preg_quote($word, '/') . '\b.*\b|\b.*\b' . preg_quote($word, '/');
      }, $search_terms)) . ')/i',
    ];
  }

  /**
   * Finds the priority bucket an icon belongs to, if any.
   *
   * A pattern is only tried while every higher priority bucket still has room,
   * so once the best matches fill up the search stops widening.
   *
   * @param string $icon_search
   *   The haystack, `icon_id pack_id`.
   * @param array $patterns
   *   Patterns by priority, from ::buildSearchPatterns().
   * @param array $matches
   *   Matches collected so far, keyed by priority.
   * @param int $max_result
   *   Maximum result per priority bucket.
   *
   * @return int|null
   *   The priority to record the icon under, or NULL to skip it.
   */
  private function matchPriority(string $icon_search, array $patterns, array $matches, int $max_result): ?int {
    foreach ($patterns as $priority => $pattern) {
      if ($priority > 0 && count($matches[$priority - 1]) >= $max_result) {
        return NULL;
      }

      if (preg_match($pattern, $icon_search)) {
        return count($matches[$priority]) < $max_result ? $priority : NULL;
      }
    }

    return NULL;
  }

  /**
   * Create icon result.
   *
   * @param string $icon_full_id
   *   The icon full id.
   * @param callable $callback
   *   A callable to process the result.
   *
   * @return string|array|null
   *   The icon result passed through the callback.
   */
  private function createResultEntry(string $icon_full_id, callable $callback): mixed {
    $icon = $this->pluginManagerIconPack->getIcon($icon_full_id);
    if (!$icon instanceof IconDefinitionInterface) {
      return NULL;
    }

    $icon_renderable = IconPreview::getPreview($icon, ['size' => self::ICON_PREVIEW_SIZE]);
    $rendered = $this->renderer->renderInIsolation($icon_renderable);

    return call_user_func($callback, $icon, $rendered);
  }

}
