<?php

namespace Drupal\tmgmt_deepl_glossary;

use DeepL\AppInfo;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\tmgmt\TranslatorInterface;
use Psr\Log\LoggerInterface;

/**
 * A service for managing DeepL glossary API calls.
 */
class DeeplMultilingualGlossaryApi implements DeeplMultilingualGlossaryApiInterface {

  use StringTranslationTrait;

  /**
   * The Drupal logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The translator.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  protected TranslatorInterface $translator;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected KeyRepositoryInterface $keyRepository,
    LoggerChannelFactoryInterface $logger_factory,
    protected MessengerInterface $messenger,
  ) {
    // @codeCoverageIgnoreStart
    $this->logger = $logger_factory->get('tmgmt_deepl_glossary');
    // @codeCoverageIgnoreEnd
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslator(TranslatorInterface $translator): void {
    $this->translator = $translator;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeeplClient(): DeepLClient {
    if (isset($this->translator)) {
      $key_id = $this->translator->getSetting('auth_key_entity');
      $omit_partner_id = $this->translator->getSetting('omit_partner_id');
      /* @phpstan-ignore-next-line */
      if (is_string($key_id) && $key_id !== '') {
        $key_entity = $this->keyRepository->getKey($key_id);
        assert($key_entity instanceof KeyInterface);

        // @codeCoverageIgnoreStart
        $api_key = $key_entity->getKeyValue();
        $app_name = (bool) $omit_partner_id ? 'tmgmt_deepl_glossary' : 'deepl-partner-integration-undpaul';
        $app_info = new AppInfo($app_name, '2.3.x');
        return new DeepLClient($api_key, ['app_info' => $app_info]);
        // @codeCoverageIgnoreEnd
      }
    }

    throw new \RuntimeException('DeepL client not set.');
  }

  /**
   * {@inheritdoc}
   */
  public function isFreeAccount(): bool {
    if (isset($this->translator)) {
      $key_id = $this->translator->getSetting('auth_key_entity');
      /* @phpstan-ignore-next-line */
      if (is_string($key_id) && $key_id !== '') {
        $key_entity = $this->keyRepository->getKey($key_id);
        assert($key_entity instanceof KeyInterface);
        // @codeCoverageIgnoreStart
        $api_key = $key_entity->getKeyValue();
        return DeepLClient::isAuthKeyFreeAccount($api_key);
        // @codeCoverageIgnoreEnd
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createMultilingualGlossary(string $name, array $dictionaries = []):? MultilingualGlossaryInfo {
    try {
      $deepl_client = $this->getDeeplClient();
      $ml_dictionaries = [];
      /** @var array<string, mixed> $dictionary */
      foreach ($dictionaries as $dictionary) {
        assert(array_key_exists('entries', $dictionary) && array_key_exists('source_lang', $dictionary) && array_key_exists('target_lang', $dictionary));
        /** @var array $entries_array */
        $entries_array = $dictionary['entries'];
        $entries = GlossaryEntries::fromEntries($entries_array);
        /** @var string $source_lang */
        $source_lang = $dictionary['source_lang'];
        /** @var string $target_lang */
        $target_lang = $dictionary['target_lang'];
        $ml_dictionaries[] = new MultilingualGlossaryDictionaryEntries($source_lang, $target_lang, $entries->getEntries());
      }
      // @codeCoverageIgnoreStart
      return $deepl_client->createMultilingualGlossary(trim($name), $ml_dictionaries);
      // @codeCoverageIgnoreEnd
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateMultilingualGlossary(string $glossary_id, string $name, array $dictionaries = []):? MultilingualGlossaryInfo {
    try {
      $deepl_client = $this->getDeeplClient();
      // @codeCoverageIgnoreStart
      $glossary = $deepl_client->getMultilingualGlossary($glossary_id);
      return $deepl_client->updateMultilingualGlossary($glossary, trim($name), $dictionaries);
      // @codeCoverageIgnoreEnd
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function replaceMultilingualGlossaryDictionary(string $glossary_id, string $source_lang, string $target_lang, array $entries, string $glossary_name = ''): ?MultilingualGlossaryDictionaryInfo {
    try {
      $deepl_client = $this->getDeeplClient();
      $entries = GlossaryEntries::fromEntries($entries);
      $dictionary_entries = new MultilingualGlossaryDictionaryEntries($source_lang, $target_lang, $entries->getEntries());
      // @codeCoverageIgnoreStart
      return $deepl_client->replaceMultilingualGlossaryDictionary($glossary_id, $dictionary_entries);
      // @codeCoverageIgnoreEnd
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMultilingualGlossaryMetadata(string $glossary_id):? MultilingualGlossaryInfo {
    try {
      $deepl_client = $this->getDeeplClient();
      // @codeCoverageIgnoreStart
      return $deepl_client->getMultilingualGlossary($glossary_id);
      // @codeCoverageIgnoreEnd
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMultilingualGlossaries(): array {
    try {
      $deepl_client = $this->getDeeplClient();
      // @codeCoverageIgnoreStart
      return $deepl_client->listMultilingualGlossaries();
      // @codeCoverageIgnoreEnd
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultilingualGlossary(string $glossary_id): bool {
    try {
      $deepl_client = $this->getDeeplClient();
      // @codeCoverageIgnoreStart
      $deepl_client->deleteMultilingualGlossary($glossary_id);
      // @codeCoverageIgnoreEnd
      return TRUE;
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultilingualGlossaryDictionary(string $glossary_id, string $source_lang, string $target_lang): bool {
    try {
      $deepl_client = $this->getDeeplClient();
      // @codeCoverageIgnoreStart
      $deepl_client->deleteMultilingualGlossaryDictionary($glossary_id, NULL, $source_lang, $target_lang);
      // @codeCoverageIgnoreEnd
      return TRUE;
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMultilingualGlossaryEntries(string $glossary_id, string $source_lang, string $target_lang): array {
    try {
      $deepl_client = $this->getDeeplClient();
      // @codeCoverageIgnoreStart
      $entries = $deepl_client->getMultilingualGlossaryEntries($glossary_id, $source_lang, $target_lang);
      // @codeCoverageIgnoreEnd
      $entries = reset($entries);
      assert($entries instanceof MultilingualGlossaryDictionaryEntries);
      $dictionary_entries = [];
      if (count($entries->entries) > 0) {
        foreach ($entries->entries as $subject => $definition) {
          $dictionary_entries[] = [
            'subject' => $subject,
            'definition' => $definition,
          ];
        }
      }
      return $dictionary_entries;
    }
    catch (DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      $this->logger->error($e);
      return [];
    }
  }

}
