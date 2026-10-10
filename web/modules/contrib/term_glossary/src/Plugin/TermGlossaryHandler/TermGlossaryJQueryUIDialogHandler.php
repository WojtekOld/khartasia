<?php

namespace Drupal\term_glossary\Plugin\TermGlossaryHandler;

use Drupal\Core\Form\FormStateInterface;

/**
 * Render terms using jQuery UI dialog.
 *
 * @TermGlossaryHandler(
 *   id = "default",
 *   title = @Translation("jQueryUI dialog"),
 * )
 */
class TermGlossaryJQueryUIDialogHandler extends TermGlossaryCustomJSHandler {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'glossary.glossaryconfig.jqueryui',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('term_glossary.glossaryconfig.jqueryui');

    $form['dialog_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('jQuery UI Dialog'),
      '#collapsible' => FALSE,
    ];

    $form['dialog_settings']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Dialog with the term name and description (or rendered term entity cf. "View mode" below), minimal styles for highlighted terms.'),
      '#prefix' => '<em>',
      '#suffix' => '</em>',
    ];

    $form['dialog_settings']['dialog_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialog width (optional)'),
      '#default_value' => $config->get('dialog_width') ?? '',
      '#description' => $this->t('The width of the dialog in pixels (ex: 500px) or viewport width percentage (ex: 66%). Defaults to 300px.'),
    ];

    $form['dialog_settings']['dialog_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialog height (optional)'),
      '#default_value' => $config->get('dialog_height') ?? '',
      '#description' => $this->t('The height of the dialog in pixels (ex: 500px) or viewport height percentage (ex: 66%). Defaults to "auto".'),
    ];

    $form['dialog_settings']['dialog_min_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialog minimum height (optional)'),
      '#default_value' => $config->get('dialog_min_height') ?? '',
      '#description' => $this->t('The minimum height of the dialog in pixels (ex: 300px) or viewport height percentage (ex: 20%). Defaults to 150px.'),
    ];

    $form['dialog_settings']['dialog_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialog title (optional)'),
      '#default_value' => $config->get('dialog_title') ?? '',
      '#description' => $this->t('The text that will be displayed in the dialog title bar. If empty, "Term definition" will be used.'),
    ];

    $form['dialog_settings']['dialog_close_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bottom close button'),
      '#default_value' => $config->get('dialog_close_button') ?? TRUE,
      '#description' => $this->t('When enabled, a close button will be displayed at the bottom of the dialog.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('term_glossary.glossaryconfig.jqueryui')
      ->set('dialog_width', $form_state->getValue('dialog_width'))
      ->set('dialog_height', $form_state->getValue('dialog_height'))
      ->set('dialog_min_height', $form_state->getValue('dialog_min_height'))
      ->set('dialog_title', $form_state->getValue('dialog_title'))
      ->set('dialog_close_button', $form_state->getValue('dialog_close_button'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function attachLibrariesAndSettings(&$variables) {
    parent::attachLibrariesAndSettings($variables);
    $config = $this->config('term_glossary.glossaryconfig.jqueryui');
    // Attach the jQueryUI dialog library and the related settings.
    $variables['#attached']['library'][] = 'term_glossary/glossary';
    $variables['#attached']['drupalSettings']['termGlossary'] = [
      'dialogWidth' => $config->get('dialog_width') ?? '',
      'dialogHeight' => $config->get('dialog_height') ?? '',
      'dialogMinHeight' => $config->get('dialog_min_height') ?? '',
      'dialogTitle' => $config->get('dialog_title') ?? $this->t('Term definition'),
      'closeButton' => $config->get('dialog_close_button') ?? TRUE,
    ];
  }

}
