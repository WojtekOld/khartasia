<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionary;

/**
 * Tests the DeepL multilingual glossary entities.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary
 * @covers \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionary
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryEntityTest extends DeeplGlossaryKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('deepl_ml_glossary_dictionary');

    Translator::create([
      'name' => 'deepl_api_test',
      'label' => 'DeepL API Test',
      'plugin' => 'deepl_api',
      'settings' => ['auth_key_entity' => ''],
    ])->save();
  }

  /**
   * Creates and saves a glossary entity.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary
   *   The saved glossary.
   */
  protected function createGlossary(): DeeplMultilingualGlossary {
    $glossary = DeeplMultilingualGlossary::create([
      'label' => 'Test glossary',
      'tmgmt_translator' => 'deepl_api_test',
      'glossary_id' => 'deepl-uuid-123',
    ]);
    $glossary->save();
    return $glossary;
  }

  /**
   * Tests glossary entity getters.
   */
  public function testGlossaryGetters(): void {
    $glossary = $this->createGlossary();
    $this->assertSame('deepl-uuid-123', $glossary->getGlossaryId());

    $translator = $glossary->getTranslator();
    $this->assertNotNull($translator);
    $this->assertSame('deepl_api_test', $translator->id());
  }

  /**
   * Tests ::getGlossaryId without a stored DeepL glossary id.
   */
  public function testGlossaryIdEmpty(): void {
    $glossary = DeeplMultilingualGlossary::create([
      'label' => 'No remote id',
      'tmgmt_translator' => 'deepl_api_test',
    ]);
    $glossary->save();
    $this->assertNull($glossary->getGlossaryId());
  }

  /**
   * Tests dictionary creation, preSave label and getters.
   */
  public function testDictionary(): void {
    $glossary = $this->createGlossary();
    $dictionary = DeeplMultilingualGlossaryDictionary::create([
      'glossary_id' => $glossary->id(),
      'source_lang' => 'en',
      'target_lang' => 'de',
      'entry_count' => 2,
      'entries' => [
        ['subject' => 'Hello', 'definition' => 'Hallo'],
        ['subject' => 'World', 'definition' => 'Welt'],
      ],
    ]);
    $dictionary->save();

    // The label is derived in ::preSave.
    $this->assertSame('en -> de', (string) $dictionary->label());
    $this->assertSame('en', $dictionary->getSourceLanguage());
    $this->assertSame('de', $dictionary->getTargetLanguage());
    $this->assertSame(['Hello' => 'Hallo', 'World' => 'Welt'], $dictionary->getEntries());
    $this->assertSame(2, $dictionary->getEntryCount());

    $related_glossary = $dictionary->getGlossary();
    $this->assertNotNull($related_glossary);
    $this->assertSame($glossary->id(), $related_glossary->id());
  }

  /**
   * Tests ::getGlossary returns NULL when no glossary is referenced.
   */
  public function testDictionaryGetGlossaryNull(): void {
    $dictionary = DeeplMultilingualGlossaryDictionary::create([
      'source_lang' => 'en',
      'target_lang' => 'de',
    ]);
    $this->assertNull($dictionary->getGlossary());
  }

  /**
   * Tests the static ::getAllowedLanguages delegates to the helper service.
   */
  public function testDictionaryGetAllowedLanguages(): void {
    $languages = DeeplMultilingualGlossaryDictionary::getAllowedLanguages();
    $this->assertArrayHasKey('EN', $languages);
  }

  /**
   * Tests that deleting a glossary deletes its dictionaries.
   */
  public function testGlossaryDeleteCascades(): void {
    $glossary = $this->createGlossary();
    foreach ([['en', 'de'], ['en', 'fr']] as $pair) {
      DeeplMultilingualGlossaryDictionary::create([
        'glossary_id' => $glossary->id(),
        'source_lang' => $pair[0],
        'target_lang' => $pair[1],
        'entries' => [['subject' => 'Hello', 'definition' => 'X']],
      ])->save();
    }
    $storage = $this->container->get('entity_type.manager')->getStorage('deepl_ml_glossary_dictionary');
    $this->assertCount(2, $storage->loadByProperties(['glossary_id' => $glossary->id()]));

    $glossary_id = $glossary->id();
    $glossary->delete();
    $this->assertCount(0, $storage->loadByProperties(['glossary_id' => $glossary_id]));
  }

}
