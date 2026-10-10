<?php

namespace Drupal\term_glossary\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\term_glossary\TermGlossaryHandlerInterface;
use Drupal\term_glossary\TermGlossaryHandlerPluginManager;

/**
 * The TermGlossary manager service.
 */
class TermGlossaryManager implements TermGlossaryManagerInterface {

  use StringTranslationTrait;

  const MATCH_DEFAULT = -1;
  const MATCH_DISABLE = 0;
  const MATCH_ENFORCE = 1;

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The current glossary handler.
   *
   * @var null|\Drupal\term_glossary\TermGlossaryHandlerInterface
   */
  protected ?TermGlossaryHandlerInterface $handler;

  /**
   * The cache id.
   *
   * @var string
   */
  protected string $cacheId = 'glossary_terms_array';

  /**
   * The seen terms.
   *
   * @var array
   */
  protected array $seenTerms;

  public function __construct(
    protected ConfigManagerInterface $configManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CacheBackendInterface $cache,
    protected LanguageManagerInterface $languageManager,
    protected RendererInterface $renderer,
    protected ModuleHandlerInterface $moduleHandler,
    protected LoggerChannelInterface $logger,
    protected TermGlossaryHandlerPluginManager $handlerManager,
    protected EntityRepositoryInterface $entityRepository,
    protected CacheContextsManager $cacheContextsManager,
  ) {
    $this->config = $configManager->getConfigFactory()->get('term_glossary.glossaryconfig');
    $integration_type = $this->config->get('integration_type');
    if (empty($integration_type)) {
      $this->handler = NULL;
      $this->logger->warning($this->t('No integration type defined. Check module configuration.'));
    }
    else {
      try {
        $this->handler = $this->handlerManager->createInstance($integration_type);
      }
      catch (\Exception $e) {
        $this->handler = NULL;
        $this->logger->error($e->getMessage());
      }
    }
    $this->seenTerms = &drupal_static('term_glossary_seen_terms', []);
  }

  /**
   * {@inheritDoc}
   */
  public function getHandler() {
    return $this->handler;
  }

  /**
   * Escapes special characters for use in a regular expression character class.
   *
   * @param string $string
   *   The input string to escape.
   *
   * @return string
   *   The escaped string safe for use in a regex character class.
   */
  private static function escapeCharClass($string) {
    return str_replace(
      ['\\', '/', '-', ']', '[', '^'],
      ['\\\\', '\/', '\-', '\]', '\[', '\^'],
      $string
    );
  }

  /**
   * Folds the case of a string the way a case-insensitive regex does.
   *
   * The "u" + "i" pattern flags compare characters by Unicode simple case
   * folding, not by lowercase: the micro sign "µ" matches the Greek "μ" and
   * "ς" matches "σ", while mb_strtolower() keeps them apart. A substring
   * check on folded strings therefore never rejects text the regex matches.
   *
   * @param string $text
   *   The text to fold.
   *
   * @return string
   *   The case-folded text.
   */
  private static function foldCase(string $text): string {
    return mb_convert_case($text, MB_CASE_FOLD_SIMPLE, 'UTF-8');
  }

  /**
   * Builds the regexp pattern to match a term.
   */
  protected function buildTermPattern(
    string $name,
    array $term_array,
    bool $global_full_word,
    bool $global_case_sensitive,
    string $global_boundary_exceptions = '',
  ) {
    $term_full_word = $term_array['full_word'] ?? self::MATCH_DEFAULT;
    $term_case_sensitive = $term_array['case_sensitive'] ?? self::MATCH_DEFAULT;

    $full_word = ($term_full_word === self::MATCH_ENFORCE)
      || ($global_full_word && ($term_full_word !== self::MATCH_DISABLE));
    $case_sensitive = ($term_case_sensitive === self::MATCH_ENFORCE)
      || ($global_case_sensitive && ($term_case_sensitive !== self::MATCH_DISABLE));

    $quoted_name = preg_quote($name, '/');
    if ($full_word) {
      $term_boundary_exceptions = $term_array['boundary_exceptions'] ?? '';
      $boundary_exceptions = $term_boundary_exceptions ?: $global_boundary_exceptions;
      if (empty($boundary_exceptions)) {
        // Use \b to match word boundaries.
        $pattern = "/\b($quoted_name)\b/";
      }
      else {
        // Use negative lookbehind and lookahead to match word boundaries.
        // It allows avoiding matching punctuation characters like -:;.
        $escaped_boundary_exceptions = self::escapeCharClass($boundary_exceptions);
        $boundary = "(?:\p{L}|\p{N}|[" . $escaped_boundary_exceptions . "])";
        $pattern = "/(?<!$boundary)($quoted_name)(?!$boundary)/";
      }
    }
    else {
      $pattern = "/($quoted_name)/";
    }
    // Treat pattern and subject strings as UTF-8.
    $pattern .= 'u';
    if (!$case_sensitive) {
      $pattern .= 'i';
    }

    return $pattern;
  }

