<?php

namespace Drupal\Tests\bibcite_import\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\bibcite_entity\Entity\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Basic import tests.
 */
#[RunTestsInSeparateProcesses]
#[Group('bibcite')]
class ImportBasicTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'serialization',
    'bibcite',
    'bibcite_entity',
    'bibcite_import',
    'bibcite_bibtex',
    'bibcite_ris',
  ];

  /**
   * Bibcite format manager service.
   *
   * @var \Drupal\bibcite\Plugin\BibciteFormatManagerInterface
   */
  protected $formatManager;

  /**
   * Serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('bibcite_keyword');
    $this->installEntitySchema('bibcite_contributor');

    $this->installConfig([
      'system',
      'user',
      'serialization',
      'bibcite',
      'bibcite_import',
      'bibcite_bibtex',
      'bibcite_ris',
    ]);

    $this->formatManager = $this->container->get('plugin.manager.bibcite_format');
    $this->serializer = $this->container->get('serializer');
  }

  /**
   * Test if export formats available after enabling modules.
   */
  #[DataProvider('importData')]
  public function testAvailableFormats($format) {
    $this->assertTrue($this->formatManager->hasDefinition($format));
  }

  /**
   * Test decode and denormalization from available text formats to entity.
   */
  #[DataProvider('importData')]
  public function testReferenceDeserialization($format, $text, $expected_type, $entity_expected_values) {
    $entries = $this->serializer->decode($text, $format);

    foreach ($entries as $entry) {
      /** @var \Drupal\bibcite_entity\Entity\Reference $entity */
      $entity = $this->serializer->denormalize($entry, Reference::class, $format);
      $this->assertTrue($entity instanceof Reference);
      $this->assertEquals($expected_type, $entity->type->target_id);
      $this->assertEntityValues($entity, $entity_expected_values);
    }
  }

  /**
   * Check if values in the provided entity equal to expected values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param array $expected_values
   *   List of expected values.
   */
  protected function assertEntityValues(EntityInterface $entity, array $expected_values) {
    foreach ($expected_values as $field_name => $expected_value) {
      if (!in_array($field_name, ['author', 'editor', 'keywords'])) {
        /** @var Reference $entity */
        $this->assertNotEmpty($entity->get($field_name));
        $this->assertEquals($expected_value, $entity->{$field_name}->value);
      }
      if (in_array($field_name, ['author', 'editor'])) {
        /** @var \Drupal\bibcite_entity\Entity\Reference $entity */
        $field_item_list = $entity->get('author');
        $contributors_by_role = [];
        foreach ($field_item_list as $field) {
          /** @var \Drupal\bibcite_entity\Entity\ContributorInterface $contributor */
          if ($contributor = $field->entity) {
            switch ($field->role) {
              case 'editor':
              case 'series_editor':
                $contributors_by_role['editor'][] = $contributor->getName();
                break;

              case 'recipient':
              case 'translator':
                $contributors_by_role[$field->role][] = $contributor->getName();
                break;

              default:
                $contributors_by_role['author'][] = $contributor->getName();
                break;
            }
          }
        }
        $serialized = serialize($contributors_by_role);
        $this->assertTrue(in_array($expected_value, $contributors_by_role[$field_name]), "The value '$expected_value' is one of the authors in $serialized");
      }
      if ($field_name === 'keywords') {
        /** @var \Drupal\bibcite_entity\Entity\Reference $entity */
        $field_item_list = $entity->get('keywords');
        $keywords = [];
        foreach ($field_item_list as $field) {
          $keywords[] = $field->entity ? $field->entity->label() : NULL;
        }
        $serialized = serialize($keywords);
        $this->assertTrue(in_array($expected_value, $keywords), "The value '$expected_value' is one of the keywords in $serialized");
      }
    }
  }

  /**
   * Get test data from YAML.
   *
   * @return array
   *   Data for import test.
   */
  public static function importData() {
    $yaml_text = file_get_contents(__DIR__ . '/data/ImportBasicTest.data.yml');
    return Yaml::parse($yaml_text);
  }

}
