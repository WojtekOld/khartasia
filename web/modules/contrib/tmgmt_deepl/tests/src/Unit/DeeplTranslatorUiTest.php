<?php

namespace Drupal\Tests\tmgmt_deepl\Unit;

use DeepL\Usage;
use DeepL\UsageDetail;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_deepl\DeeplTranslatorApiInterface;
use Drupal\tmgmt_deepl\DeeplTranslatorUi;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DeeplTranslatorUi class.
 *
 * @covers \Drupal\tmgmt_deepl\DeeplTranslatorUi
 * @group tmgmt_deepl
 */
class DeeplTranslatorUiTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Creates a DeeplTranslatorUi with mocked dependencies.
   */
  private function createUi(
    ?DeeplTranslatorApiInterface $deeplApi = NULL,
    ?MessengerInterface $messenger = NULL,
    ?ModuleHandlerInterface $moduleHandler = NULL,
  ): DeeplTranslatorUi {
    return new DeeplTranslatorUi(
      [],
      'deepl_api',
      [],
      $deeplApi ?? $this->createMock(DeeplTranslatorApiInterface::class),
      $messenger ?? $this->createMock(MessengerInterface::class),
      $moduleHandler ?? $this->createMock(ModuleHandlerInterface::class),
    );
  }

  /**
   * Tests buildConfigurationForm() returns the expected form structure.
   */
  public function testBuildConfigurationForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(FALSE);
    $form_object->method('getEntity')->willReturn($translator);

    $usage = $this->createMock(Usage::class);
    $usage->method('anyLimitReached')->willReturn(FALSE);
    $usage->character = new UsageDetail(100, 500000);

    $deepl_api = $this->createMock(DeeplTranslatorApiInterface::class);
    $deepl_api->method('setTranslator')->with($translator);
    $deepl_api->method('getUsage')->willReturn($usage);

    $ui = $this->createUi(deeplApi: $deepl_api);

    $form = [];
    $result = $ui->buildConfigurationForm($form, $form_state);

    $this->assertArrayHasKey('auth_key_entity', $result);
    $this->assertArrayHasKey('enable_context', $result);
    $this->assertArrayHasKey('omit_partner_id', $result);
    $this->assertArrayHasKey('model_type', $result);
    $this->assertIsArray($result['model_type']);

    $this->assertEquals('select', $result['model_type']['#type']);
    $this->assertEquals([
      'latency_optimized' => 'latency_optimized (default)',
      'prefer_quality_optimized' => 'prefer_quality_optimized',
    ], $result['model_type']['#options']);

    $this->assertArrayHasKey('split_sentences', $result);
    $this->assertIsArray($result['split_sentences']);
    $this->assertEquals([
      '0' => 'No splitting at all, whole input is treated as one sentence',
      '1' => 'Splits on punctuation and on newlines (default)',
      'nonewlines' => 'Splits on punctuation only, ignoring newlines',
    ], $result['split_sentences']['#options']);

    $this->assertArrayHasKey('formality', $result);
    $this->assertIsArray($result['formality']);
    $this->assertEquals([
      'default' => 'default',
      'more' => 'more - for a more formal language',
      'less' => 'less - for a more informal language',
      'prefer_more' => 'prefer_more - for a more formal language if available, otherwise fallback to default formality',
      'prefer_less' => 'prefer_less - for a more informal language if available, otherwise fallback to default formality',
    ], $result['formality']['#options']);

    $this->assertArrayHasKey('preserve_formatting', $result);
    $this->assertArrayHasKey('tag_handling', $result);
    $this->assertIsArray($result['tag_handling']);
    $this->assertEquals([
      '0' => 'off',
      'xml' => 'xml',
      'html' => 'html',
    ], $result['tag_handling']['#options']);

    $this->assertArrayHasKey('tag_handling_version', $result);
    $this->assertIsArray($result['tag_handling_version']);
    $this->assertEquals([
      'v1' => 'v1',
      'v2' => 'v2',
    ], $result['tag_handling_version']['#options']);

    $this->assertArrayHasKey('outline_detection', $result);
    $this->assertArrayHasKey('splitting_tags', $result);
    $this->assertArrayHasKey('non_splitting_tags', $result);
    $this->assertArrayHasKey('ignore_tags', $result);
  }

  /**
   * Tests buildConfigurationForm() with a non-entity form.
   */
  public function testBuildConfigurationFormInvalidForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(FormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $ui = $this->createUi();
    $result = $ui->buildConfigurationForm([], $form_state);

    $this->assertArrayNotHasKey('split_sentences', $result);
    $this->assertArrayNotHasKey('formality', $result);
    $this->assertArrayNotHasKey('preserve_formatting', $result);
    $this->assertArrayNotHasKey('tag_handling', $result);
    $this->assertArrayNotHasKey('outline_detection', $result);
    $this->assertArrayNotHasKey('splitting_tags', $result);
    $this->assertArrayNotHasKey('non_splitting_tags', $result);
    $this->assertArrayNotHasKey('ignore_tags', $result);
  }

  /**
   * Tests validateConfigurationForm() with a non-entity form.
   */
  public function testValidateConfigurationFormInvalidForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(FormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $ui = $this->createUi();
    $form = [];
    $ui->validateConfigurationForm($form, $form_state);
    $this->assertEmpty($form);
  }

  /**
   * Data provider for testValidateConfigurationFormTagHandling.
   */
  public static function dataProviderTestValidateConfigurationFormTagHandling(): array {
    return [
      'default_tag_handling' => [
        'tag_handling' => '0',
        'actual_values' => [
          'outline_detection' => 1,
          'splitting_tags' => 'div,span',
          'non_splitting_tags' => 'b,i',
          'ignore_tags' => 'code,pre',
        ],
        'expected_values' => [
          'outline_detection' => 0,
          'splitting_tags' => '',
          'non_splitting_tags' => '',
          'ignore_tags' => '',
        ],
      ],
      'html_tag_handling' => [
        'tag_handling' => 'html',
        'actual_values' => [
          'outline_detection' => 1,
          'splitting_tags' => 'div,span',
          'non_splitting_tags' => 'b,i',
          'ignore_tags' => 'code,pre',
        ],
        'expected_values' => [
          'outline_detection' => 0,
          'splitting_tags' => '',
          'non_splitting_tags' => '',
          'ignore_tags' => '',
        ],
      ],
      'xml_tag_handling' => [
        'tag_handling' => 'xml',
        'actual_values' => [
          'outline_detection' => 1,
          'splitting_tags' => 'p,br',
          'non_splitting_tags' => 'a,strong,em',
          'ignore_tags' => 'script,style',
        ],
        'expected_values' => [
          'outline_detection' => 1,
          'splitting_tags' => 'p,br',
          'non_splitting_tags' => 'a,strong,em',
          'ignore_tags' => 'script,style',
        ],
      ],
    ];
  }

  /**
   * Data provider for testValidateConfigurationFormTagHandlingVersion.
   */
  public static function dataProviderTestValidateConfigurationFormTagHandlingVersion(): array {
    return [
      'latency_optimized_with_v2_error' => [
        'model_type' => 'latency_optimized',
        'tag_handling_version' => 'v2',
        'expect_error' => TRUE,
      ],
      'latency_optimized_with_v1_no_error' => [
        'model_type' => 'latency_optimized',
        'tag_handling_version' => 'v1',
        'expect_error' => FALSE,
      ],
      'prefer_quality_optimized_with_v2_no_error' => [
        'model_type' => 'prefer_quality_optimized',
        'tag_handling_version' => 'v2',
        'expect_error' => FALSE,
      ],
      'prefer_quality_optimized_with_v1_no_error' => [
        'model_type' => 'prefer_quality_optimized',
        'tag_handling_version' => 'v1',
        'expect_error' => FALSE,
      ],
    ];
  }

  /**
   * Tests validateConfigurationForm() clears tag fields when not XML.
   *
   * @param string $tag_handling
   *   The selected tag_handling.
   * @param array $actual_values
   *   The values to use in the form.
   * @param array $expected_values
   *   The expected values after validation.
   *
   * @dataProvider dataProviderTestValidateConfigurationFormTagHandling
   */
  public function testValidateConfigurationFormTagHandling(string $tag_handling, array $actual_values, array $expected_values): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $settings = [
      'auth_key_entity' => 'test_auth_key',
      'tag_handling' => $tag_handling,
      'model_type' => 'prefer_quality_optimized',
      'tag_handling_version' => 'v1',
      'outline_detection' => $actual_values['outline_detection'],
      'splitting_tags' => $actual_values['splitting_tags'],
      'non_splitting_tags' => $actual_values['non_splitting_tags'],
      'ignore_tags' => $actual_values['ignore_tags'],
    ];
    $form_state->method('getValue')->with('settings')->willReturn($settings);

    $cleared_values = [];
    $form_state->method('setValueForElement')
      ->willReturnCallback(function ($element, $value) use (&$cleared_values) {
        $this->assertIsArray($element);
        $parents = $element['#parents'];
        $this->assertIsArray($parents);
        $parent_key = end($parents);
        $this->assertIsString($parent_key);
        $cleared_values[$parent_key] = $value;
      });

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(FALSE);
    $form_object->method('getEntity')->willReturn($translator);

    $form = [
      'plugin_wrapper' => [
        'settings' => [
          'outline_detection' => ['#parents' => ['settings', 'outline_detection']],
          'splitting_tags' => ['#parents' => ['settings', 'splitting_tags']],
          'non_splitting_tags' => ['#parents' => ['settings', 'non_splitting_tags']],
          'ignore_tags' => ['#parents' => ['settings', 'ignore_tags']],
        ],
      ],
    ];

    $ui = $this->createUi();

    $ui->validateConfigurationForm($form, $form_state);

    if ($tag_handling !== 'xml') {
      $this->assertCount(4, $cleared_values);
      $this->assertEquals($expected_values['outline_detection'], $cleared_values['outline_detection']);
      $this->assertEquals($expected_values['splitting_tags'], $cleared_values['splitting_tags']);
      $this->assertEquals($expected_values['non_splitting_tags'], $cleared_values['non_splitting_tags']);
      $this->assertEquals($expected_values['ignore_tags'], $cleared_values['ignore_tags']);
    }
    else {
      $this->assertCount(0, $cleared_values);
    }
  }

  /**
   * Tests validateConfigurationForm() for tag_handling_version validation.
   *
   * @param string $model_type
   *   The model type.
   * @param string $tag_handling_version
   *   The tag handling version.
   * @param bool $expect_error
   *   Whether an error is expected.
   *
   * @dataProvider dataProviderTestValidateConfigurationFormTagHandlingVersion
   */
  public function testValidateConfigurationFormTagHandlingVersion(string $model_type, string $tag_handling_version, bool $expect_error): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(FALSE);
    $form_object->method('getEntity')->willReturn($translator);

    $messenger = $this->createMock(MessengerInterface::class);

    $settings = [
      'tag_handling' => 'xml',
      'model_type' => $model_type,
      'tag_handling_version' => $tag_handling_version,
    ];
    $form_state->method('getValue')->with('settings')->willReturn($settings);

    $form = [
      'plugin_wrapper' => [
        'settings' => [
          'tag_handling_version' => ['#parents' => ['settings', 'tag_handling_version']],
        ],
      ],
    ];

    if ($expect_error) {
      $string_translation_sub = $this->getStringTranslationStub();
      $expected_message = new TranslatableMarkup('You cannot use Tag handling version to "v2" for model type latency_optimized, please select version "v1".', [], [], $string_translation_sub);
      $form_state->expects($this->once())
        ->method('setError')
        ->with($form['plugin_wrapper']['settings']['tag_handling_version'], $expected_message);
    }
    else {
      $form_state->expects($this->never())->method('setError');
    }

    $ui = $this->createUi(messenger: $messenger);

    $ui->validateConfigurationForm($form, $form_state);
  }

  /**
   * Data provider for testCheckoutSettingsForm.
   */
  public static function dataProviderTestCheckoutSettingsForm(): array {
    return [
      'Default behavior without altering' => [
        'alter_form' => FALSE,
        'enable_context' => FALSE,
        'expected_description' => 'The DeepL translator doesn\'t provide any checkout settings.',
        'expected_context_form' => FALSE,
      ],
      'External altering via tmgmt_deepl_checkout_settings_form' => [
        'alter_form' => TRUE,
        'enable_context' => FALSE,
        'expected_description' => '',
        'expected_context_form' => FALSE,
      ],
      'Translator setting enable_context is active' => [
        'alter_form' => FALSE,
        'enable_context' => TRUE,
        'expected_description' => '',
        'expected_context_form' => TRUE,
      ],
    ];
  }

  /**
   * Tests checkoutSettingsForm().
   *
   * @param bool $alter_form
   *   Whether to simulate module altering.
   * @param bool $enable_context
   *   Whether the context setting is enabled.
   * @param string $expected_description
   *   Expected description text.
   * @param bool $expected_context_form
   *   Whether a context textarea is expected.
   *
   * @dataProvider dataProviderTestCheckoutSettingsForm
   */
  public function testCheckoutSettingsForm(bool $alter_form, bool $enable_context, string $expected_description, bool $expected_context_form): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $job = $this->createMock(JobInterface::class);

    $translator = $this->createMock(Translator::class);
    $translator->method('label')->willReturn('DeepL');
    $translator->method('getSetting')
      ->with('enable_context')
      ->willReturn($enable_context);
    $job->method('getTranslator')->willReturn($translator);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    if ($alter_form) {
      $module_handler->expects($this->once())
        ->method('alter')
        ->willReturnCallback(function ($hook, &$form, $job_arg) {
          $form = [];
          $form['custom_field'] = [
            '#type' => 'textfield',
            '#title' => 'Custom Field',
            '#description' => new TranslatableMarkup('This is a custom field added during form alteration.'),
          ];
        });
    }
    else {
      $module_handler->expects($this->once())
        ->method('alter')
        ->with('tmgmt_deepl_checkout_settings_form', self::callback(static fn($v): bool => is_array($v)), $job);
    }

    $ui = $this->createUi(moduleHandler: $module_handler);

    $result = $ui->checkoutSettingsForm([], $form_state, $job);

    if ($expected_description !== '') {
      $this->assertArrayHasKey('#description', $result);
      $this->assertInstanceOf(TranslatableMarkup::class, $result['#description']);
      $this->assertEquals($expected_description, $result['#description']->__toString());
    }
    else {
      $this->assertArrayNotHasKey('#description', $result);
    }

    if ($expected_context_form) {
      $this->assertArrayHasKey('context', $result);
      $context = $result['context'];
      $this->assertIsArray($context);
      $this->assertEquals('textarea', $context['#type']);
      $this->assertInstanceOf(TranslatableMarkup::class, $context['#description']);
      $this->assertEquals(
        'Provide additional context for translation (e.g., product descriptions, article summaries).',
        $context['#description']->__toString(),
      );
    }
    else {
      $this->assertArrayNotHasKey('context', $result);
    }

    if ($alter_form) {
      $this->assertArrayHasKey('custom_field', $result);
      $this->assertIsArray($result['custom_field']);
      $this->assertEquals('textfield', $result['custom_field']['#type']);
      $this->assertEquals('Custom Field', $result['custom_field']['#title']);
      $this->assertEquals(
        'This is a custom field added during form alteration.',
        $result['custom_field']['#description'],
      );
    }
  }

  /**
   * Tests showUsageInfo via buildConfigurationForm when limits are reached.
   */
  public function testShowUsageInfoWithLimitsReached(): void {
    $usage = $this->createMock(Usage::class);
    $usage->method('anyLimitReached')->willReturn(TRUE);

    $deepl_api = $this->createMock(DeeplTranslatorApiInterface::class);
    $deepl_api->method('setTranslator');
    $deepl_api->method('getUsage')->willReturn($usage);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addWarning')
      ->with('The translation limit of your account has been reached.');

    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(FALSE);
    $form_object->method('getEntity')->willReturn($translator);

    $ui = $this->createUi(deeplApi: $deepl_api, messenger: $messenger);
    $ui->buildConfigurationForm([], $form_state);
  }

  /**
   * Tests showUsageInfo via buildConfigurationForm with normal usage stats.
   */
  public function testShowUsageInfoWithUsageInfo(): void {
    $usage = $this->createMock(Usage::class);
    $usage->method('anyLimitReached')->willReturn(FALSE);
    $usage->character = new UsageDetail(1000, 10000);

    $deepl_api = $this->createMock(DeeplTranslatorApiInterface::class);
    $deepl_api->method('setTranslator');
    $deepl_api->method('getUsage')->willReturn($usage);

    $messenger = $this->createMock(MessengerInterface::class);
    $string_translation_sub = $this->getStringTranslationStub();
    $expected_message = new TranslatableMarkup('Usage information: :usage_count of :usage_limit characters', [
      ':usage_count' => '1000',
      ':usage_limit' => '10000',
    ], [], $string_translation_sub);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($expected_message);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(FALSE);
    $form_object->method('getEntity')->willReturn($translator);

    $ui = $this->createUi(deeplApi: $deepl_api, messenger: $messenger);
    $ui->buildConfigurationForm([], $form_state);
  }

  /**
   * Tests isEntityForm returns TRUE via buildConfigurationForm.
   */
  public function testIsEntityFormValid(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(TRUE);
    $form_object->method('getEntity')->willReturn($translator);

    $ui = $this->createUi();
    $result = $ui->buildConfigurationForm([], $form_state);

    // When isNew is TRUE, showUsageInfo is not called, but the form fields
    // are still built (isEntityForm returned TRUE and was not rejected).
    $this->assertArrayHasKey('auth_key_entity', $result);
  }

  /**
   * Tests isEntityForm returns FALSE via buildConfigurationForm.
   */
  public function testIsEntityFormInvalid(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(FormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $ui = $this->createUi();
    $result = $ui->buildConfigurationForm([], $form_state);

    $this->assertArrayNotHasKey('auth_key_entity', $result);
  }

  /**
   * Tests create() wires services from the passed container, not globals.
   *
   * The global Drupal container is intentionally left without the required
   * services so that any \Drupal::service() call would throw. If create()
   * succeeds, it proves the passed container was used.
   */
  public function testCreateInjectsServicesFromPassedContainer(): void {
    $deeplApi = $this->createMock(DeeplTranslatorApiInterface::class);
    $messenger = $this->createMock(MessengerInterface::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    // Global container intentionally lacks plugin services.
    $global_container = new ContainerBuilder();
    $global_container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($global_container);

    // Passed container has all three required services.
    $create_container = new ContainerBuilder();
    $create_container->set('tmgmt_deepl.api', $deeplApi);
    $create_container->set('messenger', $messenger);
    $create_container->set('module_handler', $moduleHandler);

    $instance = DeeplTranslatorUi::create($create_container, [], 'plugin_id', []);

    // Verify the services work by triggering buildConfigurationForm, which
    // calls setTranslator() and getUsage() on the deeplApi. Both are mocked
    // in the passed container but would throw if taken from the global one.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    $translator = $this->createMock(Translator::class);
    $translator->method('isNew')->willReturn(TRUE);
    $form_object->method('getEntity')->willReturn($translator);

    // No exception means all services came from the passed container.
    $result = $instance->buildConfigurationForm([], $form_state);
    $this->assertArrayHasKey('auth_key_entity', $result);
  }

}
