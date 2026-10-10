<?php

namespace Drupal\attribution\Plugin\Field\FieldType;

use Drupal\attribution\Entity\AttributionLicense;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'attribution' field type.
 */
#[FieldType(
  id: 'attribution',
  label: new TranslatableMarkup('Attribution'),
  description: new TranslatableMarkup('Field to store author and license attribution.'),
  category: 'general',
  default_widget: 'attribution_source_author_license',
  default_formatter: 'attribution_default',
)]
class AttributionItem extends FieldItemBase {

  const CREATION_TYPE_HUMAN = 'human_created';
  const CREATION_TYPE_AI_GENERATED = 'ai_generated';
  const CREATION_TYPE_AI_MODIFIED = 'ai_modified';

  /**
   * Returns the labels for the possible 'creation_type' values.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The creation type labels, keyed by creation type value.
   *
   * @infection-ignore-all
   *   Static label list.
   */
  public static function creationTypeOptions(): array {
    return [
      self::CREATION_TYPE_HUMAN => new TranslatableMarkup('Human-created'),
      self::CREATION_TYPE_AI_GENERATED => new TranslatableMarkup('AI-generated'),
      self::CREATION_TYPE_AI_MODIFIED => new TranslatableMarkup('AI-modified'),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @infection-ignore-all
   *   Static default settings.
   */
  public static function defaultFieldSettings(): array {
    return [
      'licenses' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    $licenses = AttributionLicense::loadMultiple();
    foreach ($licenses as $license) {
      $options[$license->getId()] = $license->getName();
    }
    $elements['licenses'] = [
      '#type' => 'select',
      '#title' => $this->t('Available licenses'),
      '#default_value' => $this->getSetting('licenses'),
      '#options' => $options,
      '#multiple' => TRUE,
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    if ($this->source_name !== NULL) {
      return FALSE;
    }

    if ($this->source_link !== NULL) {
      return FALSE;
    }

    if ($this->author_name !== NULL) {
      return FALSE;
    }

    if ($this->author_link !== NULL) {
      return FALSE;
    }

    if ($this->license !== NULL) {
      return FALSE;
    }

    if ($this->creation_type !== NULL) {
      return FALSE;
    }

    if ($this->ai_tool !== NULL) {
      return FALSE;
    }

    if ($this->ai_prompt !== NULL) {
      return FALSE;
    }

    if ($this->prompt_editor_name !== NULL) {
      return FALSE;
    }

    if ($this->prompt_editor_link !== NULL) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * @infection-ignore-all
   *   Static property definitions.
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['source_name'] = DataDefinition::create('string')
      ->setLabel(t('Source name'));
    $properties['source_link'] = DataDefinition::create('uri')
      ->setLabel(t('Source link'));
    $properties['author_name'] = DataDefinition::create('string')
      ->setLabel(t('Author name'));
    $properties['author_link'] = DataDefinition::create('uri')
      ->setLabel(t('Author link'));
    $properties['license'] = DataDefinition::create('string')
      ->setLabel(t('License'));
    $properties['creation_type'] = DataDefinition::create('string')
      ->setLabel(t('Creation type'));
    $properties['ai_tool'] = DataDefinition::create('string')
      ->setLabel(t('AI tool'));
    $properties['ai_prompt'] = DataDefinition::create('string')
      ->setLabel(t('AI prompt'));
    $properties['prompt_editor_name'] = DataDefinition::create('string')
      ->setLabel(t('Prompt editor'));
    $properties['prompt_editor_link'] = DataDefinition::create('uri')
      ->setLabel(t('Prompt editor link'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @infection-ignore-all
   *   Static column schema.
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $columns = [
      'source_name' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'source_link' => [
        'type' => 'varchar',
        'length' => 2048,
      ],
      'author_name' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'author_link' => [
        'type' => 'varchar',
        'length' => 2048,
      ],
      'license' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'creation_type' => [
        'type' => 'varchar',
        'length' => 32,
      ],
      'ai_tool' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'ai_prompt' => [
        'type' => 'text',
        'size' => 'big',
      ],
      'prompt_editor_name' => [
        'type' => 'varchar',
        'length' => 255,
      ],
      'prompt_editor_link' => [
        'type' => 'varchar',
        'length' => 2048,
      ],
    ];
    $schema = [
      'columns' => $columns,
      // @DCG Add indexes here if necessary.
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   *
   * @infection-ignore-all
   *   Random devel/preview sample data.
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $random = new Random();
    $tlds = [
      'academy', 'barefoot', 'coffee', 'digital', 'earth', 'foo', 'gives',
      'host', 'international', 'joy', 'kim', 'lgbt', 'meme', 'ngo', 'online',
      'pizza', 'qpon', 'rocks', 'software', 'team', 'university', 'vacations',
      'wtf', 'xyz', 'yoga', 'zone',
    ];

    $values['source_name'] = $random->word(random_int(3, 10)) . ' ' . $random->word(random_int(3, 10));

    $domain_length = random_int(7, 15);
    $protocol = random_int(0, 1) ? 'https' : 'http';
    $www = random_int(0, 1) ? 'www' : '';
    $domain = $random->word($domain_length);
    $tld = $tlds[random_int(0, (count($tlds) - 1))];
    $values['source_link'] = "$protocol://$www.$domain.$tld";

    $values['author_name'] = $random->word(random_int(3, 10)) . ' ' . $random->word(random_int(3, 10));

    $domain_length = random_int(7, 15);
    $protocol = random_int(0, 1) ? 'https' : 'http';
    $www = random_int(0, 1) ? 'www' : '';
    $domain = $random->word($domain_length);
    $tld = $tlds[random_int(0, (count($tlds) - 1))];
    $values['author_link'] = "$protocol://$www.$domain.$tld";

    $licenses = [
      'cc0_1_0',
      'cc_by_4_0',
      'cc_by_nc_4_0',
      'cc_by_nc_nd_4_0',
      'cc_by_nc_sa_4_0',
      'cc_by_nd_4_0',
      'cc_by_sa_4_0',
      'gpl_2_0_or_later',
    ];
    $values['license'] = $licenses[random_int(0, 7)];

    $creation_types = [
      self::CREATION_TYPE_HUMAN,
      self::CREATION_TYPE_AI_GENERATED,
      self::CREATION_TYPE_AI_MODIFIED,
    ];
    $values['creation_type'] = $creation_types[array_rand($creation_types)];
    if (in_array($values['creation_type'], [self::CREATION_TYPE_AI_GENERATED, self::CREATION_TYPE_AI_MODIFIED], TRUE)) {
      $values['ai_tool'] = $random->word(random_int(3, 10));
      $values['ai_prompt'] = $random->sentences(random_int(5, 15));
    }
    if ($values['creation_type'] === self::CREATION_TYPE_AI_MODIFIED) {
      $values['prompt_editor_name'] = $random->word(random_int(3, 10)) . ' ' . $random->word(random_int(3, 10));

      $domain_length = random_int(7, 15);
      $protocol = random_int(0, 1) ? 'https' : 'http';
      $www = random_int(0, 1) ? 'www' : '';
      $domain = $random->word($domain_length);
      $tld = $tlds[random_int(0, (count($tlds) - 1))];
      $values['prompt_editor_link'] = "$protocol://$www.$domain.$tld";
    }
    return $values;
  }

}
