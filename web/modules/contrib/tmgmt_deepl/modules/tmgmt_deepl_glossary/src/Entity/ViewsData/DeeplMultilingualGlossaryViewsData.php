<?php

namespace Drupal\tmgmt_deepl_glossary\Entity\ViewsData;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the deepl_ml_glossary entity type.
 */
class DeeplMultilingualGlossaryViewsData extends EntityViewsData {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Set custom filter for tmgmt_translator.
    /** @var array{
     *    'tmgmt_deepl_ml_glossary': array{
     *      'tmgmt_translator': array<string, array<string, string>>,
     *      'related_dictionaries': array{
     *        'title': string,
     *        'help': string,
     *        'field': array{
     *        'id': string,
     *         ...
     *       }
     *     },
     *   }
     * } $data */
    $data['tmgmt_deepl_ml_glossary']['tmgmt_translator']['filter']['id'] = 'tmgmt_deepl_glossary_allowed_translators';

    // Related dictionaries.
    $data['tmgmt_deepl_ml_glossary']['related_dictionaries'] = [
      'title' => $this->t('Related Dictionaries'),
      'help' => $this->t('Display linked names of related deepl_ml_glossary_dictionary entities.'),
      'field' => [
        'id' => 'deepl_ml_glossary_related_dictionaries',
      ],
    ];
    return $data;
  }

}
