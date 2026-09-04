<?php

namespace Drupal\tmgmt_deepl\Plugin\KeyType;

use DeepL\DeepLException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\key\Plugin\KeyType\AuthenticationKeyType;
use Drupal\tmgmt_deepl\DeepLClientFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a key type for DeepL API keys with integrated validation.
 *
 * @KeyType(
 *   id = "deepl_api_key",
 *   label = @Translation("DeepL API Key"),
 *   description = @Translation("Provides a key type for DeepL API keys. Attempts to validate the key against the DeepL API upon saving."),
 *   group = "api_credentials",
 *   key_value = {
 *     "plugin" = "text_field"
 *   },
 *   storage_method = "any",
 * )
 *
 * @phpstan-consistent-constructor
 */
class DeepLApiKeyType extends AuthenticationKeyType implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The DeepL client factory.
   *
   * @var \Drupal\tmgmt_deepl\DeepLClientFactoryInterface
   */
  protected DeepLClientFactoryInterface $deepLClientFactory;

  /**
   * Constructs a new DeepLKeyType object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\tmgmt_deepl\DeepLClientFactoryInterface $deepLClientFactory
   *   The DeepL client factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, DeepLClientFactoryInterface $deepLClientFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->deepLClientFactory = $deepLClientFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('tmgmt_deepl'),
      $container->get('tmgmt_deepl.client_factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateKeyValue(array $form, FormStateInterface $form_state, $key_value): void {
    // Basic non-empty check first.
    if ($key_value === '' || $key_value === NULL) {
      $form_state->setErrorByName('key_value', $this->t('The DeepL API key value cannot be empty.'));
      return;
    }

    try {
      $deepl_client = $this->deepLClientFactory->create($key_value, 'tmgmt_deepl', '');
      $deepl_client->getUsage();
    }
    catch (DeepLException $e) {
      // Use the injected logger.
      $this->logger->error('DeepL API key validation failed during Key save: DeepL API error. Message: %msg', [
        '%msg' => $e->getMessage(),
      ]);
      $form_state->setErrorByName('key_value', $this->t('Validation failed: An error occurred while communicating with the DeepL API (@error). Please check the key and DeepL service status.', ['@error' => $e->getMessage()]));
    }
  }

}
