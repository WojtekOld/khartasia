<?php

namespace Drupal\term_glossary\Plugin\TermGlossaryHandler;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\term_glossary\TermGlossaryHandlerBase;

/**
 * Render terms using custom JS.
 *
 * @TermGlossaryHandler(
 *   id = "custom_js",
 *   title = @Translation("Custom JS"),
 * )
 */
class TermGlossaryCustomJSHandler extends TermGlossaryHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'glossary.glossaryconfig.customjs',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildMatchTag(array &$match_tag, string $match_value, array $term_data): void {
    $match_tag += [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'role' => 'button',
        'tabindex' => 0,
        'class' => ['glos-term'],
        'data-gterm' => $term_data['tid'],
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
      '#markup' => $this->t('If you select "Custom JS" it\'s up to you to handle the Javascript and CSS side of what happens when someone clicks a term.<br>Generates a <code>span</code> element with class <code>glos-term</code> and adds a <code>data-gterm</code> attribute which will contain the term id.'),
      '#prefix' => '<em>',
      '#suffix' => '</em>',
    ];

    return $form;
  }

}
