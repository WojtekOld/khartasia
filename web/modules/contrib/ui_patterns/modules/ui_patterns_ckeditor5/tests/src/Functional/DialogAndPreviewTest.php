<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ckeditor5\Functional;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\Component\Serialization\Json;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\ui_patterns_ckeditor5\Controller\PreviewController;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The routes behind the editor, as a logged-in user reaches them.
 *
 * Pins what the JavaScript plugin relies on: the host entity on the
 * textarea, the plugin configuration in drupalSettings, the dialog for
 * inserting and for editing, the preview with its CSRF token, and the
 * access rules of both routes.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ckeditor5')]
#[RunTestsInSeparateProcesses]
final class DialogAndPreviewTest extends BrowserTestBase {

  private const FORMAT = 'component_test';

  private const COMPONENT = 'ui_patterns_test:test-component';

  private const OTHER_COMPONENT = 'ui_patterns_test:test-form-component';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The user of the editor.
   */
  private UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    FilterFormat::create([
      'format' => self::FORMAT,
      'name' => 'Component test',
      'filters' => ['component_embed' => ['status' => TRUE, 'weight' => 100]],
    ])->save();
    Editor::create([
      'format' => self::FORMAT,
      'editor' => 'ckeditor5',
      'settings' => ['toolbar' => ['items' => ['drupalComponent']], 'plugins' => []],
      'image_upload' => ['status' => FALSE],
    ])->save();
    FilterFormat::create(['format' => 'other', 'name' => 'Other'])->save();
    $this->user = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit own page content',
      'use text format ' . self::FORMAT,
      'use text format other',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * The node form gives the editor its host entity and its configuration.
   */
  public function testEditorPage(): void {
    $this->drupalGet('node/add/page');
    $assert = $this->assertSession();
    $assert->elementAttributeContains('css', 'textarea#edit-body-0-value', 'data-ui-patterns-ckeditor5-entity', '"type":"node"');
    $assert->elementAttributeContains('css', 'textarea#edit-body-0-value', 'data-ui-patterns-ckeditor5-entity', '"id":null');
    $assert->elementAttributeContains('css', 'textarea#edit-body-0-value', 'data-ui-patterns-ckeditor5-entity', '"bundle":"page"');
    $config = $this->getDrupalSettings()['editor']['formats'][self::FORMAT]['editorSettings']['config']['drupalComponent'];
    // The site may live in a sub-directory.
    self::assertStringEndsWith('/editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT, $config['dialogURL']);
    self::assertNotEmpty($config['previewCsrfToken']);
    $assert->responseContains('ui_patterns_ckeditor5/js/build/drupalComponent.js');

    $node = Node::create(['type' => 'page', 'title' => 'Saved', 'uid' => $this->user->id()]);
    $node->save();
    $this->drupalGet('node/' . $node->id() . '/edit');
    $assert->elementAttributeContains('css', 'textarea#edit-body-0-value', 'data-ui-patterns-ckeditor5-entity', '"id":"' . $node->id() . '"');
  }

  /**
   * The dialog inserts, or edits when it gets a component configuration.
   */
  public function testDialog(): void {
    $assert = $this->assertSession();
    $this->drupalGet('editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT);
    $assert->statusCodeEquals(200);
    $assert->titleEquals('Insert component | Drupal');
    $assert->optionExists('component[component_id]', self::COMPONENT);
    $assert->buttonNotExists('Embed');

    $this->post('editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT, [
      'component_config' => Json::encode(['component_id' => self::COMPONENT]),
    ]);
    $assert->statusCodeEquals(200);
    $assert->titleEquals('Edit component: UI Patterns Test component | Drupal');
    $assert->pageTextContains('UI Patterns Test component');
    $assert->elementExists('css', 'select[name="component[variant_id][source_id]"]');
    $assert->buttonExists('Embed');
  }

  /**
   * Both routes need the text format to use the filter, and the permission.
   */
  public function testAccess(): void {
    $assert = $this->assertSession();
    $this->drupalGet('editor/dialog/ui_patterns_ckeditor5/other');
    $assert->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet('editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT);
    $assert->statusCodeEquals(403);
    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE);
    $assert->statusCodeEquals(403);
  }

  /**
   * The preview renders for the user carrying the token of their session.
   */
  public function testPreview(): void {
    $assert = $this->assertSession();
    $this->drupalGet('node/add/page');
    $token = $this->getDrupalSettings()['editor']['formats'][self::FORMAT]['editorSettings']['config']['drupalComponent']['previewCsrfToken'];
    $content = Json::encode([
      'component_id' => self::COMPONENT,
      'component_settings' => ['props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'Hello']]]],
    ]);

    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE, $content, $token);
    $assert->statusCodeEquals(200);
    $assert->responseMatches('#ui-patterns-props-string">\s*Hello\s*<#');
    $assert->responseHeaderEquals('Drupal-Component-Label', 'UI Patterns Test component');

    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE, $content, 'wrong');
    $assert->statusCodeEquals(403);
    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE, $content);
    $assert->statusCodeEquals(403);
  }

  /**
   * The text format form lists the components by extension and saves the list.
   *
   * The format has a CKEditor 5 editor, so saving also goes through its
   * validation.
   */
  public function testSettingsForm(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer filters']));
    $this->drupalGet('admin/config/content/formats/manage/' . self::FORMAT);
    $assert = $this->assertSession();
    $checkbox = 'filters[component_embed][settings][allowed_components][ui_patterns_test][components][' . self::COMPONENT . ']';
    $assert->fieldExists($checkbox);
    $assert->elementTextContains('xpath', '//input[@name="' . $checkbox . '"]/ancestor::details[1]/summary', 'UI Patterns Test');
    $this->submitForm([$checkbox => TRUE], 'Save configuration');
    $assert->pageTextContains('The text format Component test has been updated.');
    $settings = FilterFormat::load(self::FORMAT)->filters('component_embed')->getConfiguration()['settings'];
    self::assertSame([self::COMPONENT], $settings['allowed_components']);

    $this->drupalGet('admin/config/content/formats/manage/' . self::FORMAT);
    $assert->checkboxChecked($checkbox);
    $this->submitForm([$checkbox => FALSE], 'Save configuration');
    $settings = FilterFormat::load(self::FORMAT)->filters('component_embed')->getConfiguration()['settings'];
    self::assertSame([], $settings['allowed_components']);
  }

  /**
   * The dialog and the preview refuse a component the text format forbids.
   *
   * Nested too: the selector inside a slot only offers allowed components,
   * and the preview renders nothing for a nested forbidden one.
   */
  public function testAllowedComponents(): void {
    $format = FilterFormat::load(self::FORMAT);
    $format->setFilterConfig('component_embed', [
      'status' => TRUE,
      'weight' => 100,
      'settings' => ['allowed_components' => [self::COMPONENT]],
    ]);
    $format->save();
    $assert = $this->assertSession();
    $dialog = 'editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT;
    $this->drupalGet($dialog);
    $assert->optionExists('component[component_id]', self::COMPONENT);
    $assert->optionNotExists('component[component_id]', self::OTHER_COMPONENT);

    $this->post($dialog, ['component_config' => Json::encode(['component_id' => self::OTHER_COMPONENT])]);
    $assert->pageTextContains('This component is not allowed by the text format.');
    $assert->buttonNotExists('Embed');

    $nested = static fn (string $component_id): array => [
      'component_id' => self::COMPONENT,
      'slots' => [
        'slot' => [
          'sources' => [
            [
              'source_id' => 'component',
              'source' => [
                'component' => [
                  'component_id' => $component_id,
                  'props' => ['string' => ['source_id' => 'textfield', 'source' => ['value' => 'Nested']]],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
    $this->post($dialog, ['component_config' => Json::encode($nested(self::OTHER_COMPONENT))]);
    $assert->buttonExists('Embed');
    $nested_select = 'component[slots][slot][sources][0][source][component][component_id]';
    $assert->optionExists($nested_select, self::COMPONENT);
    $assert->optionNotExists($nested_select, 'ui_patterns_test:test-wrapper-component');

    $this->drupalGet('node/add/page');
    $token = $this->getDrupalSettings()['editor']['formats'][self::FORMAT]['editorSettings']['config']['drupalComponent']['previewCsrfToken'];
    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE, Json::encode(['component_id' => self::OTHER_COMPONENT]), $token);
    $assert->statusCodeEquals(403);
    $preview = static fn (array $settings): string => Json::encode([
      'component_id' => self::COMPONENT,
      'component_settings' => $settings,
    ]);
    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE, $preview($nested(self::OTHER_COMPONENT)), $token);
    $assert->statusCodeEquals(200);
    $assert->responseNotContains('Nested');
    $this->post('editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, [], TRUE, $preview($nested(self::COMPONENT)), $token);
    $assert->responseContains('Nested');
  }

  /**
   * Posts to a path, as the JavaScript plugin does.
   *
   * A NULL content sends the parameters; a content replaces them.
   */
  private function post(string $path, array $parameters, bool $json = FALSE, ?string $content = NULL, ?string $token = NULL): void {
    $server = [];
    if ($json) {
      $server['CONTENT_TYPE'] = 'application/json';
    }
    if ($token !== NULL) {
      $server['HTTP_' . \strtoupper(\str_replace('-', '_', PreviewController::CSRF_HEADER))] = $token;
    }
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof BrowserKitDriver) {
      self::fail('The test needs the BrowserKit driver.');
    }
    $driver->getClient()->request('POST', $this->buildUrl($path), $parameters, [], $server, $content);
  }

}
