<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Unit\Hook;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\term_glossary\Hook\TermGlossaryHooks;
use Drupal\term_glossary\Service\TermGlossaryManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TermGlossaryHooks class.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryHooks::class)]
#[Group('term_glossary')]
class TermGlossaryHooksTest extends UnitTestCase {

  /**
   * The mocked glossary manager.
   *
   * @var \Drupal\term_glossary\Service\TermGlossaryManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $glossaryManager;

  /**
   * The mocked renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $renderer;

  /**
   * The hooks class under test.
   *
   * @var \Drupal\term_glossary\Hook\TermGlossaryHooks
   */
  protected $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->glossaryManager = $this->createMock(TermGlossaryManagerInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);

    $this->hooks = new TermGlossaryHooks(
      $this->glossaryManager,
      $this->renderer,
    );

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')
      ->willReturnCallback(function ($input) {
        return $input->getUntranslatedString();
      });
    $this->hooks->setStringTranslation($translation);
  }

  /**
   * Tests compatible formatter delegates to manager.
   *
   * Uses a 'text_default' formatter (a compatible plugin ID).
   * Verifies that getFieldFormatterThirdPartySettingsElements()
   * is called once on the manager and its return value is
   * passed through as the form elements.
   */
  public function testFieldFormatterThirdPartySettingsFormCompatible(): void {
    $expected = ['glossary' => ['#type' => 'checkbox']];
    $plugin = $this->createMock(FormatterInterface::class);
    $plugin->method('getPluginId')->willReturn('text_default');

    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $form_state = $this->createMock(FormStateInterface::class);

    $this->glossaryManager->expects($this->once())
      ->method('getFieldFormatterThirdPartySettingsElements')
      ->with($plugin, $field_definition)
      ->willReturn($expected);

    $form_elements = $this->hooks->fieldFormatterThirdPartySettingsForm(
      $plugin, $field_definition, 'default', [], $form_state,
    );
    $this->assertSame($expected, $form_elements);
  }

  /**
   * Tests incompatible formatter returns empty array.
   *
   * Uses an 'entity_reference_label' formatter which is not
   * a text formatter. Verifies the manager method is never
   * called and an empty array is returned.
   */
  public function testFieldFormatterThirdPartySettingsFormIncompatible(): void {
    $plugin = $this->createMock(FormatterInterface::class);
    $plugin->method('getPluginId')->willReturn('entity_reference_label');

    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $form_state = $this->createMock(FormStateInterface::class);

    $this->glossaryManager->expects($this->never())
      ->method('getFieldFormatterThirdPartySettingsElements');

    $form_elements = $this->hooks->fieldFormatterThirdPartySettingsForm(
      $plugin, $field_definition, 'default', [], $form_state,
    );
    $this->assertSame([], $form_elements);
  }

  /**
   * Tests summary with glossary enabled and vocabularies.
   *
   * Mocks a 'text_default' formatter with glossary=TRUE and
   * two vocabularies (tags, topics). Verifies the summary
   * contains "Term glossary enabled." and a comma-separated
   * vocabulary list.
   */
  public function testFieldFormatterSettingsSummaryAlterEnabled(): void {
    $formatter = $this->createMock(FormatterInterface::class);
    $formatter->method('getPluginId')->willReturn('text_default');
    $formatter->method('getThirdPartySetting')
      ->willReturnMap([
        ['term_glossary', 'glossary', NULL, TRUE],
        [
          'term_glossary', 'glossary_vocabulary', NULL,
          [
            ['target_id' => 'tags'],
            ['target_id' => 'topics'],
          ],
        ],
      ]);

    $summary = [];
    $context = ['formatter' => $formatter];
    $this->hooks->fieldFormatterSettingsSummaryAlter($summary, $context);

    $this->assertCount(2, $summary);
    $this->assertEquals('Term glossary enabled.', (string) $summary[0]);
    $this->assertEquals(
      'Vocabularies: tags, topics',
      (string) $summary[1],
    );
  }

  /**
   * Tests summary when vocabulary is a scalar string.
   *
   * Mocks a 'text_trimmed' formatter with glossary=FALSE and
   * vocabulary as a plain string 'glossary_vocab' (legacy
   * format). Verifies only the singular "Vocabulary:" line
   * appears, and no "enabled" line since glossary is off.
   */
  public function testFieldFormatterSettingsSummaryAlterScalarVocabulary(): void {
    $formatter = $this->createMock(FormatterInterface::class);
    $formatter->method('getPluginId')->willReturn('text_trimmed');
    $formatter->method('getThirdPartySetting')
      ->willReturnMap([
        ['term_glossary', 'glossary', NULL, FALSE],
        ['term_glossary', 'glossary_vocabulary', NULL, 'glossary_vocab'],
      ]);

    $summary = [];
    $context = ['formatter' => $formatter];
    $this->hooks->fieldFormatterSettingsSummaryAlter($summary, $context);

    $this->assertCount(1, $summary);
    $this->assertEquals(
      'Vocabulary: glossary_vocab',
      (string) $summary[0],
    );
  }

  /**
   * Tests preprocess skips incompatible formatter.
   *
   * Sets #formatter to 'entity_reference_label'. Verifies
   * that getHandler() is never called — the hook returns
   * early without touching the field items.
   */
  public function testPreprocessFieldIncompatibleFormatter(): void {
    $variables = [
      'element' => ['#formatter' => 'entity_reference_label'],
      'items' => [],
    ];

    $this->glossaryManager->expects($this->never())
      ->method('getHandler');

    $this->hooks->preprocessField($variables);
  }

  /**
   * Tests preprocess applies markup, cache tags, and libraries.
   *
   * Mocks a 'text_default' formatter with one field item.
   * The manager's replaceFieldValue() returns replaced HTML
   * with count=1. Verifies: the item #markup is updated,
   * #type is set to 'markup', cache tags from both config
   * and the replacement are merged, and
   * attachLibrariesAndSettings() is called once.
   */
  public function testPreprocessFieldWithReplacements(): void {
    $markup = Markup::create('<p>Hello world</p>');

    $variables = [
      'element' => ['#formatter' => 'text_default'],
      'items' => [
        0 => ['content' => ['#markup' => $markup]],
      ],
    ];

    $handler = new \stdClass();
    $this->glossaryManager->method('getHandler')
      ->willReturn($handler);
    $this->glossaryManager->method('getVocabulariesFromFieldPreprocessVariables')
      ->willReturn(['tags']);

    $root_entity = $this->createMock(EntityInterface::class);
    $this->glossaryManager->method('getRootEntityFromFieldPreprocessVariables')
      ->willReturn($root_entity);

    $this->renderer->method('render')
      ->willReturn($markup);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getCacheTags')
      ->willReturn(['config:term_glossary.settings']);
    $this->glossaryManager->method('getConfig')
      ->willReturn($config);

    $this->glossaryManager->method('replaceFieldValue')
      ->with('<p>Hello world</p>', ['tags'], $root_entity)
      ->willReturn([
        'html' => '<p>Hello <span class="glossary">world</span></p>',
        'count' => 1,
        'cache_tags' => ['taxonomy_term:5'],
      ]);

    $this->glossaryManager->expects($this->once())
      ->method('attachLibrariesAndSettings');

    $this->hooks->preprocessField($variables);

    // The HTML is wrapped in a Markup object so the renderer does not run it
    // through Xss::filterAdmin() again (see #3606615).
    $this->assertInstanceOf(
      MarkupInterface::class,
      $variables['items'][0]['content']['#markup'],
    );
    $this->assertSame(
      '<p>Hello <span class="glossary">world</span></p>',
      (string) $variables['items'][0]['content']['#markup'],
    );

    // Verify cache tags were merged and applied.
    $this->assertContains(
      'config:term_glossary.settings',
      $variables['#cache']['tags'],
    );
    $this->assertContains(
      'taxonomy_term:5',
      $variables['#cache']['tags'],
    );
  }

}
