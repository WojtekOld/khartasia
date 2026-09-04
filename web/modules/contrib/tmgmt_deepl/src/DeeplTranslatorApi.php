<?php

namespace Drupal\tmgmt_deepl;

use DeepL\DeepLException;
use DeepL\DocumentTranslationException;
use DeepL\TextResult;
use DeepL\Translator as DeepLTranslator;
use DeepL\Usage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;

/**
 * The DeeplTranslatorApi service.
 */
class DeeplTranslatorApi implements DeeplTranslatorApiInterface {
  use StringTranslationTrait;

  /**
   * The translator.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  protected TranslatorInterface $translator;

  /**
   * Define source language mappings.
   */
  const array LANGUAGE_MAPPING = [
    'EN-GB' => 'EN',
    'EN-US' => 'EN',
    'PT-BR' => 'PT',
    'PT-PT' => 'PT',
  ];

  public function __construct(
    protected MessengerInterface $messenger,
    protected KeyRepositoryInterface $keyRepository,
    protected FileSystemInterface $fileSystem,
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DeepLClientFactoryInterface $deepLClientFactory,
  ) {
  }

  /**
   * Returns the installed module version.
   *
   * @return string
   *   Semantic version or 'dev' for git checkouts.
   */
  protected function getModuleVersion(): string {
    $info = $this->moduleExtensionList->getExtensionInfo('tmgmt_deepl');
    $version = isset($info['version']) && is_string($info['version']) ? $info['version'] : '';
    return $version !== '' ? $version : 'dev';
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslator(): DeepLTranslator {
    if (isset($this->translator)) {
      $key_id = $this->translator->getSetting('auth_key_entity');
      $omit_partner_id = $this->translator->getSetting('omit_partner_id');

      /* @phpstan-ignore-next-line */
      if (is_string($key_id) && $key_id !== '') {
        $key_entity = $this->keyRepository->getKey($key_id);
        assert($key_entity instanceof KeyInterface);

        $api_key = $key_entity->getKeyValue();
        $app_name = (bool) $omit_partner_id ? 'tmgmt_deepl' : 'deepl-partner-integration-undpaul';
        return $this->deepLClientFactory->create($api_key, $app_name, $this->getModuleVersion());
      }
      throw new \RuntimeException('API key not configured.');
    }

    throw new \RuntimeException('Translator not set.');
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslator(TranslatorInterface $translator): void {
    $this->translator = $translator;
  }

  /**
   * {@inheritDoc}
   */
  public function translate(Translator $translator, $texts, string $source_language, string $target_language, array $options = [], ?string $context = NULL): array|TextResult|null {
    try {
      $deepl_translator = $this->getTranslator();

      // Add context to the options, if provided.
      $request_options = $options;
      if ($context !== NULL && $context !== '') {
        $request_options['context'] = $context;
      }

      return $deepl_translator->translateText($texts, $source_language, $target_language, $request_options);
    }
    catch (\RuntimeException | DeepLException $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      return NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function translateDocument(Translator $translator, FileInterface $file, string $source_language, string $target_language, array $options = []): FileInterface|null {
    if (isset($options['translate_documents']) && $options['translate_documents'] === FALSE) {
      return NULL;
    }

    try {
      $deepl_translator = $this->getTranslator();

      $original_filename = $file->getFilename();
      assert(is_string($original_filename));
      $filename = pathinfo($original_filename, PATHINFO_FILENAME);
      $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
      $target_filename = $filename . '_' . $target_language . '.' . $extension;
      $file_uri = $file->getFileUri();
      assert(is_string($file_uri));
      $destination = $this->fileSystem->getDestinationFilename($this->fileSystem->dirname($file_uri) . '/' . $target_filename, FileExists::Rename);
      assert(is_string($destination));
      $destination_filename = pathinfo($destination, PATHINFO_FILENAME);
      $new_uri = str_replace($filename, $destination_filename, $file_uri);

      $input_file = $this->fileSystem->realpath($file_uri);
      if (!is_string($input_file)) {
        return NULL;
      }
      $output_file = $this->fileSystem->realpath($new_uri);
      if (!is_string($output_file)) {
        return NULL;
      }
      $translated_document_status = $deepl_translator->translateDocument($input_file, $output_file, $source_language, $target_language, $options);
      if ($translated_document_status->done()) {
        $file_storage = $this->entityTypeManager->getStorage('file');
        $translated_file = $file_storage->create(['uri' => $new_uri]);
        $translated_file->save();
        return $translated_file;
      }

      return NULL;
    }
    catch (\RuntimeException | DocumentTranslationException $e) {
      $this->messenger->addError($e->getMessage());
      return NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getUsageData(array $deepl_translators): array {
    $translator_storage = $this->entityTypeManager->getStorage('tmgmt_translator');
    $usage_data = [];
    foreach ($deepl_translators as $deepl_translator_id => $deepl_translator_name) {
      $name = 'tmgmt_deepl_' . $deepl_translator_id;
      $usage_data[$name] = [
        'title' => $this->t('TMGMT DeepL (:name)', [':name' => $deepl_translator_name]),
        'value' => $this->t('Usage information'),
      ];

      /** @var \Drupal\tmgmt\TranslatorInterface|null $deepl_translator */
      $deepl_translator = $translator_storage->load($deepl_translator_id);
      if ($deepl_translator === NULL) {
        continue;
      }

      $this->setTranslator($deepl_translator);
      $usage = $this->getUsage();
      if ($usage instanceof Usage) {
        $usage_data[$name]['description'] = $this->t(':usage_count of :usage_limit characters', [
          ':usage_count' => $usage->character->count ?? '',
          ':usage_limit' => $usage->character->limit ?? '',
        ]);
        $usage_data[$name]['severity'] = !$usage->anyLimitReached() ? REQUIREMENT_OK : REQUIREMENT_WARNING;
      }
      else {
        $usage_data[$name]['description'] = $this->t('Cannot retrieve usage information due to connectivity issues.');
        $usage_data[$name]['severity'] = REQUIREMENT_ERROR;
      }
    }
    return $usage_data;
  }

  /**
   * {@inheritDoc}
   */
  public function getUsage(): Usage|string {
    try {
      $deepl_translator = $this->getTranslator();
      return $deepl_translator->getUsage();
    }
    catch (\RuntimeException | DeepLException $e) {
      return $e->getMessage();
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getSourceLanguages(): array {
    try {
      $deepl_translator = $this->getTranslator();
      $source_languages = $deepl_translator->getSourceLanguages();
      // Build associative array of source languages.
      $languages = [];
      foreach ($source_languages as $source_language) {
        $languages[$source_language->code] = $source_language->name;
      }
      return $languages;
    }
    catch (\RuntimeException | DeepLException $exception) {
      return [];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getTargetLanguages(): array {
    try {
      $deepl_translator = $this->getTranslator();
      $target_languages = $deepl_translator->getTargetLanguages();
      // Build associative array of target languages.
      $languages = [];
      foreach ($target_languages as $target_language) {
        $languages[$target_language->code] = $target_language->name;
      }
      return $languages;
    }
    catch (\RuntimeException | DeepLException $exception) {
      return [];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function fixSourceLanguageMappings(string $source_lang): string {
    if (isset(self::LANGUAGE_MAPPING[$source_lang])) {
      return self::LANGUAGE_MAPPING[$source_lang];
    }

    return $source_lang;
  }

}