  /**
   * Executes the regexp replacement.
   */
  protected function doMatchReplace(&$ctx) {
    return preg_replace_callback(
      $ctx->pattern,
      function ($matches) use (&$ctx) {
        $ctx->tags[$ctx->tagIndex] = [$ctx->termArray, $matches[1]];
        $placeholder = '{{' . $ctx->tagIndex . '}}';
        $ctx->tagIndex += 1;
        $this->setCurrentMatchTermAsSeen($ctx);
        return $placeholder;
      },
      $ctx->currentText,
      $ctx->isSingleMatch ? 1 : -1,
    );
  }

  /**
   * Generate a cache key based on the vocabulary name and the given language.
   *
   * @param string $vocabulary
   *   Glossary vocabulary id.
   * @param string $langcode
   *   Language code.
   *
   * @return string
   *   The generated cache key.
   */
  protected function getCacheId(string $vocabulary, string $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED): string {
    $context_tokens = ['user.roles'];
    $context_keys = $this->cacheContextsManager->convertTokensToKeys($context_tokens)->getKeys();
    return $this->cacheId . '-' . $vocabulary . '-' . $langcode . '-' . implode(':', $context_keys);
  }

  /**
   * Update seenTerms cache from context.
   */
  private function setCurrentMatchTermAsSeen($ctx): void {
    if ($ctx->isSingleMatch) {
      $this->seenTerms[$ctx->seenId][$ctx->seenName][$ctx->synonym] = TRUE;
    }
  }

  /**
   * Check if a term as already been seen.
   */
  private function checkIfMatchTermAlreadySeen(&$ctx): bool {
    if ($ctx->isSingleMatch) {
      if ($ctx->matchAllSynonyms) {
        // Check if we have already seen the current synonym.
        return isset($this->seenTerms[$ctx->seenId][$ctx->seenName][$ctx->synonym]);
      }
      else {
        // Check if we have already seen this term.
        return isset($this->seenTerms[$ctx->seenId][$ctx->seenName]);
      }
    }
    return FALSE;
  }

  /**
   * Check if all synonyms of a term have been seen.
   */
  private function checkIfAllSynonymsSeen(&$ctx): bool {
    // +1 because the term name is added to the list of seen synonyms.
    return empty($ctx->termArray['synonyms']) || (isset($this->seenTerms[$ctx->seenId][$ctx->seenName])
      && (count($this->seenTerms[$ctx->seenId][$ctx->seenName]) === count($ctx->termArray['synonyms']) + 1));
  }

