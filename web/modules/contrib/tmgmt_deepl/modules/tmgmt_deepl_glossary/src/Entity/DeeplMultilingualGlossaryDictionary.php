<?php

namespace Drupal\tmgmt_deepl_glossary\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the DeepL multilingual glossary dictionary entity.
 *
 * @ContentEntityType(
 *   id = "deepl_ml_glossary_dictionary",
 *   label = @Translation("DeepL glossary dictionary"),
 *   label_singular = @Translation("DeepL glossary dictionary"),
 *   label_plural = @Translation("DeepL glossary dictionaries"),
 *   handlers = {
 *     "access" = "Drupal\tmgmt_deepl_glossary\AccessControlHandler",
 *     "list_builder" = "Drupal\tmgmt_deepl_glossary\Controller\DeeplMultilingualGlossaryListBuilder",
 *     "views_data" = "Drupal\tmgmt_deepl_glossary\Entity\ViewsData\DeeplMultilingualGlossaryViewsData",
 *     "form" = {
 *       "default" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryForm",
 *       "add" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryForm",
 *       "edit" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryForm",
 *       "delete" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDictionaryDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "tmgmt_deepl_ml_glossary_dictionary",
 *   admin_permission = "administer deepl_glossary entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "label" = "label",
 *   },
 *   links = {
 *     "add-form" = "/admin/tmgmt/deepl_glossaries/{deepl_ml_glossary}/add",
 *     "edit-form" = "/admin/tmgmt/deepl_glossaries/manage/dictionary/{deepl_ml_glossary_dictionary}/edit",
 *     "delete-form" = "/admin/tmgmt/deepl_glossaries/manage/dictionary/{deepl_ml_glossary_dictionary}/delete",
 *     "collection" = "/admin/tmgmt/deepl_glossaries/{deepl_ml_glossary}/dictionaries",
 *   },
 * )
 */
class DeeplMultilingualGlossaryDictionary extends ContentEntityBase implements DeeplMultilingualGlossaryDictionaryInterface {

  use EntityOwnerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Name associated to the dictionary.
    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('Name of the dictionary.'))
      ->setReadOnly(TRUE);

    // The user id of the current user.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The author of the glossary entry.'))
      ->setSetting('target_type', 'user')
      ->setReadOnly(TRUE);

    // The time that the entity was created.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    // The glossary id of the dictionary.
    $fields['glossary_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Glossary'))
      ->setDescription(t('The glossary of the dictionary.'))
      ->setSetting('target_type', 'deepl_ml_glossary')
      ->setReadOnly(TRUE);

    // The source language.
    $fields['source_lang'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Source language'))
      ->setDescription(t('The language in which the source texts in the dictionary are specified.'))
      ->setSetting('allowed_values_function', static::class . '::getAllowedLanguages')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The target language.
    $fields['target_lang'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Target language'))
      ->setDescription(t('The language in which the target texts in the dictionary are specified.'))
      ->setSetting('allowed_values_function', static::class . '::getAllowedLanguages')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The number of entries in the glossary.
    $fields['entry_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entry count'))
      ->setDescription(t('The number of entries in the glossary.'))
      ->setReadOnly(TRUE);

    // The format in which the glossary entries are provided.
    $fields['entries_format'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Entries format'))
      ->setDescription(t('The format in which the glossary entries are provided.'))
      ->setSetting('allowed_values', [['tsv' => 'text/tab-separated-values']])
      ->setReadOnly(TRUE)
      ->setRequired(TRUE)
      ->setDefaultValue('tsv');

    // The entries of the glossary dictionary.
    $fields['entries'] = BaseFieldDefinition::create('deepl_glossary_item')
      ->setLabel(t('Entries'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDescription(t('The entries of the glossary dictionary.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'deepl_glossary_item',
      ]);

    // @phpstan-ignore-next-line
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getGlossary(): ?DeeplMultilingualGlossaryInterface {
    $glossary = $this->get('glossary_id')->entity ?? NULL;
    if (!$glossary instanceof DeeplMultilingualGlossaryInterface) {
      return NULL;
    }

    return $glossary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguage(): string {
    /** @var string $source_lang */
    $source_lang = $this->get('source_lang')->value;
    return $source_lang;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguage(): string {
    /** @var string $target_lang */
    $target_lang = $this->get('target_lang')->value;
    return $target_lang;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntries(): array {
    $entries = $this->get('entries');
    $entries_ar = [];
    foreach ($entries as $entry) {
      $values = $entry->getValue();
      if (is_array($values) && isset($values['subject']) && isset($values['definition'])) {
        assert(is_string($values['subject']));
        $entries_ar[$values['subject']] = $values['definition'];
      }
    }

    return $entries_ar;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntryCount(): ?int {
    /** @var int $entry_count */
    $entry_count = is_int($this->get('entry_count')->value) ? $this->get('entry_count')->value : NULL;
    return $entry_count;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    // @codeCoverageIgnoreStart
    parent::preSave($storage);

    // Set uid to current user.
    $this->set('uid', self::getDefaultEntityOwner());
    // Set correct label.
    $label = $this->t('@source_lang -> @target_lang', [
      '@source_lang' => $this->getSourceLanguage(),
      '@target_lang' => $this->getTargetLanguage(),
    ]);
    $this->set('label', $label);
    // @codeCoverageIgnoreEnd
  }

  /**
   * {@inheritdoc}
   */
  public static function getAllowedLanguages(): array {
    return \Drupal::service('tmgmt_deepl_glossary.ml.helper')->getAllowedLanguages();
  }

}
