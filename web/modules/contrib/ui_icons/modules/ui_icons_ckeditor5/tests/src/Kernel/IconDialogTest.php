<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_ckeditor5\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\ui_icons_ckeditor5\Form\IconDialog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the icon selector the dialog is built with.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconDialog::class)]
#[Group('ui_icons')]
class IconDialogTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'editor',
    'ui_icons',
    'ui_icons_ckeditor5',
    'ui_icons_text',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter']);
  }

  /**
   * Builds the dialog form for a format using the given selector.
   *
   * @param string|null $selector_format
   *   The 'selector_format' filter setting, or NULL to leave it unset.
   *
   * @return string
   *   The '#type' the icon element ended up with.
   */
  private function selectorOf(?string $selector_format): string {
    $settings = ['allowed_icon_pack' => []];
    if (NULL !== $selector_format) {
      $settings['selector_format'] = $selector_format;
    }

    $format = FilterFormat::create([
      'format' => 'test_' . bin2hex(random_bytes(4)),
      'name' => 'Test format',
      'filters' => [
        'icon_embed' => ['status' => TRUE, 'settings' => $settings],
      ],
    ]);
    $format->save();

    $form_object = IconDialog::create($this->container);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, $format);

    return $form['icon']['#type'];
  }

  /**
   * Tests the selector defaults to the autocomplete when unset.
   */
  public function testDefaultsToAutocomplete(): void {
    $this->assertSame('icon_autocomplete', $this->selectorOf(NULL));
  }

  /**
   * Tests a configured selector is the one used.
   */
  public function testConfiguredSelectorIsUsed(): void {
    $this->assertSame('icon_autocomplete', $this->selectorOf('icon_autocomplete'));
  }

  /**
   * Tests an unavailable selector falls back to the autocomplete.
   *
   * 'icon_picker' comes from the optional ui_icons_picker module, not enabled
   * here. An unknown '#type' renders as nothing instead of failing, so without
   * the fallback the dialog would come up with no icon field at all.
   */
  public function testUnavailableSelectorFallsBack(): void {
    $this->assertFalse(
      $this->container->get('plugin.manager.element_info')->hasDefinition('icon_picker'),
    );
    $this->assertSame('icon_autocomplete', $this->selectorOf('icon_picker'));
  }

}
