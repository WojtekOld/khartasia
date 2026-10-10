<?php

declare(strict_types=1);

namespace Drupal\term_glossary\Hook;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\term_glossary\Service\TermGlossaryManagerInterface;

/**
 * Hook implementations for term_glossary.
 */
class TermGlossaryHooks {

  use StringTranslationTrait;

  public function __construct(
    protected TermGlossaryManagerInterface $glossaryManager,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.term_glossary':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('This flexible module uses a vocabulary (taxonomy) as a glossary.
      It auto scans the fields (you configure it to) for uses of taxonomy terms
      defined in your vocabulary.') . '</p>';
        return $output;

      default:
        return NULL;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'glossary_alphabetical_block' => [
        'variables' => [
          'content' => NULL,
          'type' => NULL,
        ],
        'template' => 'glossary-alphabetical-block',
        'render element' => 'children',
      ],
    ];
  }

  /**
   * Check if the formatter is compatible with term_glossary.
   */
  private function checkFormatter(string $plugin_id): bool {
    $accept_only = [
      'text_default',
      'text_trimmed',
      'string',
    ];
    return in_array($plugin_id, $accept_only);
  }

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   */
  #[Hook('field_formatter_third_party_settings_form')]
  public function fieldFormatterThirdPartySettingsForm(FormatterInterface $plugin, FieldDefinitionInterface $field_definition, $view_mode, array $form, FormStateInterface $form_state): array {
    $elements = [];
    if ($this->checkFormatter($plugin->getPluginId())) {
      $elements = $this->glossaryManager->getFieldFormatterThirdPartySettingsElements($plugin, $field_definition);
    }
    return $elements;
  }

  /**
   * Implements hook_field_formatter_settings_summary_alter().
   */
  #[Hook('field_formatter_settings_summary_alter')]
  public function fieldFormatterSettingsSummaryAlter(array &$summary, array $context): void {
    if ($this->checkFormatter($context['formatter']->getPluginId())) {
      if ($context['formatter']->getThirdPartySetting('term_glossary', 'glossary')) {
        $summary[] = $this->t('Term glossary enabled.');
      }
      if ($context['formatter']->getThirdPartySetting('term_glossary', 'glossary_vocabulary')) {
        $glossary_vocabularies = $context['formatter']->getThirdPartySetting('term_glossary', 'glossary_vocabulary');
        $vocabularies_ids = [];
        if (is_array($glossary_vocabularies)) {
          foreach ($glossary_vocabularies as $vocabulary) {
            $vocabularies_ids[] = $vocabulary['target_id'];
          }
          $summary[] = $this->t('Vocabularies: @vocabularies', ['@vocabularies' => implode(', ', $vocabularies_ids)]);
        }
        else {
          $summary[] = $this->t('Vocabulary: @vocabulary', ['@vocabulary' => (string) $glossary_vocabularies]);
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_field().
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    if (!$this->checkFormatter($variables['element']['#formatter'])) {
      return;
    }

    if (is_null($this->glossaryManager->getHandler())) {
      // Module is not properly configured yet, do nothing.
      return;
    }

    $vocabularies = $this->glossaryManager->getVocabulariesFromFieldPreprocessVariables($variables);

    if (empty($vocabularies)) {
      return;
    }

    $root_entity = $this->glossaryManager->getRootEntityFromFieldPreprocessVariables($variables);
    $replacements = 0;
    // Parse string to add term highlight.
    foreach ($variables['items'] as $key => $item) {
      /** @var \Drupal\Core\Render\Markup $markup */
      $markup = $this->renderer->render($variables['items'][$key]['content']);
      $result = $this->glossaryManager->replaceFieldValue(
        $markup->jsonSerialize(), $vocabularies, $root_entity);
      if (is_array($result)) {
        if (isset($result['html'])) {
          $replacements += $result['count'];
          // Replace field markup content. The HTML is already safe: it is the
          // field's own rendered (text-format filtered) output with the term
          // tags (rendered in isolation) spliced in. Wrap it in Markup so the
          // renderer does not run it through Xss::filterAdmin() again, which
          // would strip format-allowed markup such as style attributes and
          // inline SVG that the field legitimately contains.
          $variables['items'][$key]['content'] = [
            '#markup' => Markup::create($result['html']),
          ];
        }
        // Attach cache tags to the render array.
        $bubbleable_metadata = new BubbleableMetadata();
        $cache_tags = $this->glossaryManager->getConfig()->getCacheTags();
        if (!empty($result['cache_tags'])) {
          $cache_tags = array_merge($cache_tags, $result['cache_tags']);
        }
        $bubbleable_metadata->setCacheTags($cache_tags);
        $bubbleable_metadata->applyTo($variables);
      }
    }
    // Inject libraries and settings when replacements occurred.
    if ($replacements > 0) {
      $this->glossaryManager->attachLibrariesAndSettings($variables);
    }
  }

}
