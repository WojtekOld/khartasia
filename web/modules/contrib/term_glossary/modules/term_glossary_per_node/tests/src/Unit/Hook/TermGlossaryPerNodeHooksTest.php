<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary_per_node\Unit\Hook;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\term_glossary_per_node\Hook\TermGlossaryPerNodeHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TermGlossaryPerNodeHooks class.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryPerNodeHooks::class)]
#[Group('term_glossary')]
class TermGlossaryPerNodeHooksTest extends UnitTestCase {

  /**
   * The hooks class under test.
   *
   * @var \Drupal\term_glossary_per_node\Hook\TermGlossaryPerNodeHooks
   */
  protected TermGlossaryPerNodeHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type_manager = $this->createMock(
      EntityTypeManagerInterface::class,
    );
    $current_user = $this->createMock(AccountProxyInterface::class);

    $this->hooks = new TermGlossaryPerNodeHooks(
      $entity_type_manager,
      $current_user,
    );

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')
      ->willReturnCallback(function ($input) {
        return $input->getUntranslatedString();
      });
    $this->hooks->setStringTranslation($translation);

    // BaseFieldDefinition::create() needs the container.
    $typed_data_manager = $this->createMock(
      TypedDataManagerInterface::class,
    );
    $typed_data_manager->method('getDefaultConstraints')
      ->willReturn([]);
    // getClass() falls back to typedDataManager->getDefinition.
    $typed_data_manager->method('getDefinition')
      ->willReturnCallback(function ($type) {
        $boolean_class = 'Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem';
        $string_class = 'Drupal\Core\Field\Plugin\Field\FieldType\StringItem';
        $map = [
          'field_item:boolean' => ['class' => $boolean_class],
          'field_item:string' => ['class' => $string_class],
        ];
        return $map[$type] ?? ['class' => \stdClass::class];
      });

    $boolean_class = 'Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem';
    $string_class = 'Drupal\Core\Field\Plugin\Field\FieldType\StringItem';
    $field_type_manager = $this->createMock(
      FieldTypePluginManagerInterface::class,
    );
    $field_type_manager->method('getDefaultStorageSettings')
      ->willReturn([]);
    $field_type_manager->method('getDefaultFieldSettings')
      ->willReturn([]);
    $field_type_manager->method('getDefinition')
      ->willReturnCallback(function ($type) use ($boolean_class, $string_class) {
        $map = [
          'boolean' => ['class' => $boolean_class],
          'string' => ['class' => $string_class],
        ];
        return $map[$type] ?? NULL;
      });
    $field_type_manager->method('getPluginClass')
      ->willReturnCallback(function ($type) use ($boolean_class, $string_class) {
        $map = [
          'boolean' => $boolean_class,
          'string' => $string_class,
        ];
        return $map[$type] ?? NULL;
      });

    $container = new ContainerBuilder();
    $container->set('typed_data_manager', $typed_data_manager);
    $container->set(
      'plugin.manager.field.field_type', $field_type_manager,
    );
    \Drupal::setContainer($container);
  }

  /**
   * Tests entityBaseFieldInfo returns fields for node type.
   *
   * Passes an entity type with id='node'. Verifies two base
   * fields are returned: 'term_glossary_active' (boolean)
   * and 'term_glossary_vocabulary' (string).
   */
  public function testEntityBaseFieldInfoNode(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('node');

    $fields = $this->hooks->entityBaseFieldInfo($entity_type);

    $this->assertCount(2, $fields);
    $this->assertArrayHasKey(
      'term_glossary_active', $fields,
    );
    $this->assertArrayHasKey(
      'term_glossary_vocabulary', $fields,
    );
  }

  /**
   * Tests entityBaseFieldInfo returns empty for non-node type.
   *
   * Passes an entity type with id='taxonomy_term'. Verifies
   * an empty array is returned — per-node fields are only
   * added to node entities.
   */
  public function testEntityBaseFieldInfoNonNode(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('taxonomy_term');

    $fields = $this->hooks->entityBaseFieldInfo($entity_type);

    $this->assertEmpty($fields);
  }

}
