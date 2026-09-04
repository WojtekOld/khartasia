<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Service;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\proc\ProcKeyManager;
use Drupal\proc\Service\ProcFieldProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the ProcFieldProcessor class.
 *
 * @coversDefaultClass \Drupal\proc\Service\ProcFieldProcessor
 *
 * @group proc
 * @SuppressWarnings(PHPMD)
 */
class ProcFieldProcessorTest extends TestCase {

  protected $moduleHandler;
  protected $pathCurrent;
  protected $configFactory;
  protected $tokenService;
  protected $currentUser;
  protected $keyManager;
  protected $logger;
  protected $procFieldProcessor;

  /**
   *
   */
  protected function setUp(): void {
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->pathCurrent = $this->createMock(CurrentPathStack::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->tokenService = $this->createMock(Token::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->keyManager = $this->createMock(ProcKeyManager::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->keyManager->method('getPrivKeyMetadata')
      ->willReturn(['proc_pass' => 'test_proc_pass']);
    $this->keyManager->method('getKeys')
      ->willReturn([
        'encrypted_private_key' => 'test_priv_key',
        'public_key' => 'test_pub_key',
        'keyring_type' => 'keyring',
        'keyring_cid' => 'test_cid',
      ]);

    $this->currentUser->method('id')->willReturn(1);

    $this->procFieldProcessor = new ProcFieldProcessor(
      $this->moduleHandler,
      $this->pathCurrent,
      $this->configFactory,
      $this->tokenService,
      $this->currentUser,
      $this->keyManager,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->logger
    );

    $container = new ContainerBuilder();
    $string_translation = $this->createMock(TranslationInterface::class);
    $container->set('string_translation', $string_translation);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::processProcField
   */
  public function testProcessProcField() {
    $element = [
      '#proc-settings' => [
        'fetcher_endpoint' => 'endpoint',
        'proc_field_name' => 'field_name',
        'field_input_mode' => 0,
        'to_recipients_field_name' => 'to_field',
        'carbon_copy_recipients_field_name' => 'cc_field',
        'preset_recipients_csv' => 'csv',
        'fetcher_endpoint_filter' => 'filter',
        'fetcher_endpoint_filter_type' => 'type',
        'encrypt_button_label' => 'Encrypt',
        'decrypt_button_label' => 'Decrypt',
        'cache_password' => TRUE,
        'trigger_format' => 0,
        'submit_element_id' => 'submit_id',
        'trigger_fields' => 'fields',
        'proc_field_decryption_mode' => 0,
        'proc_field_cardinality' => 1,
      ],
      'target_id' => [
        '#default_value' => NULL,
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $config = $this->createMock(Config::class);
    $config->method('get')->willReturn(NULL);
    $this->configFactory->method('get')
      ->with('proc.settings')
      ->willReturn($config);

    $this->pathCurrent->method('getPath')->willReturn('/proc/123');

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('proc_element', $element, $form_state, $complete_form);

    $result = $this->procFieldProcessor->processProcField($element, $form_state, $complete_form);

    $this->assertIsArray($result);
  }

  /**
   * @covers ::procFieldIsDisabled
   */
  public function testProcFieldIsDisabled() {
    $element = [
      '#disabled' => TRUE,
      '#proc-settings' => [
        'fetcher_endpoint' => 'endpoint',
        'proc_field_name' => 'field_name',
        'field_input_mode' => 0,
        'to_recipients_field_name' => 'to_field',
        'carbon_copy_recipients_field_name' => 'cc_field',
        'preset_recipients_csv' => 'csv',
        'fetcher_endpoint_filter' => 'filter',
        'fetcher_endpoint_filter_type' => 'type',
        'encrypt_button_label' => 'Encrypt',
        'decrypt_button_label' => 'Decrypt',
        'cache_password' => TRUE,
        'trigger_format' => 0,
        'submit_element_id' => 'submit_id',
        'trigger_fields' => 'fields',
        'proc_field_decryption_mode' => 0,
      ],
    ];
    $complete_form = [];

    $result = $this->procFieldProcessor->procFieldIsDisabled($element, $complete_form);

    $this->assertTrue($result);
  }

  /**
   * @covers ::procGetDefaultValue
   */
  public function testProcGetDefaultValue() {
    $element = [
      'target_id' => [
        '#default_value' => NULL,
      ],
    ];

    $result = $this->procFieldProcessor->procGetDefaultValue($element);

    $this->assertEquals(NULL, $result);
  }

  /**
   * @covers ::getProcElementMetadata
   */
  public function testGetProcElementMetadata() {
    $result = $this->procFieldProcessor->getProcElementMetadata();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('metadata', $result);
  }

  /**
   * @covers ::getSettings
   */
  public function testGetSettings() {
    $element = [
      '#proc-settings' => [
        'fetcher_endpoint' => 'endpoint',
        'proc_field_name' => 'field_name',
        'field_input_mode' => 0,
        'to_recipients_field_name' => 'to_field',
        'carbon_copy_recipients_field_name' => 'cc_field',
        'preset_recipients_csv' => 'csv',
        'fetcher_endpoint_filter' => 'filter',
        'fetcher_endpoint_filter_type' => 'type',
        'encrypt_button_label' => 'Encrypt',
        'decrypt_button_label' => 'Decrypt',
        'cache_password' => TRUE,
        'trigger_format' => 0,
        'submit_element_id' => 'submit_id',
        'trigger_fields' => 'fields',
        'proc_field_decryption_mode' => 0,
      ],
    ];

    $this->pathCurrent->method('getPath')->willReturn('current_path');
    $this->configFactory->method('get')->willReturn($this->createMock(Config::class));
    $this->tokenService->method('replace')->willReturn('replaced_endpoint');

    $result = $this->procFieldProcessor->getSettings($element);

    $this->assertIsArray($result);
    $this->assertEquals('current_path', $result['current_path']);
    $this->assertEquals('replaced_endpoint', $result['fetcher_endpoint']);
  }

}
