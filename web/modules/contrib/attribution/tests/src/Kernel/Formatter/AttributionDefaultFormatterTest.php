<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Entity\AttributionLicense;
use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionDefaultFormatter;
use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\field\Entity\FieldConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionDefaultFormatter.
 *
 * Unlike the other formatters, AttributionDefaultFormatter is configurable
 * and pre-renders its values into #parts/#creation markup rather than
 * exposing raw #source/#author/#creation pairs, so the inherited
 * assertSourcePassedToRenderArray()/assertAuthorPassedToRenderArray()/
 * assertCreationValuesPassedToRenderArray() are overridden to assert
 * against the rendered HTML instead.
 *
 * @group attribution
 */
#[CoversClass(AttributionDefaultFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionDefaultFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_default';
  }

  /**
   * {@inheritdoc}
   *
   * Core's details.html.twig differs in whitespace between the <summary>
   * and its content across Drupal core versions/themes, which would
   * otherwise make the AI prompt details element fail a byte-exact fixture
   * comparison on some but not all supported core versions. Only
   * whitespace runs that contain a newline are collapsed — that is
   * template-indentation noise, never the template's own deliberate
   * single-space {{ ' ' }} separators between statements, which never
   * contain a newline and are left untouched.
   */
  protected function normalizeFixtureHtml(string $html): string {
    assert($this->container !== NULL);
    $module_path = $this->container->get('extension.list.module')->getPath('attribution');
    $html = str_replace(base_path() . $module_path . '/assets/', 'MODULE_ASSETS/', $html);
    $html = preg_replace('/>\s*\n\s*/', '>', $html) ?? $html;
    return preg_replace('/\s*\n\s*</', '<', $html) ?? $html;
  }

  /**
   * {@inheritdoc}
   */
  protected function assertSourcePassedToRenderArray(): void {
    $elements = $this->viewElementsForValues(['source_name' => 'Example Source']);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<span class="attribution--source">Example Source</span>', $html);

    $elements = $this->viewElementsForValues(['source_link' => 'https://example.com']);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<a class="attribution--source" href="https://example.com">https://example.com</a>', $html);

    $elements = $this->viewElementsForValues([
      'source_name' => 'Example Source',
      'source_link' => 'https://example.com',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<a class="attribution--source" href="https://example.com">Example Source</a>', $html);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertAuthorPassedToRenderArray(): void {
    $elements = $this->viewElementsForValues(['author_name' => 'Jane Doe']);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<span class="attribution--author">Jane Doe</span>', $html);

    $elements = $this->viewElementsForValues(['author_link' => 'https://janedoe.example.com']);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<a class="attribution--author" href="https://janedoe.example.com">https://janedoe.example.com</a>', $html);

    $elements = $this->viewElementsForValues([
      'author_name' => 'Jane Doe',
      'author_link' => 'https://janedoe.example.com',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<a class="attribution--author" href="https://janedoe.example.com">Jane Doe</a>', $html);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertCreationValuesPassedToRenderArray(): void {
    $elements = $this->viewElementsForValues(['source_name' => 'No Creation']);
    $this->assertArrayNotHasKey('#creation', $elements[0]);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_tool' => 'Midjourney v6',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertArrayHasKey('#creation', $elements[0]);
    $this->assertContains('attribution--creation-ai_generated', $elements[0]['#attributes']['class']);
    $this->assertStringContainsString('AI-generated', $html);
    $this->assertStringContainsString('<em class="attribution--tool">Midjourney v6</em>', $html);

    // The prompt editor is meaningful for ai_generated too: someone still
    // wrote the prompt, even though nothing pre-existed to "edit".
    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<a class="attribution--editor" href="https://editor.example.com">Jane Doe</a>', $html);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
      'prompt_editor_name' => 'Jane Doe',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertContains('attribution--creation-ai_modified', $elements[0]['#attributes']['class']);
    $this->assertStringContainsString('AI-modified', $html);
    $this->assertStringContainsString('<span class="attribution--editor">Jane Doe</span>', $html);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
      'prompt_editor_link' => 'https://editor.example.com',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<a class="attribution--editor" href="https://editor.example.com">https://editor.example.com</a>', $html);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertRenderFixtures(): void {
    $this->assertMatchesHtmlFixture('license_only', ['license' => 'cc_by_4_0']);
    $this->assertMatchesHtmlFixture('full', [
      'license' => 'cc_by_nc_4_0',
      'source_name' => 'Example Source',
      'source_link' => 'https://example.com/source',
      'author_name' => 'Jane Doe',
      'author_link' => 'https://example.com/author',
    ]);
    $this->assertMatchesHtmlFixture('full_configured', [
      'license' => 'cc_by_nc_4_0',
      'source_name' => 'Example Source',
      'source_link' => 'https://example.com/source',
      'author_name' => 'Jane Doe',
      'author_link' => 'https://example.com/author',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_tool' => 'Midjourney v6',
      'ai_prompt' => 'A watercolor fox in a meadow',
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ], [
      'use_identifier' => TRUE,
      'cc_icon' => TRUE,
      'divider' => TRUE,
      'ai_icon' => TRUE,
      'show_prompt' => TRUE,
    ]);
  }

  /**
   * Tests that #parts only contains keys for the fields actually present.
   */
  public function testPartsPresence(): void {
    $elements = $this->viewElementsForValues(['source_name' => 'Only Source']);
    $this->assertArrayNotHasKey('#attributes', $elements[0]);
    $this->assertArrayHasKey('source', $elements[0]['#parts']);
    $this->assertArrayNotHasKey('license', $elements[0]['#parts']);
    $this->assertArrayNotHasKey('author', $elements[0]['#parts']);
  }

  /**
   * Tests the "Licensed" / "This work is" lead-ins.
   *
   * Present in word mode, absent in divider mode, and absent entirely
   * when their group has nothing to show.
   */
  public function testLeadIns(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0']);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('Licensed', $html);
    $this->assertStringNotContainsString('<div> ', $html);

    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0'], ['divider' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('Licensed', $html);
    $this->assertStringNotContainsString('<div> ', $html);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('Licensed', $html);
    $this->assertStringContainsString('This work is', $html);
    $this->assertStringNotContainsString('<div> ', $html);
  }

  /**
   * Tests that statements get a real, screen-reader-visible full stop.
   *
   * The license/author/source statement and the creation statement each
   * need actual announced punctuation — not just a decorative divider —
   * regardless of the divider setting, since the boundary between two
   * statements must be audible.
   */
  public function testAccessibleStops(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0']);
    $html = $this->renderElement($elements[0]);
    $this->assertSame(1, substr_count($html, 'attribution--stop">.</span>'));

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertSame(1, substr_count($html, 'attribution--stop">.</span>'));

    foreach ([['divider' => FALSE], ['divider' => TRUE]] as $settings) {
      $elements = $this->viewElementsForValues([
        'license' => 'cc_by_4_0',
        'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      ], $settings);
      $html = $this->renderElement($elements[0]);
      $this->assertSame(2, substr_count($html, 'attribution--stop">.</span>'));
    }
  }

  /**
   * Tests the use_identifier setting toggles identifier vs. name.
   *
   * Also checks that the expected title attribute is added when the
   * identifier stands in for the name.
   */
  public function testUseIdentifierSetting(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_nc_4_0'], ['use_identifier' => FALSE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('Creative Commons Attribution Non Commercial 4.0 International', $html);
    // The identifier still appears in the license's href (an SPDX URL), so
    // check it is absent as the visible label rather than absent outright.
    $this->assertStringNotContainsString('>CC-BY-NC-4.0<', $html);
    $this->assertStringNotContainsString('title=', $html);

    $elements = $this->viewElementsForValues(['license' => 'cc_by_nc_4_0'], ['use_identifier' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('>CC-BY-NC-4.0<', $html);
    $this->assertStringContainsString('title="Creative Commons Attribution Non Commercial 4.0 International"', $html);
  }

  /**
   * Tests cc_icon rendering for a mapped Creative Commons license.
   */
  public function testCcIconMapped(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_nc_4_0'], ['cc_icon' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('assets/creative-commons/by-nc.svg', $html);
    $this->assertStringContainsString('alt="Creative Commons Attribution Non Commercial 4.0 International"', $html);
  }

  /**
   * Tests cc_icon falls back to text instead of a broken image.
   *
   * Covers a CC-like license with no mapped icon.
   */
  public function testCcIconUnmappedFallback(): void {
    AttributionLicense::create([
      'id' => 'cc_future_1_0',
      'identifier' => 'CC-FUTURE-1.0',
      'name' => 'Creative Commons Future License 1.0',
      'osiCertified' => FALSE,
      'deprecated' => FALSE,
      'link' => 'https://example.com/cc-future',
    ])->save();

    $elements = $this->viewElementsForValues(['license' => 'cc_future_1_0'], ['cc_icon' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('<img', $html);
    $this->assertStringContainsString('Creative Commons Future License 1.0', $html);
  }

  /**
   * Tests that a version bump of a supported CC family still resolves.
   *
   * E.g. CC-BY-4.1 should resolve to the existing icon without any code
   * change, since the icon slug is derived from the identifier with the
   * version segment stripped.
   */
  public function testCcIconVersionAgnostic(): void {
    AttributionLicense::create([
      'id' => 'cc_by_4_1',
      'identifier' => 'CC-BY-4.1',
      'name' => 'Creative Commons Attribution 4.1 International',
      'osiCertified' => FALSE,
      'deprecated' => FALSE,
      'link' => 'https://example.com/cc-by-4-1',
    ])->save();

    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_1'], ['cc_icon' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('assets/creative-commons/by.svg', $html);
  }

  /**
   * Tests non-CC licenses never render an icon even if cc_icon is enabled.
   */
  public function testCcIconIgnoredForNonCcLicense(): void {
    $elements = $this->viewElementsForValues(['license' => 'gpl_2_0_or_later'], ['cc_icon' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('<img', $html);
  }

  /**
   * Tests divider mode replaces connector words with divider markup.
   */
  public function testDividerSetting(): void {
    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'author_name' => 'Jane Doe',
    ], ['divider' => FALSE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('by', $html);
    $this->assertStringNotContainsString('attribution--divider', $html);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'author_name' => 'Jane Doe',
    ], ['divider' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('attribution--divider attribution--divider-license-author', $html);
  }

  /**
   * Tests ai_icon rendering for both AI creation types.
   */
  public function testAiIconSetting(): void {
    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
    ], ['ai_icon' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('assets/ai/ai_generated.svg', $html);
    $this->assertStringContainsString('alt="AI-generated"', $html);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_MODIFIED,
    ], ['ai_icon' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('assets/ai/ai_modified.svg', $html);
    $this->assertStringContainsString('alt="AI-modified"', $html);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
    ], ['ai_icon' => FALSE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('<img', $html);
    $this->assertStringContainsString('AI-generated', $html);

    // No settings at all: the plugin's own default ('ai_icon' => FALSE)
    // must apply, not just the explicit FALSE case above.
    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('<img', $html);
  }

  /**
   * Tests show_prompt toggles the AI prompt details element.
   */
  public function testShowPromptSetting(): void {
    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_prompt' => 'A watercolor fox in a meadow',
    ], ['show_prompt' => FALSE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('A watercolor fox in a meadow', $html);

    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_prompt' => 'A watercolor fox in a meadow',
    ], ['show_prompt' => TRUE]);
    $html = $this->renderElement($elements[0]);
    // The exact whitespace between </summary> and the prompt text differs
    // across core versions/themes (details.html.twig), so check both
    // pieces are present rather than assuming they are directly adjacent.
    $this->assertStringContainsString('<summary role="button" aria-expanded="false">AI prompt</summary>', $html);
    $this->assertStringContainsString('A watercolor fox in a meadow', $html);

    // No settings at all: the plugin's own default ('show_prompt' => FALSE)
    // must apply, not just the explicit FALSE case above.
    $elements = $this->viewElementsForValues([
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_prompt' => 'A watercolor fox in a meadow',
    ]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringNotContainsString('A watercolor fox in a meadow', $html);
  }

  /**
   * Tests label_toggle wraps the content in a details element.
   */
  public function testLabelToggleSetting(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0'], ['label_toggle' => FALSE]);
    $this->assertArrayNotHasKey('#label_display', $elements);
    $this->assertStringNotContainsString('<details', $this->renderElement($elements[0]));

    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0'], ['label_toggle' => TRUE]);
    $this->assertSame('hidden', $elements['#label_display']);
    $html = $this->renderElement($elements[0]);
    // Same 'details' render element as the AI prompt in
    // buildCreationParts(), so it carries the same js-form-wrapper/
    // form-wrapper classes and role/aria-expanded attributes.
    $this->assertStringContainsString('<details class="attribution--toggle attribution attribution--license-cc_by_4_0 attribution--license-not-osi-approved attribution--license-not-deprecated js-form-wrapper form-wrapper">', $html);
    $this->assertStringContainsString('role="button"', $html);
    $this->assertStringContainsString('aria-expanded="false"', $html);
    $this->assertStringContainsString('<span class="attribution--toggle-label">Attribution</span>', $html);
  }

  /**
   * Tests label_toggle uses the field's own label when one is set.
   *
   * The field configured in setUp() happens to be labeled "Attribution" —
   * the same text as the "Attribution" fallback used when there is no
   * label — so testLabelToggleSetting() alone cannot tell whether the
   * toggle text came from the field label or from the fallback. Give the
   * field a distinct label here to prove the field label wins.
   */
  public function testLabelToggleUsesFieldLabelWhenPresent(): void {
    assert($this->container !== NULL);
    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_attribution');
    assert($field_config instanceof FieldConfig);
    $field_config->setLabel('Custom Label')->save();

    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0'], ['label_toggle' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<span class="attribution--toggle-label">Custom Label</span>', $html);
  }

  /**
   * Tests label_toggle falls back to "Attribution" when the label is empty.
   *
   * Complements testLabelToggleUsesFieldLabelWhenPresent(): with an empty
   * field label, the toggle text must come from the "Attribution"
   * fallback rather than rendering an empty toggle.
   */
  public function testLabelToggleFallsBackToDefaultLabelWhenFieldLabelEmpty(): void {
    assert($this->container !== NULL);
    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_attribution');
    assert($field_config instanceof FieldConfig);
    $field_config->setLabel('')->save();

    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0'], ['label_toggle' => TRUE]);
    $html = $this->renderElement($elements[0]);
    $this->assertStringContainsString('<span class="attribution--toggle-label">Attribution</span>', $html);
  }

}
