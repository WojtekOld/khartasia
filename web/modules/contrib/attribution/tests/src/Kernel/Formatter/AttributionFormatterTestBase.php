<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Entity\AttributionLicense;
use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\Core\Field\FormatterInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Shared kernel test base for all Attribution field formatters.
 */
abstract class AttributionFormatterTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'attribution',
    'entity_test',
    'field',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'attribution']);
    assert($this->container !== NULL);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    FieldStorageConfig::create([
      'field_name' => 'field_attribution',
      'entity_type' => 'entity_test',
      'type' => 'attribution',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'field_attribution',
      'label' => 'Attribution',
    ])->save();
  }

  /**
   * Returns the formatter plugin ID under test.
   */
  abstract protected function getFormatterPluginId(): string;

  /**
   * Runs all formatter assertions in a single process.
   */
  public function testFormatterOutput(): void {
    $this->assertCssClasses();
    $this->assertNoLicenseMeansNoAttributesKey();
    $this->assertSourcePassedToRenderArray();
    $this->assertAuthorPassedToRenderArray();
    $this->assertCreationValuesPassedToRenderArray();
    $this->assertRenderFixtures();
  }

  // -------------------------------------------------------------------------
  // Sub-assertions — each may be overridden by concrete subclasses
  // -------------------------------------------------------------------------

  /**
   * Asserts CSS classes for OSI-certified and deprecated license states.
   */
  protected function assertCssClasses(): void {
    $elements = $this->viewElementsForValues(['license' => 'gpl_2_0_or_later']);
    $classes = $elements[0]['#attributes']['class'];
    $this->assertContains('attribution--license-is-osi-approved', $classes);
    $this->assertNotContains('attribution--license-not-osi-approved', $classes);

    AttributionLicense::create([
      'id' => 'test_deprecated',
      'identifier' => 'Test-Deprecated',
      'name' => 'Test Deprecated License',
      'osiCertified' => FALSE,
      'deprecated' => TRUE,
      'link' => '',
    ])->save();

    $elements = $this->viewElementsForValues(['license' => 'test_deprecated']);
    $classes = $elements[0]['#attributes']['class'];
    $this->assertContains('attribution--license-is-deprecated', $classes);
    $this->assertNotContains('attribution--license-not-deprecated', $classes);
  }

  /**
   * Asserts that without a license neither #attributes nor #license are set.
   */
  protected function assertNoLicenseMeansNoAttributesKey(): void {
    $elements = $this->viewElementsForValues(['source_name' => 'No License']);
    $this->assertArrayNotHasKey('#attributes', $elements[0]);
    $this->assertArrayNotHasKey('#license', $elements[0]);
  }

  /**
   * Asserts #source values for name-only, link-only, and both scenarios.
   */
  protected function assertSourcePassedToRenderArray(): void {
    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'source_name' => 'Example Source',
    ]);
    $this->assertArrayHasKey('#source', $elements[0]);
    $this->assertSame('Example Source', $elements[0]['#source']['name']);
    $this->assertNull($elements[0]['#source']['link']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'source_link' => 'https://example.com',
    ]);
    $this->assertArrayHasKey('#source', $elements[0]);
    $this->assertNull($elements[0]['#source']['name']);
    $this->assertSame('https://example.com', $elements[0]['#source']['link']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'source_name' => 'Example Source',
      'source_link' => 'https://example.com',
    ]);
    $this->assertArrayHasKey('#source', $elements[0]);
    $this->assertSame('Example Source', $elements[0]['#source']['name']);
    $this->assertSame('https://example.com', $elements[0]['#source']['link']);
  }

  /**
   * Asserts #author values for name-only, link-only, and both scenarios.
   */
  protected function assertAuthorPassedToRenderArray(): void {
    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'author_name' => 'Jane Doe',
    ]);
    $this->assertArrayHasKey('#author', $elements[0]);
    $this->assertSame('Jane Doe', $elements[0]['#author']['name']);
    $this->assertNull($elements[0]['#author']['link']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'author_link' => 'https://janedoe.example.com',
    ]);
    $this->assertArrayHasKey('#author', $elements[0]);
    $this->assertNull($elements[0]['#author']['name']);
    $this->assertSame('https://janedoe.example.com', $elements[0]['#author']['link']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'author_name' => 'Jane Doe',
      'author_link' => 'https://janedoe.example.com',
    ]);
    $this->assertArrayHasKey('#author', $elements[0]);
    $this->assertSame('Jane Doe', $elements[0]['#author']['name']);
    $this->assertSame('https://janedoe.example.com', $elements[0]['#author']['link']);
  }

  /**
   * Asserts #creation values for AI-generated and AI-modified scenarios.
   */
  protected function assertCreationValuesPassedToRenderArray(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0']);
    $this->assertArrayNotHasKey('#creation', $elements[0]);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_tool' => 'Midjourney v6',
      'ai_prompt' => 'A watercolor fox in a meadow',
    ]);
    $this->assertArrayHasKey('#creation', $elements[0]);
    $this->assertSame(AttributionItem::CREATION_TYPE_AI_GENERATED, $elements[0]['#creation']['type']);
    $this->assertSame('Midjourney v6', $elements[0]['#creation']['tool']);
    $this->assertSame('A watercolor fox in a meadow', $elements[0]['#creation']['prompt']);
    $this->assertNull($elements[0]['#creation']['editor']);
    $this->assertContains('attribution--creation-ai_generated', $elements[0]['#attributes']['class']);

    // The prompt editor is meaningful for ai_generated too: someone still
    // wrote the prompt, even though nothing pre-existed to "edit".
    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_tool' => 'Midjourney v6',
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
    $this->assertSame('Jane Doe', $elements[0]['#creation']['editor']['name']);
    $this->assertSame('https://editor.example.com', $elements[0]['#creation']['editor']['link']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
      'prompt_editor_name' => 'Jane Doe',
    ]);
    $this->assertArrayHasKey('#creation', $elements[0]);
    $this->assertSame(AttributionItem::CREATION_TYPE_AI_MODIFIED, $elements[0]['#creation']['type']);
    $this->assertSame('Jane Doe', $elements[0]['#creation']['editor']['name']);
    $this->assertNull($elements[0]['#creation']['editor']['link']);
    $this->assertContains('attribution--creation-ai_modified', $elements[0]['#attributes']['class']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
    $this->assertArrayHasKey('#creation', $elements[0]);
    $this->assertNull($elements[0]['#creation']['editor']['name']);
    $this->assertSame('https://editor.example.com', $elements[0]['#creation']['editor']['link']);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
    $this->assertArrayHasKey('#creation', $elements[0]);
    $this->assertSame('Jane Doe', $elements[0]['#creation']['editor']['name']);
    $this->assertSame('https://editor.example.com', $elements[0]['#creation']['editor']['link']);
  }

  /**
   * Asserts rendered HTML output against stored fixture files.
   */
  protected function assertRenderFixtures(): void {
    $this->assertMatchesHtmlFixture('license_only', ['license' => 'cc_by_4_0']);
    $this->assertMatchesHtmlFixture('license_without_link', ['license' => 'all_rights_reserved']);
    $this->assertMatchesHtmlFixture('full', [
      'license' => 'cc_by_4_0',
      'source_name' => 'Example Source',
      'source_link' => 'https://example.com/source',
      'author_name' => 'Jane Doe',
      'author_link' => 'https://example.com/author',
    ]);
    $this->assertMatchesHtmlFixture('ai_generated', [
      'license' => 'cc_by_4_0',
      'creation_type' => 'ai_generated',
      'ai_tool' => 'Midjourney v6',
      'ai_prompt' => 'A watercolor fox in a meadow',
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
    $this->assertMatchesHtmlFixture('ai_modified', [
      'license' => 'cc_by_4_0',
      'author_name' => 'Jane Doe',
      'creation_type' => 'ai_modified',
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Instantiates the formatter and calls viewElements() with the given values.
   *
   * @param array $values
   *   Field item values keyed by property name.
   * @param array $settings
   *   Formatter settings overriding the formatter's own defaultSettings().
   *
   * @return array
   *   The render array returned by viewElements().
   */
  protected function viewElementsForValues(array $values, array $settings = []): array {
    assert($this->container !== NULL);
    $field_definition = FieldConfig::loadByName('entity_test', 'entity_test', 'field_attribution');
    $formatter = $this->container->get('plugin.manager.field.formatter')->getInstance([
      'field_definition' => $field_definition,
      'view_mode' => 'default',
      'configuration' => [
        'type' => $this->getFormatterPluginId(),
        'label' => 'hidden',
        'settings' => $settings,
        'third_party_settings' => [],
      ],
    ]);
    $this->assertInstanceOf(FormatterInterface::class, $formatter);

    $entity = EntityTest::create();
    $entity->set('field_attribution', $values);
    $entity->save();

    return $formatter->viewElements($entity->get('field_attribution'), 'en');
  }

  /**
   * Renders a single render array element to an HTML string.
   *
   * @param array $element
   *   A render array element (e.g. $elements[0] from viewElements()).
   *
   * @return string
   *   The rendered HTML.
   */
  protected function renderElement(array $element): string {
    assert($this->container !== NULL);
    return (string) $this->container->get('renderer')->renderRoot($element);
  }

  /**
   * Returns the absolute path to a fixture file for the given scenario.
   *
   * @param string $scenario
   *   Fixture filename without extension (e.g. 'license_only').
   *
   * @return string
   *   Absolute path to the fixture file.
   */
  protected function getFixturePath(string $scenario): string {
    return dirname(__DIR__, 3)
      . '/fixtures/formatter/'
      . $this->getFormatterPluginId()
      . '/'
      . $scenario
      . '.html';
  }

  /**
   * Asserts that rendered HTML matches a stored fixture.
   *
   * On the first run (fixture absent) or when UPDATE_FIXTURES=1 is set the
   * fixture is written to disk and the assertion is skipped. On subsequent
   * runs the rendered output is compared character-for-character.
   *
   * @param string $scenario
   *   Fixture filename without extension (e.g. 'license_only').
   * @param array $values
   *   Field item values to pass to viewElementsForValues().
   * @param array $settings
   *   Formatter settings to pass to viewElementsForValues().
   */
  protected function assertMatchesHtmlFixture(string $scenario, array $values, array $settings = []): void {
    $html = $this->normalizeFixtureHtml($this->renderElement($this->viewElementsForValues($values, $settings)));
    $fixturePath = $this->getFixturePath($scenario);

    if (!file_exists($fixturePath) || (bool) getenv('UPDATE_FIXTURES')) {
      $directory = dirname($fixturePath);
      if (!is_dir($directory)) {
        mkdir($directory, 0777, TRUE);
      }
      file_put_contents($fixturePath, $html);
      return;
    }

    $this->assertStringEqualsFile(
      $fixturePath, $html, sprintf(
        'Rendered HTML does not match fixture %s/%s.html',
        $this->getFormatterPluginId(),
        $scenario,
      )
    );
  }

  /**
   * Normalizes rendered HTML before comparing against/writing a fixture.
   *
   * No-op by default. Override when the formatter's output embeds
   * environment-specific values (e.g. a module's install path) that need
   * to be replaced with a stable placeholder before the fixture
   * comparison.
   *
   * @param string $html
   *   The rendered HTML.
   *
   * @return string
   *   The normalized HTML.
   */
  protected function normalizeFixtureHtml(string $html): string {
    return $html;
  }

}
