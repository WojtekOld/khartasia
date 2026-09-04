<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\Entity\Proc;
use Drupal\proc\Form\ProcKeysGenerationForm;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcJsonFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../../inc/proc.functions.inc';

/**
 * Unit tests for the ProcKeysGenerationForm class.
 *
 * @coversDefaultClass \Drupal\proc\Form\ProcKeysGenerationForm
 * @group proc
 *
 * @SuppressWarnings(PHPMD)
 */
class ProcKeysGenerationFormTest extends UnitTestCase {

  /**
   * The form under test.
   *
   * @var \Drupal\proc\Form\ProcKeysGenerationForm
   */
  protected $form;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $procKeyManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The file repository.
   *
   * @var \Drupal\file\FileRepository|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileRepository;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileUsage;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $translation;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock all the dependencies.
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->procKeyManager = $this->createMock(ProcKeyManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountProxy::class);
    $this->fileRepository = $this->createMock(FileRepository::class);
    $this->fileUsage = $this->createMock(DatabaseFileUsageBackend::class);
    $this->renderer = $this->createMock(Renderer::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->translation = $this->createMock(TranslationInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    // Set up the container with the translation and messenger services.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->translation);
    $container->set('messenger', $this->messenger);
    \Drupal::setContainer($container);

    // Define the getPrivKeyMetadata method in the procKeyManager mock.
    $this->procKeyManager->method('getPrivKeyMetadata')
      ->willReturn(['mocked_metadata']);

    // Instantiate the form with the mocked dependencies.
    $this->form = new ProcKeysGenerationForm(
      $this->configFactory,
      $this->procKeyManager,
      $this->logger,
      $this->entityTypeManager,
      $this->currentUser,
      $this->fileRepository,
      $this->fileUsage,
      $this->renderer,
      $this->moduleHandler,
      $this->createMock(Environment::class),
      $this->createMock(FileSystem::class),
      $this->createMock(CurrentPathStack::class),
      $this->createMock(ProcJsonFileService::class)
    );
  }

  /**
   * Tests the form ID.
   */
  public function testGetFormId() {
    $this->assertEquals('proc_keys_generation_form', $this->form->getFormId());
  }

  /**
   * Tests the buildForm method.
   */
  public function testBuildForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    // Mock the config factory to return a config object.
    $config = $this->createMock(Config::class);
    $config->method('get')
      ->willReturn('some_value');
    $this->configFactory->method('get')
      ->willReturn($config);

    $builtForm = $this->form->buildForm($form, $form_state);

    // Assert that the form has the expected fields.
    $this->assertArrayHasKey('password_confirm', $builtForm);
    $this->assertArrayHasKey('actions', $builtForm);
    $this->assertArrayHasKey('submit', $builtForm['actions']);
  }

  /**
   * Tests the validateForm method.
   */
  public function testValidateForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    // Simulate an empty public key.
    $form_state->method('getValue')
      ->with('public_key')
      ->willReturn('');

    // Mock the translation service.
    $translation = $this->createMock(TranslationInterface::class);

    // Create the expected TranslatableMarkup object with the mocked translation service.
    $expectedError = new TranslatableMarkup('Unknown error on creating asymmetric keys.', [], [], $translation);

    // Expect an error to be set only once.
    $form_state->expects($this->once())
      ->method('setError')
      ->with($this->anything(), $expectedError);

    $this->form->validateForm($form, $form_state);
  }

  /**
   * Tests the submitForm method.
   */
  public function testSubmitForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    // Simulate form values.
    $form_state->method('getValue')
      ->willReturnMap([
        ['encrypted_private_key', 'encrypted_private_key_value'],
        ['public_key', 'public_key_value'],
        ['generation_timestamp', 'timestamp_value'],
        ['generation_timespan', 'timespan_value'],
        ['browser_fingerprint', 'fingerprint_value'],
        ['proc_email', 'email_value'],
      ]);

    // Mock the config factory to return a key size.
    $config = $this->createMock(Config::class);
    $config->method('get')
      ->with('proc-rsa-key-size')
      ->willReturn(2048);
    $this->configFactory->method('get')
      ->with('proc.settings')
      ->willReturn($config);

    // Mock the Proc entity.
    $proc_entity = $this->createMock(Proc::class);
    $proc_entity->method('id')
      ->willReturn(1);

    // Mock the entity storage to return the Proc entity.
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('create')
      ->willReturn($proc_entity);

    $this->entityTypeManager->method('getStorage')
      ->with('proc')
      ->willReturn($entity_storage);

    // Call the submitForm method.
    $this->form->submitForm($form, $form_state);
  }

}
