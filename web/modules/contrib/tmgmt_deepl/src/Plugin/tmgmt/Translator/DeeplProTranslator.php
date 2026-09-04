<?php

namespace Drupal\tmgmt_deepl\Plugin\tmgmt\Translator;

/**
 * DeepL API Pro translator plugin.
 *
 * @TranslatorPlugin(
 *   id = "deepl_pro",
 *   label = @Translation("DeepL API Pro (deprecated)"),
 *   description = @Translation("DeepL API Pro Translator service."),
 *   ui = "Drupal\tmgmt_deepl\DeeplTranslatorUi",
 *   logo = "icons/deepl.svg",
 * )
 *
 * @deprecated in tmgmt_deepl:2.3.0 and is removed from tmgmt_deepl:2.4.0 - use deepl_api plugin instead.
 * @see https://www.drupal.org/node/3324073
 */
class DeeplProTranslator extends DeeplTranslator {

  /**
   * Translation service URL.
   *
   * @var string
   */
  protected string $translatorUrl = 'https://api.deepl.com/v2/translate';

  /**
   * Translation usage service URL.
   *
   * @var string
   */
  protected string $translatorUsageUrl = 'https://api.deepl.com/v2/usage';

  /**
   * Translation glossary service URL.
   *
   * @var string
   */
  protected string $translatorGlossaryUrl = 'https://api.deepl.com/v2/glossaries';

}
