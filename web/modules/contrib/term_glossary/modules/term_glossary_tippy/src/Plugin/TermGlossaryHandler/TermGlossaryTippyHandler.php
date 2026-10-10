<?php

namespace Drupal\term_glossary_tippy\Plugin\TermGlossaryHandler;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_glossary\TermGlossaryHandlerBase;
use Drupal\term_glossary_tippy\TippyLibraries;

/**
 * Render terms in tooltips using Tippy.js library.
 *
 * @TermGlossaryHandler(
 *   id = "term_glossary_tippy",
 *   title = @Translation("Tooltips with Tippy.js"),
 * )
 */
class TermGlossaryTippyHandler extends TermGlossaryHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'glossary.glossaryconfig.tippyjs',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildTermData(array &$term_data, TermInterface $term, string $langcode): void {
    parent::buildTermData($term_data, $term, $langcode);
    $description = $term->getDescription();
    if (!empty($description)) {
      $term_data['title'] = strip_tags($description);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildMatchTag(array &$match_tag, string $match_value, array $term_data): void {
    parent::buildMatchTag($match_tag, $match_value, $term_data);
    $match_tag += [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => Html::escape($match_value),
    ];
    $description = $term_data['title'] ?? '';
    if (!empty($description)) {
      $config = $this->config('term_glossary.glossaryconfig.tippyjs');
      $match_tag['#tag'] = $config->get('html_tag') ?: 'span';
      $description_attribute = $config->get('desc_attr') ?: 'title';
      $match_tag['#attributes'][$description_attribute] = $description;
      $match_tag['#attributes']['class'] = ['glos-term', 'glos-term-tippy'];
      if ($config->get('a11y_features')) {
        $match_tag['#attributes']['tabindex'] = '0';
        $match_tag['#attributes']['role'] = 'button';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function attachLibrariesAndSettings(&$variables) {
    parent::attachLibrariesAndSettings($variables);
    // Attach the libraries required by tippy.
    $variables['#attached']['library'][] = 'term_glossary_tippy/term_glossary_tippy';
    $config = $this->config('term_glossary.glossaryconfig.tippyjs');
    $variables['#attached']['drupalSettings']['termGlossaryTippy'] = [
      'a11yFeatures' => $config->get('a11y_features') ?? TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('term_glossary.glossaryconfig.tippyjs');

    // Warn the administrator when the external libraries are missing.
    $missing_files = TippyLibraries::getMissingFiles();
    if ($missing_files !== []) {
      $form['libraries_warning'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [TippyLibraries::buildWarningMessage($missing_files)],
        ],
        '#status_headings' => [
          'warning' => $this->t('Warning message'),
        ],
      ];
    }

    $form['handler_settings'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
    ];

    $form['handler_settings']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Generates tooltips for glossary terms using the Tippy.js Javascript library.<br>The term description is used as the tooltip content.'),
      '#prefix' => '<em>',
      '#suffix' => '</em>',
    ];

    $form['handler_settings']['html_tag'] = [
      '#title' => $this->t('HTML tag'),
      '#type' => 'select',
      '#options' => ['span' => $this->t('span'), 'abbr' => $this->t('abbr')],
      '#default_value' => $config->get('html_tag') ?: 'span',
      '#description' => $this->t('The HTML element that will be used to render the matched terms.'),
    ];

    $form['handler_settings']['desc_attr'] = [
      '#title' => $this->t('Description attribute'),
      '#type' => 'select',
      '#options' => ['aria-label' => $this->t('aria-label'), 'title' => $this->t('title')],
      '#default_value' => $config->get('desc_attr') ?: 'title',
      '#description' => $this->t('The attribute of the HTML tag that will receive the term description.'),
    ];

    $form['handler_settings']['a11y_features'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accessibility features (recommended)'),
      '#default_value' => $config->get('a11y_features') ?? TRUE,
      '#description' => $this->t('Configure Tippy.js for better accessibility, adds <code>tabindex="0"</code> and <code>role="button"</code> attributes to the HTML element for keyboard navigation.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('term_glossary.glossaryconfig.tippyjs')
      ->set('html_tag', $form_state->getValue('html_tag'))
      ->set('desc_attr', $form_state->getValue('desc_attr'))
      ->set('a11y_features', $form_state->getValue('a11y_features'))
      ->save();
  }

}
