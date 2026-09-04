<?php

namespace Drupal\tmgmt_deepl\Plugin\tmgmt\Translator;

use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Defines an interface for the DeepL translator.
 */
interface DeeplTranslatorInterface {

  /**
   * Checks if the translator is available.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   *
   * @return \Drupal\tmgmt\Translator\AvailableResult
   *   The result.
   */
  public function checkAvailable(TranslatorInterface $translator): AvailableResult;

  /**
   * Requests translation for a job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   */
  public function requestTranslation(JobInterface $job): void;

  /**
   * Provides default remote languages mappings.
   *
   * @return array
   *   The mappings.
   */
  public function getDefaultRemoteLanguagesMappings(): array;

  /**
   * Lists supported remote languages for a translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   *
   * @return array
   *   Array of supported languages.
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator): array;

  /**
   * Lists supported target languages for a translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param string $source_language
   *   The source language.
   *
   * @return array
   *   Array of supported target languages.
   */
  public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language): array;

  /**
   * Source language mapping.
   *
   * @param string $source_lang
   *   The source language.
   *
   * @return string
   *   Fixed language mapping based on DeepL specification.
   */
  public function fixSourceLanguageMappings(string $source_lang): string;

  /**
   * Check if job has checkout settings.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   *
   * @return bool
   *   Returns TRUE, if checkout settings are available.
   */
  public function hasCheckoutSettings(JobInterface $job): bool;

  /**
   * Get default settings of the translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity.
   *
   * @return array
   *   Array of settings used while translating.
   */
  public function getDefaultSettings(TranslatorInterface $translator): array;

  /**
   * Requests translation for job items.
   *
   * @param \Drupal\tmgmt\JobItemInterface[] $job_items
   *   Array of job items.
   */
  public function requestJobItemsTranslation(array $job_items): void;

  /**
   * Returns a labeled list of DeepL translators.
   *
   * @return array
   *   A list of all DeepL translators.
   */
  public static function getTranslators(): array;

}
