<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\Hook\UiIconsHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ui_icons hook implementations.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(UiIconsHooks::class)]
#[Group('ui_icons')]
class UiIconsHooksTest extends UnitTestCase {

  /**
   * Builds the hooks class with an active theme of the given name.
   *
   * @param string $active_theme
   *   Machine name of the active theme.
   * @param array $base_themes
   *   Machine names of the base themes it inherits from.
   *
   * @return \Drupal\ui_icons\Hook\UiIconsHooks
   *   The hook implementations.
   */
  private function hooks(string $active_theme = 'stark', array $base_themes = []): UiIconsHooks {
    $theme = $this->createMock(ActiveTheme::class);
    $theme->method('getName')->willReturn($active_theme);
    $theme->method('getBaseThemeExtensions')->willReturn(array_fill_keys($base_themes, TRUE));

    $theme_manager = $this->createMock(ThemeManagerInterface::class);
    $theme_manager->method('getActiveTheme')->willReturn($theme);

    $hooks = new UiIconsHooks($theme_manager);
    $hooks->setStringTranslation($this->getStringTranslationStub());

    return $hooks;
  }

  /**
   * Tests the theme hooks the module declares.
   */
  public function testTheme(): void {
    $theme = $this->hooks()->theme([], 'module', 'ui_icons', '');

    $this->assertSame(['render element' => 'element'], $theme['icon_selector']);
    foreach (['pack_id', 'icon_id', 'icon_label', 'settings'] as $variable) {
      $this->assertArrayHasKey($variable, $theme['icon_preview']['variables']);
    }
  }

  /**
   * Tests the help text is only returned for the module help route.
   */
  public function testHelp(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $help = $this->hooks()->help('help.page.ui_icons', $route_match);
    $this->assertStringContainsString('UI Icons', (string) $help);

    $this->assertNull($this->hooks()->help('help.page.node', $route_match));
  }

  /**
   * Data provider for ::testPreprocessAttachesThemeLibrary().
   */
  public static function themeLibraryProvider(): array {
    return [
      'default admin' => ['default_admin', 'ui_icons/ui_icons.default_admin_autocomplete'],
      'gin' => ['gin', 'ui_icons/ui_icons.gin_autocomplete'],
      'daisyui' => ['ui_suite_daisyui', 'ui_icons/ui_icons.daisyui_autocomplete'],
      'dsfr' => ['ui_suite_dsfr', 'ui_icons/ui_icons.dsfr_autocomplete'],
    ];
  }

  /**
   * Tests each supported admin theme gets its own stylesheet.
   */
  #[DataProvider('themeLibraryProvider')]
  public function testPreprocessAttachesThemeLibrary(string $theme, string $library): void {
    $variables = $this->preprocess($this->hooks($theme), ['icon_id' => ['#value' => 'test_path:foo']]);

    $this->assertSame([$library], $variables['icon_form']['#attached']['library']);
  }

  /**
   * Tests a sub-theme inherits the stylesheet of its base theme.
   */
  public function testPreprocessAttachesLibraryForSubTheme(): void {
    $hooks = $this->hooks('my_gin_child', ['gin']);
    $variables = $this->preprocess($hooks, ['icon_id' => ['#value' => 'test_path:foo']]);

    $this->assertSame(['ui_icons/ui_icons.gin_autocomplete'], $variables['icon_form']['#attached']['library']);
  }

  /**
   * Tests an unrelated theme gets no stylesheet.
   */
  public function testPreprocessAttachesNothingForUnknownTheme(): void {
    $variables = $this->preprocess($this->hooks('olivero'), ['icon_id' => ['#value' => 'test_path:foo']]);

    $this->assertArrayNotHasKey('#attached', $variables['icon_form']);
  }

  /**
   * Tests `pack_id` and `icon_id` resolved from the submitted element value.
   */
  public function testPreprocessFromSubmittedValue(): void {
    $variables = $this->preprocess($this->hooks(), ['icon_id' => ['#value' => 'test_path:foo']]);

    $this->assertSame('test_path', $variables['pack_id']);
    $this->assertSame('foo', $variables['icon_id']);
  }

  /**
   * Tests resolution from the default value when nothing was submitted.
   */
  public function testPreprocessFromDefaultValue(): void {
    $variables = $this->preprocess($this->hooks(), ['#default_value' => 'test_svg:bar']);

    $this->assertSame('test_svg', $variables['pack_id']);
    $this->assertSame('bar', $variables['icon_id']);
  }

  /**
   * Tests resolution from an icon object carried by the element value.
   */
  public function testPreprocessFromIconObject(): void {
    $icon = $this->createMock(IconDefinitionInterface::class);
    $icon->method('getPackId')->willReturn('test_path');
    $icon->method('getId')->willReturn('baz');

    $variables = $this->preprocess($this->hooks(), ['#value' => ['object' => $icon]]);

    $this->assertSame('test_path', $variables['pack_id']);
    $this->assertSame('baz', $variables['icon_id']);
  }

  /**
   * Tests the submitted value wins over the default value.
   */
  public function testPreprocessSubmittedValueWinsOverDefault(): void {
    $variables = $this->preprocess($this->hooks(), [
      'icon_id' => ['#value' => 'test_path:foo'],
      '#default_value' => 'test_svg:bar',
    ]);

    $this->assertSame('test_path', $variables['pack_id']);
    $this->assertSame('foo', $variables['icon_id']);
  }

  /**
   * Tests nothing is resolved when the element carries no icon at all.
   */
  public function testPreprocessWithoutAnyValue(): void {
    $variables = $this->preprocess($this->hooks(), []);

    $this->assertArrayNotHasKey('pack_id', $variables);
    $this->assertSame('', $variables['icon_form']);
  }

  /**
   * Tests an unparsable icon id stops preprocessing before the settings form.
   */
  public function testPreprocessWithInvalidIdSkipsSettings(): void {
    $variables = $this->preprocess($this->hooks(), [
      'icon_id' => ['#value' => 'not-an-icon-id'],
      'icon_settings' => ['#type' => 'details'],
      '#show_settings' => TRUE,
    ]);

    $this->assertArrayNotHasKey('pack_id', $variables);
    $this->assertArrayNotHasKey('settings_form', $variables);
  }

  /**
   * Tests the settings sub-form is exposed only when settings are enabled.
   */
  public function testPreprocessSettingsForm(): void {
    $element = [
      'icon_id' => ['#value' => 'test_settings:foo'],
      'icon_settings' => ['#type' => 'details'],
    ];

    $variables = $this->preprocess($this->hooks(), $element + ['#show_settings' => TRUE]);
    $this->assertTrue($variables['has_settings']);
    $this->assertSame(['#type' => 'details'], $variables['settings_form']);

    $variables = $this->preprocess($this->hooks(), $element + ['#show_settings' => FALSE]);
    $this->assertFalse($variables['has_settings']);
    $this->assertArrayNotHasKey('settings_form', $variables);
  }

  /**
   * Runs the preprocess hook over an element and returns the variables.
   *
   * @param \Drupal\ui_icons\Hook\UiIconsHooks $hooks
   *   The hook implementations.
   * @param array $element
   *   The `icon_selector` element.
   *
   * @return array
   *   The preprocessed template variables.
   */
  private function preprocess(UiIconsHooks $hooks, array $element): array {
    $variables = ['element' => $element];
    $hooks->preprocessIconSelector($variables);

    return $variables;
  }

}
