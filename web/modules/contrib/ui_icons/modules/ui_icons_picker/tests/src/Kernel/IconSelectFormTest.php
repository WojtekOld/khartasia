<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_picker\Kernel;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_picker\Form\IconSelectForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test the icon picker modal form.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconSelectForm::class)]
#[Group('ui_icons')]
class IconSelectFormTest extends KernelTestBase {

  /**
   * Wrapper id the picker is opened for.
   */
  private const WRAPPER_ID = 'edit-icon-wrapper';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_picker',
    'ui_icons_test',
  ];

  /**
   * Builds the picker form with the given dialog query and user input.
   *
   * @param array $query
   *   The `dialogOptions[query]` the modal was opened with.
   * @param array $input
   *   User input, `filter` being the only key the form reads.
   *
   * @return array
   *   The built form.
   */
  private function buildPicker(array $query, array $input = []): array {
    $request = Request::create('/ui-icons/picker/dialog', 'GET', [
      'dialogOptions' => ['query' => $query],
    ]);
    // The form builder reads the session to build the form token.
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $form_state = new FormState();
    if ($input) {
      $form_state->setUserInput($input);
    }

    return $this->container->get('form_builder')
      ->buildForm(IconSelectForm::class, $form_state);
  }

  /**
   * Tests that the picker lists icons of the allowed packs only.
   */
  public function testBuildFormListsAllowedPacksOnly(): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $this->assertSame(self::WRAPPER_ID, $form['wrapper_id']['#value']);

    $options = $form['list']['icon_full_id']['#options'];
    // The empty option always comes first so a selection can be cleared.
    $this->assertArrayHasKey('_none_', $options);
    unset($options['_none_']);

    $this->assertNotEmpty($options);
    foreach (array_keys($options) as $icon_full_id) {
      $this->assertStringStartsWith('test_path:', (string) $icon_full_id);
    }
  }

  /**
   * Tests that an empty `allowed_icon_pack` lists every pack.
   */
  public function testBuildFormWithoutPackLimit(): void {
    $form = $this->buildPicker(['wrapper_id' => self::WRAPPER_ID]);

    $options = $form['list']['icon_full_id']['#options'];
    unset($options['_none_']);

    $packs = [];
    foreach (array_keys($options) as $icon_full_id) {
      [$pack_id] = explode(':', (string) $icon_full_id);
      $packs[$pack_id] = TRUE;
    }

    $this->assertGreaterThan(1, count($packs));
  }

  /**
   * Tests the preview libraries and settings attached to the icon list.
   */
  public function testBuildFormAttachesPreviewAssets(): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $attached = $form['list']['#attached'];
    $this->assertContains('ui_icons_picker/library', $attached['library']);
    $this->assertContains('ui_icons/ui_icons.preview', $attached['library']);

    $preview_data = $attached['drupalSettings']['ui_icons_preview_data'];
    $this->assertNotEmpty($preview_data['icon_full_ids']);
    $this->assertSame(['size' => 32], $preview_data['settings']);
    $this->assertTrue($preview_data['target_input_label']);
  }

  /**
   * Tests the search filter narrows the listed icons.
   */
  public function testBuildFormWithSearchQuery(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID],
      ['filter' => 'foo'],
    );

    $this->assertSame('foo', $form['filters']['filter']['#default_value']);

    $options = $form['list']['icon_full_id']['#options'];
    unset($options['_none_']);
    $this->assertNotEmpty($options);
  }

  /**
   * Tests the empty state when nothing matches the filter.
   */
  public function testBuildFormWithNoResult(): void {
    $form = $this->buildPicker(
      ['wrapper_id' => self::WRAPPER_ID],
      ['filter' => 'nothing_can_ever_match_this_query'],
    );

    $this->assertSame('markup', $form['list']['#type']);
    $this->assertStringContainsString('No icon found', (string) $form['list']['#markup']);
    // The empty state stops before the select button and the pager.
    $this->assertArrayNotHasKey('actions', $form);
    $this->assertArrayNotHasKey('pagination', $form);
  }

  /**
   * Tests that a single page of results carries no pager.
   */
  public function testBuildFormWithoutPagination(): void {
    $form = $this->buildPicker([
      'wrapper_id' => self::WRAPPER_ID,
      'allowed_icon_pack' => 'test_path',
    ]);

    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayNotHasKey('pagination', $form);
  }

  /**
   * Tests the page submit handlers move the stored page and rebuild.
   */
  public function testPageSubmitHandlers(): void {
    $form = [];
    $form_state = new FormState();
    IconSelectForm::setModalState($form_state, ['page' => 0]);

    $picker = IconSelectForm::create($this->container);

    $picker->nextPageSubmit($form, $form_state);
    $this->assertSame(1, IconSelectForm::getModalState($form_state)['page']);
    $this->assertTrue($form_state->isRebuilding());

    $picker->previousPageSubmit($form, $form_state);
    $this->assertSame(0, IconSelectForm::getModalState($form_state)['page']);
  }

  /**
   * Tests searching resets the pager back to the first page.
   */
  public function testSearchSubmitResetsPage(): void {
    $form = [];
    $form_state = new FormState();
    IconSelectForm::setModalState($form_state, ['page' => 4]);

    IconSelectForm::create($this->container)->searchSubmit($form, $form_state);

    $this->assertSame(0, IconSelectForm::getModalState($form_state)['page']);
    $this->assertTrue($form_state->isRebuilding());
  }

  /**
   * Tests submitting without a selection is rejected.
   */
  public function testValidateFormRequiresSelection(): void {
    $form = ['list' => ['#parents' => ['list']]];
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['submit']]);

    IconSelectForm::create($this->container)->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('Pick an icon to insert', (string) reset($errors));
  }

  /**
   * Tests validation is skipped for the pager and search buttons.
   */
  public function testValidateFormIgnoresOtherButtons(): void {
    $form = ['list' => ['#parents' => ['list']]];
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['search']]);

    IconSelectForm::create($this->container)->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Tests the ajax response sent when an icon is picked.
   */
  public function testSelectIconAjax(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'icon_full_id' => 'test_path:foo',
      'wrapper_id' => self::WRAPPER_ID,
    ]);

    $response = IconSelectForm::create($this->container)->selectIconAjax($form, $form_state);

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $commands = $response->getCommands();
    $this->assertSame('updateIconLibrarySelection', $commands[0]['command']);
    $this->assertSame('test_path:foo', $commands[0]['icon_full_id']);
    $this->assertSame(self::WRAPPER_ID, $commands[0]['wrapper_id']);
    $this->assertSame('closeDialog', $commands[1]['command']);
  }

  /**
   * Tests picking the empty option clears the selection.
   */
  public function testSelectIconAjaxWithNoneClearsValue(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'icon_full_id' => '_none_',
      'wrapper_id' => self::WRAPPER_ID,
    ]);

    $response = IconSelectForm::create($this->container)->selectIconAjax($form, $form_state);

    $this->assertSame('', $response->getCommands()[0]['icon_full_id']);
  }

}
