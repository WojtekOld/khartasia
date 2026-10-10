<?php

namespace Drupal\term_glossary\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'GlossaryAlphabeticalBock' block.
 *
 * @Block(
 *  id = "glossary_alphabetical_bock",
 *  admin_label = @Translation("Glossary alphabetical block"),
 * )
 */
class GlossaryAlphabeticalBock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'search_type' => 'all',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['search_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Search box only'),
      '#default_value' => $this->configuration['search_type'],
      '#required' => TRUE,
      '#options' => [
        'search_only' => $this->t('Search Box only'),
        'all' => $this->t('Search box and letters'),
        'only_letters' => $this->t('Only Letters'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['search_type'] = $form_state->getValue('search_type');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#theme'] = 'glossary_alphabetical_block';
    $build['#type'] = $this->configuration['search_type'];
    $build['#attached']['library'][] = 'term_glossary/glossary.alpha';
    return $build;
  }

}
