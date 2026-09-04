<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ckeditor5\Kernel;

use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The CKEditor 5 plugin definition holds against core's validation.
 *
 * Pins the plugin definition and its conditions: a format with the filter
 * and the toolbar item validates, the toolbar item without the filter does
 * not, and the JavaScript plugin gets its URLs and token.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ckeditor5')]
#[RunTestsInSeparateProcesses]
final class TextEditorPairTest extends CKEditor5KernelTestBase {

  /**
   * The saved pair is valid and the plugin is enabled for it.
   */
  public function testValidPair(): void {
    $editor = Editor::load(self::FORMAT);
    $violations = $editor->getTypedData()->validate();
    self::assertCount(0, $violations, (string) $violations);
    self::assertArrayHasKey('ui_patterns_ckeditor5_component', $this->pluginManager()->getEnabledDefinitions($editor));
  }

  /**
   * The toolbar item needs the filter.
   */
  public function testToolbarItemWithoutFilter(): void {
    FilterFormat::load(self::FORMAT)->setFilterConfig('component_embed', ['status' => FALSE])->save();
    $editor = Editor::load(self::FORMAT);
    $violations = $editor->getTypedData()->validate();
    self::assertGreaterThan(0, \count($violations));
    self::assertStringContainsString('Embed components', (string) $violations->get(0)->getMessage());
    self::assertArrayNotHasKey('ui_patterns_ckeditor5_component', $this->pluginManager()->getEnabledDefinitions($editor));
  }

  /**
   * The JavaScript plugin gets the dialog and preview URLs of the format.
   */
  public function testDynamicPluginConfig(): void {
    $editor = Editor::load(self::FORMAT);
    $config = $this->pluginManager()->getCKEditor5PluginConfig($editor)['config']['drupalComponent'];
    // The site may live in a sub-directory.
    self::assertStringEndsWith('/editor/dialog/ui_patterns_ckeditor5/' . self::FORMAT, $config['dialogURL']);
    self::assertStringEndsWith('/editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, $config['previewURL']);
    self::assertNotEmpty($config['previewCsrfToken']);
    self::assertSame('90%', $config['dialogSettings']['width']);
  }

  /**
   * The CKEditor 5 plugin manager.
   */
  private function pluginManager(): CKEditor5PluginManagerInterface {
    return $this->container->get('plugin.manager.ckeditor5.plugin');
  }

}
