<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Unit\Plugin\views\field;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Plugin\views\field\DeeplMultilingualGlossaryRelatedDictionaries;
use Drupal\views\ResultRow;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplMultilingualGlossaryRelatedDictionaries views field.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Plugin\views\field\DeeplMultilingualGlossaryRelatedDictionaries
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryRelatedDictionariesTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Creates the field plugin under test with the given dependencies.
   */
  protected function createPlugin(EntityTypeManagerInterface $entity_type_manager, ?LoggerChannelFactoryInterface $logger_factory = NULL): RelatedDictionaries_Test {
    /** @var \Drupal\Tests\tmgmt_deepl_glossary\Unit\Plugin\views\field\RelatedDictionaries_Test $plugin */
    $plugin = (new \ReflectionClass(RelatedDictionaries_Test::class))->newInstanceWithoutConstructor();
    $plugin->setEntityTypeManager($entity_type_manager);
    $plugin->setLoggerFactory($logger_factory ?? $this->createMock(LoggerChannelFactoryInterface::class));
    $plugin->setStringTranslation($this->getStringTranslationStub());
    return $plugin;
  }

  /**
   * Builds an entity type manager whose dictionary query returns given ids.
   *
   * @param array<int|string> $ids
   *   The dictionary ids the query should return.
   * @param array<\Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface> $dictionaries
   *   The dictionaries returned by loadMultiple.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The configured entity type manager.
   */
  protected function entityTypeManagerReturning(array $ids, array $dictionaries): EntityTypeManagerInterface&MockObject {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn($ids);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($dictionaries);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('deepl_ml_glossary_dictionary')->willReturn($storage);
    return $entity_type_manager;
  }

  /**
   * Tests the static ::create factory.
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    $container->set('logger.factory', $this->createMock(LoggerChannelFactoryInterface::class));
    $plugin = DeeplMultilingualGlossaryRelatedDictionaries::create($container, [], 'deepl_ml_glossary_related_dictionaries', []);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DeeplMultilingualGlossaryRelatedDictionaries::class, $plugin);
  }

  /**
   * Tests trivial handler hooks.
   */
  public function testHandlerHooks(): void {
    $plugin = $this->createPlugin($this->createMock(EntityTypeManagerInterface::class));
    $this->assertFalse($plugin->usesGroupBy());
    // query() is a no-op and must not throw.
    $plugin->query();
    $this->addToAssertionCount(1);
  }

  /**
   * Tests ::render builds a linked item list.
   */
  public function testRenderWithLinks(): void {
    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary->method('label')->willReturn('en -> de');
    $dictionary->method('id')->willReturn(10);
    $dictionary->method('toUrl')->willReturn($this->createMock(Url::class));

    $plugin = $this->createPlugin($this->entityTypeManagerReturning([10], [$dictionary]));
    $plugin->setOptionsPublic(['link_to_entity' => TRUE]);
    $plugin->entityToReturn = $this->createMock(DeeplMultilingualGlossaryInterface::class);

    $build = $plugin->render($this->createMock(ResultRow::class));
    // @phpstan-ignore-next-line
    $this->assertIsArray($build);
    // @phpstan-ignore-next-line
    $this->assertSame('item_list', $build['#theme']);
    // @phpstan-ignore-next-line
    $items = $build['#items'];
    $this->assertIsArray($items);
    $this->assertCount(1, $items);
    $first = $items[0];
    $this->assertIsArray($first);
    $this->assertSame('link', $first['#type']);
  }

  /**
   * Tests ::render builds a plain markup list without links.
   */
  public function testRenderWithoutLinks(): void {
    $dictionary = $this->createMock(DeeplMultilingualGlossaryDictionaryInterface::class);
    $dictionary->method('label')->willReturn('en -> fr');
    $dictionary->expects($this->never())->method('toUrl');

    $plugin = $this->createPlugin($this->entityTypeManagerReturning([11], [$dictionary]));
    $plugin->setOptionsPublic(['link_to_entity' => FALSE]);
    $plugin->entityToReturn = $this->createMock(DeeplMultilingualGlossaryInterface::class);

    $build = $plugin->render($this->createMock(ResultRow::class));
    // @phpstan-ignore-next-line
    $this->assertIsArray($build);
    // @phpstan-ignore-next-line
    $items = $build['#items'];
    $this->assertIsArray($items);
    $first = $items[0];
    $this->assertIsArray($first);
    $this->assertSame('en -> fr', $first['#markup']);
  }

  /**
   * Tests ::render returns an empty string when there are no dictionaries.
   */
  public function testRenderEmpty(): void {
    $plugin = $this->createPlugin($this->entityTypeManagerReturning([], []));
    $plugin->setOptionsPublic(['link_to_entity' => TRUE]);
    $plugin->entityToReturn = $this->createMock(DeeplMultilingualGlossaryInterface::class);

    $this->assertSame('', $plugin->render($this->createMock(ResultRow::class)));
  }

  /**
   * Tests ::getRelatedDictionaries logs and returns [] on failure.
   */
  public function testGetRelatedDictionariesException(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('error');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('tmgmt_deepl_glossary')->willReturn($logger);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willThrowException(new \RuntimeException('boom'));

    $plugin = $this->createPlugin($entity_type_manager, $logger_factory);

    $glossary = $this->createMock(DeeplMultilingualGlossaryInterface::class);
    $this->assertSame([], $plugin->getRelatedDictionariesPublic($glossary));
  }

}

// @codingStandardsIgnoreStart

/**
 * Testable subclass exposing protected members and a stubbed entity loader.
 */
class RelatedDictionaries_Test extends DeeplMultilingualGlossaryRelatedDictionaries {

  /**
   * The entity returned by the overridden ::getEntity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  public $entityToReturn = NULL;

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeManager(\Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager): void {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setLoggerFactory(\Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory): void {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptionsPublic(array $options): void {
    $this->options = $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(ResultRow $values) {
    return $this->entityToReturn;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedDictionariesPublic(DeeplMultilingualGlossaryInterface $glossary): array {
    return $this->getRelatedDictionaries($glossary);
  }

}
// @codingStandardsIgnoreEnd