  /**
   * {@inheritDoc}
   */
  public function replaceFieldValue($input, $vocabularies, $root_entity = NULL) {
    if (empty($input)) {
      return FALSE;
    }
    else {
      if (is_null($this->handler)) {
        $this->logger->error($this->t('No integration type defined. Check module configuration.'));
        return FALSE;
      }

      if (!Unicode::validateUtf8($input)) {
        $this->logger->debug($this->t('Input is not valid UTF-8: @input', ['@input' => $input]));
        return FALSE;
      }

      $vocabularies = is_array($vocabularies) ? $vocabularies : [$vocabularies];

      $langcode = isset($root_entity)
        ? $root_entity->language()->getId()
        : $this->languageManager->getCurrentLanguage()->getId();

      $cache_tags = [];
      $term_list = [];
      foreach ($vocabularies as $vocabulary) {
        $term_list += $this->getTerms($vocabulary, $langcode);
        $cache_tags[] = "taxonomy_term_list:$vocabulary";
      }

      /* @see https://www.drupal.org/project/term_glossary/issues/3535737 */
      if ($root_entity instanceof TermInterface) {
        $exclude_self_reference = $this->config->get('exclude_self_reference') ?? FALSE;
        if ($exclude_self_reference) {
          unset($term_list[$root_entity->id()]);
        }
      }

      $is_single_match_per_field = $this->config->get('single_match') ?? FALSE;
      $is_single_match_per_content = $this->config->get('single_match_per_content') ?? FALSE;
      $is_full_word = $this->config->get('full_word') ?? FALSE;
      $is_case_sensitive = $this->config->get('case_sensitive') ?? FALSE;
      $boundary_exceptions = $this->config->get('boundary_exceptions') ?? '';
      $ignore_tags = $this->config->get('ignore_tags') ?? '';

      $ctx = new \stdClass();

      $ctx->isSingleMatch = $is_single_match_per_field || $is_single_match_per_content;
      $ctx->matchSynonyms = $this->config->get('term_synonyms') ?? FALSE;
      $ctx->matchAllSynonyms = $this->config->get('match_all_synonyms') ?? FALSE;

      $ctx->seenId = 0;
      $this->seenTerms[0] = [];

      if ($is_single_match_per_content && isset($root_entity)) {
        $ctx->seenId = $root_entity->id();
        if (!isset($this->seenTerms[$ctx->seenId])) {
          // Create the already seen cache for root_entity if not done yet.
          $this->seenTerms[$ctx->seenId] = [];
        }
      }

      $ctx->tags = [];
      $ctx->tagIndex = 0;

      $html_dom = Html::load($input);
      $xpath = new \DOMXPath($html_dom);

      /*
       * Parts and ideas of the code have been taken from the "glossify" module.
       * https://git.drupalcode.org/project/glossify
       */
      $ignore = '';
      if (strlen($ignore_tags)) {
        $tags = explode(',', strtolower(str_replace(' ', '', $ignore_tags)));
        foreach ($tags as $tag) {
          $ignore .= " | ancestor::$tag";
        }
      }
      $query = '//text()[not(ancestor::a | ancestor::img' . $ignore . ') and not(ancestor::*[contains(concat(" ",normalize-space(@class)," ")," glossary-exclude ")])]';
      /** @var \DOMNodeList $text_nodes */
      $text_nodes = $xpath->query($query);

      // Drop terms (and synonyms) that appear nowhere in the matchable text.
      // A term absent from the case-folded text cannot match whatever its
      // case or boundary options, so this only skips regexes that would find
      // nothing; with large vocabularies it removes almost all of them.
      $all_text = '';
      foreach ($text_nodes as $text_node) {
        $all_text .= self::foldCase($text_node->nodeValue) . "\n";
      }
      $folded_names = [];
      foreach ($term_list as $tid => $term_array) {
        $folded_name = self::foldCase((string) $term_array['name']);
        $present = str_contains($all_text, $folded_name);
        if (!$present && $ctx->matchSynonyms) {
          foreach ($term_array['synonyms'] ?? [] as $synonym) {
            if (str_contains($all_text, self::foldCase((string) $synonym))) {
              $present = TRUE;
              break;
            }
          }
        }
        if ($present) {
          $folded_names[$tid] = $folded_name;
        }
        else {
          unset($term_list[$tid]);
        }
      }

      foreach ($text_nodes as $current_text_node) {
        $current_node_text = $current_text_node->nodeValue;

        if (empty(trim($current_node_text))) {
          continue;
        }

        $ctx->currentText = $current_node_text;
        // Same substring pre-check as above, narrowed to this text node.
        $haystack = self::foldCase($current_node_text);

        $last_tag_index = $ctx->tagIndex;

        foreach ($term_list as $ctx->termId => $ctx->termArray) {
          $ctx->seenName = $ctx->termArray['name'];
          $ctx->synonym = $ctx->seenName;
          if ($this->checkIfMatchTermAlreadySeen($ctx)) {
            if (!($ctx->matchSynonyms && $ctx->matchAllSynonyms)) {
              // We have already seen that term, skip.
              continue;
            }
          }
          elseif (str_contains($haystack, $folded_names[$ctx->termId])) {
            // Process main term name.
            $name = $ctx->termArray['name'];
            $ctx->pattern = $this->buildTermPattern(
              $name, $ctx->termArray, $is_full_word, $is_case_sensitive, $boundary_exceptions);
            $ctx->currentText = $this->doMatchReplace($ctx);
          }
          // If enabled, process synonyms.
          if ($ctx->matchSynonyms) {
            // Skip processing if all synonyms have already been seen.
            if ($this->checkIfAllSynonymsSeen($ctx)) {
              continue;
            }
            // Process term synonyms if any.
            foreach ($ctx->termArray['synonyms'] as $synonym) {
              $ctx->synonym = $synonym;
              if ($this->checkIfMatchTermAlreadySeen($ctx)) {
                // We have already replaced that term, skip.
                // Can occur if the term and/or some of its synonyms are present
                // in the same text.
                // Remember that the term will be processed first, regardless
                // of whether the synonym appears earlier in the text.
                continue;
              }
              if (!str_contains($haystack, self::foldCase((string) $synonym))) {
                continue;
              }
              $ctx->pattern = $this->buildTermPattern(
                $synonym, $ctx->termArray, $is_full_word, $is_case_sensitive, $boundary_exceptions);
              $ctx->currentText = $this->doMatchReplace($ctx);
            }
          }
        }

        // Perform replacements only if one or more matches are found.
        if ($ctx->tagIndex > $last_tag_index) {
          $current_text_node->nodeValue = $ctx->currentText;
        }
      }

      $result = [];
      $result['cache_tags'] = $cache_tags;
      if ($ctx->tagIndex > 0) {
        // Replace all placeholders by their corresponding tags.
        $html = preg_replace_callback(
          '/\{\{(\d+)}}/',
          function ($matches) use (&$ctx) {
            $index = (int) $matches[1];
            if (isset($ctx->tags[$index])) {
              $term_data = $ctx->tags[$index][0];
              $term_match = $ctx->tags[$index][1];
              $tag_markup = $this->buildMatchTag($term_data, $term_match);
              $tag_html = trim($tag_markup->jsonSerialize());
            }
            else {
              $tag_html = NULL;
            }
            return $tag_html ?? '{{' . $index . '}}';
          },
          Html::serialize($html_dom),
        );
        $result['html'] = $html;
        $result['count'] = $ctx->tagIndex;
      }
      return $result;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function attachLibrariesAndSettings(&$variables) {
    if (is_null($this->handler)) {
      $this->logger->error($this->t('No integration type defined. Check module configuration.'));
    }
    else {
      $this->handler->attachLibrariesAndSettings($variables);
    }
  }

  /**
   * Build the button for the matched term.
   */
  protected function buildMatchTag(
    array $term_data,
    mixed $match,
  ) {
    $match_tag = [];
    // Let handler generates the match tag.
    $this->handler->buildMatchTag($match_tag, $match, $term_data);
    // Allow other modules to modify the term render array.
    $this->moduleHandler->alter(
      'term_glossary_term_match', $match_tag, $term_data, $match);
    // Handle backward compatibility.
    if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
      $tag_markup = $this->renderer->renderInIsolation($match_tag);
    }
    else {
      // @phpstan-ignore-next-line as it is deprecated in D10.3 and removed from D12.
      $tag_markup = $this->renderer->renderPlain($match_tag);
    }
    return $tag_markup;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfig(): ?ImmutableConfig {
    return $this->config;
  }

  /**
   * Load all terms for the vocabulary that are available in a given language.
   *
   * @param string $vocabulary
   *   Glossary vocabulary id.
   * @param string $langcode
   *   Language code.
   *
   * @return int[]
   *   Array of term ids. Might be empty if no terms are available
   *   for given parameters.
   */
  private function loadTermIds(string $vocabulary, string $langcode): array {
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $query->condition('vid', $vocabulary);
    $query->condition('status', 1);
    $query->condition('langcode', $langcode);
    // Sort terms by weight to allow processing prioritization.
    $query->sort('weight');
    $query->accessCheck();
    return $query->execute() ?? [];
  }

  /**
   * Get an updated list of glossary terms.
   *
   * @return array
   *   Glossary term list
   */
  private function getTerms(string $vocabulary, string $langcode): array {
    $cid = $this->getCacheId($vocabulary, $langcode);
    $data = $this->cache->get($cid);
    if (empty($data)) {
      // Cache is invalidated when a term is added/updated/deleted.
      $terms = $this->updateTermList($vocabulary, $langcode);
    }
    else {
      // Get the terms from cache.
      $terms = $data->data;
    }
    return $terms;
  }

  /**
   * Update the vocabulary term list.
   *
   * @param string $vocabulary
   *   Glossary vocabulary id.
   * @param string $langcode
   *   Language code.
   *
   * @return array
   *   Glossary term list
   */
  private function updateTermList($vocabulary, $langcode): array {
    $entity_ids = $this->loadTermIds($vocabulary, $langcode);
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple($entity_ids);

    $per_term_options = $this->config->get('per_term_options') ?? FALSE;
    $term_synonyms = $this->config->get('term_synonyms') ?? FALSE;
    $synonyms_field = $this->config->get('synonyms_field') ?? NULL;
    $has_synonyms = $term_synonyms && $synonyms_field != NULL;
    $terms_array = [];
    /**
     * @var \Drupal\taxonomy\TermInterface $term
     */
    foreach ($terms as $term) {
      $term = $this->entityRepository->getTranslationFromContext($term, $langcode);
      $term_data = [
        'tid' => $term->id(),
        'name' => $term->getName(),
        'lang' => $term->language()->getId(),
        'full_word' => self::MATCH_DEFAULT,
        'case_sensitive' => self::MATCH_DEFAULT,
        'synonyms' => [],
      ];
      if ($per_term_options) {
        if ($term->hasField('field_full_word')) {
          if (!$term->get('field_full_word')->isEmpty()) {
            $term_data['full_word'] = (int) $term->get('field_full_word')->value;
          }
        }
        if ($term->hasField('field_case_sensitive')) {
          if (!$term->get('field_case_sensitive')->isEmpty()) {
            $term_data['case_sensitive'] = (int) $term->get('field_case_sensitive')->value;
          }
        }
        if ($term->hasField('field_boundary_exceptions')) {
          if (!$term->get('field_boundary_exceptions')->isEmpty()) {
            $term_data['boundary_exceptions'] = (string) $term->get('field_boundary_exceptions')->value;
          }
        }
      }
      if ($has_synonyms) {
        if ($term->hasField($synonyms_field)) {
          if (!$term->get($synonyms_field)->isEmpty()) {
            $synonyms = [];
            foreach ($term->get($synonyms_field)->getValue() as $item) {
              $synonyms[] = $item['value'];
            }
            $term_data['synonyms'] = $synonyms;
          }
        }
      }
      // Let handler customize default term data array.
      $this->handler->buildTermData($term_data, $term, $langcode);
      // Allow other modules to modify the term data array.
      $this->moduleHandler->alter(
        'term_glossary_term_data', $term_data, $term, $langcode);
      $terms_array[$term->id()] = $term_data;
    }
    $cid = $this->getCacheId($vocabulary, $langcode);
    // The cache will be invalidated when a term from this vocabulary is added,
    // updated, or deleted, due to the use of the following cache tag.
    $cache_tags = array_merge($this->config->getCacheTags(), ["taxonomy_term_list:$vocabulary"]);
    $this->cache->set($cid, $terms_array, CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
    return $terms_array;
  }

  /**
   * {@inheritDoc}
   */
  public function getVocabulariesFromFieldPreprocessVariables(&$variables) {
    $entity = $variables['element']['#object'];
    $view_mode = $variables['element']['#view_mode'];
    $field_name = $variables['element']['#field_name'];
    $display_vocabularies = $this->getVocabulariesFromEntity($entity, $field_name, $view_mode);
    if ($display_vocabularies === FALSE) {
      return FALSE;
    }
    else {
      if (empty($display_vocabularies)) {
        return explode(',', $this->config->get('vocab'));
      }
      else {
        return $display_vocabularies;
      }
    }
  }

  /**
   * Get the vocabulary from the field display.
   *
   * @param array|null $field_display
   *   The field display to get the vocabulary from.
   *
   * @return array|false
   *   The vocabulary name.
   */
  protected function getVocabulariesFromFieldDisplay(?array $field_display) {
    if (isset($field_display['third_party_settings']['term_glossary'])) {
      if ($field_display['third_party_settings']['term_glossary']['glossary']) {
        if (empty($field_display['third_party_settings']['term_glossary']['glossary_vocabulary'])) {
          return [];
        }
        else {
          $vocabularies_ids = [];
          $glossary_vocabularies = $field_display['third_party_settings']['term_glossary']['glossary_vocabulary'];
          if (is_array($glossary_vocabularies)) {
            foreach ($glossary_vocabularies as $vocabulary) {
              $vocabularies_ids[] = $vocabulary['target_id'];
            }
          }
          else {
            $vocabularies_ids[] = (string) $glossary_vocabularies;
          }
          return $vocabularies_ids;
        }
      }
    }
    return FALSE;
  }

  /**
   * Find the vocabulary associated with the display field of an entity.
   *
   * @param mixed $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param string $view_mode
   *   The view mode.
   *
   * @return false|array
   *   The vocabulary if defined, NULL if not defined, FALSE if not active.
   */
  protected function getVocabulariesFromEntity($entity, $field_name, $view_mode) {
    // Get the field formatter settings.
    $entity_display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);
    $field_display = $entity_display->getComponent($field_name);
    return $this->getVocabulariesFromFieldDisplay($field_display);
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldFormatterThirdPartySettingsElements(
    FormatterInterface $plugin,
    FieldDefinitionInterface $field_definition,
  ) {
    $elements = [];
    $elements['glossary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable term glossary'),
      '#default_value' => $plugin
        ->getThirdPartySetting('term_glossary', 'glossary'),
      '#description' => $this->t('Enable the term glossary functionality for this field.'),
      '#attributes' => [
        'class' => ['toggle-vocabulary-autocomplete'],
        'data-toggle-target' => $field_definition->getName() . '_glossary_vocabulary',
      ],
    ];
    $configuration_url = Url::fromRoute('term_glossary.glossary_config_form')->toString();
    $glossary_vocabularies = $plugin->getThirdPartySetting('term_glossary', 'glossary_vocabulary');
    $vocabularies = $this->getVocabulariesFromThirdPartySetting($glossary_vocabularies);
    $elements['glossary_vocabulary'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_vocabulary',
      '#default_value' => $vocabularies,
      '#description' => $this->t('Select the vocabularies to use as a glossary for this field content.<br/>If none are set, the global setting applies (<a href=":url">Settings page</a>).', [':url' => $configuration_url]),
      '#tags' => TRUE,
      '#attributes' => [
        'data-toggle-target-id' => $field_definition->getName() . '_glossary_vocabulary',
      ],
    ];
    $elements['#attached']['library'][] = 'term_glossary/glossary.toggle_autocomplete';
    return $elements;
  }

  /**
   * Gets the vocabularies referenced in the third-party setting.
   */
  private function getVocabulariesFromThirdPartySetting(mixed $glossary_vocabularies) {
    if (empty($glossary_vocabularies)) {
      $vocabularies = NULL;
    }
    else {
      $vocabularies_ids = [];
      if (is_array($glossary_vocabularies)) {
        foreach ($glossary_vocabularies as $vocabulary) {
          $vocabularies_ids[] = $vocabulary['target_id'];
        }
      }
      else {
        $vocabularies_ids[] = (string) $glossary_vocabularies;
      }
      $vocabularies = Vocabulary::loadMultiple($vocabularies_ids);
    }
    return $vocabularies;
  }

  /**
   * Gets the root entity of a paragraph.
   *
   * @param mixed $entity
   *   The current entity to check for parents.
   *
   * @return mixed
   *   The root entity.
   */
  protected function findRootEntity($entity) {
    if ($entity->getEntityTypeId() === 'paragraph') {
      $parent_entity = $entity->getParentEntity();
      $root = $this->findRootEntity($parent_entity);
    }
    else {
      $root = $entity;
    }
    return $root;
  }

  /**
   * {@inheritDoc}
   */
  public function getRootEntityFromFieldPreprocessVariables($variables) {
    if (isset($variables['element']['#object'])) {
      return $this->findRootEntity($variables['element']['#object']);
    }
    return NULL;
  }

}
