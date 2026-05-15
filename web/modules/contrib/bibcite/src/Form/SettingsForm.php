<?php

namespace Drupal\bibcite\Form;

use Drupal\bibcite\CitationStylerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common configuration.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The styler service.
   *
   * @var \Drupal\bibcite\CitationStylerInterface
   */
  protected $styler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, CitationStylerInterface $styler) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->styler = $styler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('bibcite.citation_styler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bibcite_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bibcite.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bibcite.settings');

    $processor_definitions = $this->styler->getAvailableProcessors();
    $processor_options = array_map(function ($definition) {
      return $definition['label'];
    }, $processor_definitions);

    $form['processor'] = [
      '#type' => 'select',
      '#options' => $processor_options,
      '#title' => $this->t('Processor'),
      '#default_value' => $config->get('processor'),
    ];

    $csl_styles = $this->styler->getAvailableStyles();
    $styles_options = array_map(function ($entity) {
      /** @var \Drupal\bibcite\Entity\CslStyleInterface $entity */
      return $entity->label();
    }, $csl_styles);

    $form['default_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Default style'),
      '#options' => $styles_options,
      '#default_value' => $config->get('default_style'),
    ];

    $form['convert_urls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Convert URLs into hyperlinks'),
      '#default_value' => $config->get('convert_urls'),
      '#description' => $this->t('Enable this option to render any URLs in all citation formats as hyperlinks.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('bibcite.settings');
    $config
      ->set('processor', $form_state->getValue('processor'))
      ->set('default_style', $form_state->getValue('default_style'))
      ->set('convert_urls', $form_state->getValue('convert_urls'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
