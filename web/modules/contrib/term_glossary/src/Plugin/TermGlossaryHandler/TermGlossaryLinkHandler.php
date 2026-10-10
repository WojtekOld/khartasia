<?php

namespace Drupal\term_glossary\Plugin\TermGlossaryHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\term_glossary\TermGlossaryHandlerBase;

/**
 * Render an HTML link targeting the vocabulary term.
 *
 * @TermGlossaryHandler(
 *   id = "link",
 *   title = @Translation("Link to term")
 * )
 */
class TermGlossaryLinkHandler extends TermGlossaryHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'glossary.glossaryconfig.link',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildMatchTag(array &$match_tag, string $match_value, array $term_data): void {
    $link = Link::createFromRoute(
      $match_value,
      'entity.taxonomy_term.canonical',
      ['taxonomy_term' => $term_data['tid']],
      [
        'attributes' => [
          'class' => ['glos-term'],
        ],
      ]
    );
    $match_tag += $link->toRenderable();
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
      '#markup' => $this->t('A simple HTML link targeting the corresponding vocabulary term content will be generated.'),
      '#prefix' => '<em>',
      '#suffix' => '</em>',
    ];

    return $form;
  }

}
