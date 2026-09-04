<?php

namespace Drupal\proc\Element;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\proc\Service\ProcFieldProcessor;
use Drupal\proc\Traits\ProcMetadataMessageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form element for encryption and decryption.
 *
 * @FormElement("procfield")
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ProcField extends FormElementBase implements ContainerFactoryPluginInterface {

  use ProcMetadataMessageTrait;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;
  /**
   * The token service.
   *
   * @var mixed
   */
  protected $tokenService;
  /**
   * The proc field processor.
   *
   * @var \Drupal\proc\Service\ProcFieldProcessor
   */
  protected ProcFieldProcessor $procFieldProcessor;

  /**
   * Constructs a new ProcField object.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param mixed $token_service
   *   The token service.
   * @param \Drupal\proc\Service\ProcFieldProcessor $proc_field_processor
   *   The proc field processor.
   */
  public function __construct(
    CurrentPathStack $current_path,
    ConfigFactoryInterface $config_factory,
    $token_service,
    ProcFieldProcessor $proc_field_processor,
  ) {
    parent::__construct(
      [],
      '',
      ''
    );
    $this->currentPath = $current_path;
    $this->configFactory = $config_factory;
    $this->tokenService = $token_service;
    $this->procFieldProcessor = $proc_field_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $container->get('path.current'),
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('proc.proc_field_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#input' => TRUE,
      '#process' => [
        [$this->procFieldProcessor, 'processProcField'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

}
