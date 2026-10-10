<?php

namespace Drupal\term_glossary_abbr\Plugin\TermGlossaryHandler;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_glossary\TermGlossaryHandlerBase;

/**
 * Render terms using ABBR HTML elements.
 *
 * @TermGlossaryHandler(
 *   id = "term_glossary_abbr",
 *   title = @Translation("ABBR HTML element"),
 * )
 */
class TermGlossaryAbbreviationHandler extends TermGlossaryHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'glossary.glossaryconfig.abbr',
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
      '#tag' => 'abbr',
      '#attributes' => [
        'title' => $term_data['title'] ?? '',
        'class' => ['glos-term'],
      ],
      '#value' => Html::escape($match_value),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['handler_settings'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
    ];

    $form['handler_settings']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Generates ABBR HTML elements using the term description as the element title attribute.'),
      '#prefix' => '<em>',
      '#suffix' => '</em>',
    ];

    return $form;
  }

}
