<?php

namespace Drupal\tmgmt_deepl;

use DeepL\TextResult;
use DeepL\Translator as DeepLTranslator;
use DeepL\Usage;
use Drupal\file\FileInterface;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Provides an interface defining the DeepL API service.
 */
interface DeeplTranslatorApiInterface {

  /**
   * Request to DeepL translate API endpoint.
   *
   * @param \Drupal\tmgmt\Entity\Translator $translator
   *   The tmgmt translator.
   * @param string[] $texts
   *   The array of text to be translated.
   * @param string $source_language
   *   The source language.
   * @param string $target_language
   *   The target language.
   * @param array $options
   *   (Optional) Additional options for translateText().
   * @param string|null $context
   *   (Optional) Context for translateText().
   *
   * @return \DeepL\TextResult|array|null
   *   Array of translated texts or NULL.
   */
  public function translate(Translator $translator, array $texts, string $source_language, string $target_language, array $options = [], ?string $context = NULL): TextResult|array|null;

  /**
   * Request to DeepL translate document API endpoint.
   *
   * @param \Drupal\tmgmt\Entity\Translator $translator
   *   The tmgmt translator.
   * @param \Drupal\file\FileInterface $file
   *   The file to be translated.
   * @param string $source_language
   *   The source language.
   * @param string $target_language
   *   The target language.
   * @param array $options
   *   Translation options to apply. See \DeepL\TranslateDocumentOptions.
   *
   * @return \Drupal\file\FileInterface|null
   *   A file if translation was successful, otherwise NULL.
   */
  public function translateDocument(Translator $translator, FileInterface $file, string $source_language, string $target_language, array $options = []): FileInterface|null;

  /**
   * Request to DeepL translate API endpoint.
   *
   * @return \DeepL\Usage|string
   *   The Usage object or error message.
   */
  public function getUsage(): Usage|string;

  /**
   * Get available source languages.
   *
   * @return array
   *   Associative array of available source languages.
   */
  public function getSourceLanguages(): array;

  /**
   * Get available target languages.
   *
   * @return array
   *   Associative array of available target languages.
   */
  public function getTargetLanguages(): array;

  /**
   * Get translator.
   */
  public function getTranslator(): ?DeepLTranslator;

  /**
   * Set translator for all glossary API calls.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity.
   */
  public function setTranslator(TranslatorInterface $translator): void;

  /**
   * Builds usage requirements for a set of DeepL translators.
   *
   * @param array $deepl_translators
   *   An associative array of translator IDs to labels.
   *
   * @return array
   *   The requirements array as expected by hook_requirements().
   */
  public function getUsageData(array $deepl_translators): array;

  /**
   * Source language mapping, cause not all sources as supported as target.
   *
   * @param string $source_lang
   *   The selected source language of the job item.
   *
   * @return string
   *   Fixed language mapping based on DeepL specification.
   */
  public function fixSourceLanguageMappings(string $source_lang): string;

}
