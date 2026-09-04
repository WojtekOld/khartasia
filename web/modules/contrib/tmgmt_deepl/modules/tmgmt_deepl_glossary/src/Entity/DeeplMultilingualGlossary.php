<?php

namespace Drupal\tmgmt_deepl_glossary\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the DeepL glossary entity.
 *
 * @ContentEntityType(
 *   id = "deepl_ml_glossary",
 *   label = @Translation("DeepL glossary"),
 *   label_singular = @Translation("DeepL glossary"),
 *   label_plural = @Translation("DeepL glossaries"),
 *   handlers = {
 *     "access" = "Drupal\tmgmt_deepl_glossary\AccessControlHandler",
 *     "list_builder" = "Drupal\tmgmt_deepl_glossary\Controller\DeeplMultilingualGlossaryListBuilder",
 *     "views_data" = "Drupal\tmgmt_deepl_glossary\Entity\ViewsData\DeeplMultilingualGlossaryViewsData",
 *     "form" = {
 *       "default" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryForm",
 *       "add" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryForm",
 *       "edit" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryForm",
 *       "delete" = "Drupal\tmgmt_deepl_glossary\Form\DeeplMultilingualGlossaryDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "tmgmt_deepl_ml_glossary",
 *   admin_permission = "administer deepl_glossary entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "add-form" = "/admin/tmgmt/deepl_glossaries/add",
 *     "edit-form" = "/admin/tmgmt/deepl_glossaries/manage/{deepl_ml_glossary}/edit",
 *     "delete-form" = "/admin/tmgmt/deepl_glossaries/manage/{deepl_ml_glossary}/delete",
 *     "collection" = "/admin/tmgmt/deepl_glossaries",
 *   },
 * )
 */
class DeeplMultilingualGlossary extends ContentEntityBase implements DeeplMultilingualGlossaryInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // The machine name of the translator.
    $fields['tmgmt_translator'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Translator'))
      ->setDescription(t('The tmgmt translator.'))
      ->setSetting('allowed_values_function', '\Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator::getTranslators')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // Name associated with the glossary.
    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the glossary.'))
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->setDisplayOptions('form', [
        'type' => 'string',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The user id of the current user.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The author of the glossary entry.'))
      ->setSetting('target_type', 'user')
      ->setReadOnly(TRUE);

    // A unique ID assigned to the glossary (values is retrieved by DeepL API)
    $fields['glossary_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Glossary Id'))
      ->setDescription(t('The glossary id.'));

    // The time that the entity was created.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    // The time that the entity was changed.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last changed.'));

    // @phpstan-ignore-next-line
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getGlossaryId(): ?string {
    return is_string($this->get('glossary_id')->value) ? $this->get('glossary_id')->value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslator(): ?TranslatorInterface {
    $tmgmt_translator_storage = $this->entityTypeManager()->getStorage('tmgmt_translator');
    $translator = $this->get('tmgmt_translator')->value;
    assert(is_string($translator));
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $tmgmt_translator_storage->load($translator);
    return $translator;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    // Set uid to current user.
    $this->set('uid', self::getDefaultEntityOwner());
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    parent::delete();
    // Delete all corresponding glossary dictionaries.
    $storage = $this->entityTypeManager()->getStorage('deepl_ml_glossary_dictionary');
    $dictionaries = $storage->loadByProperties(['glossary_id' => $this->id()]);
    foreach ($dictionaries as $dictionary) {
      $dictionary->delete();
    }
  }

}
