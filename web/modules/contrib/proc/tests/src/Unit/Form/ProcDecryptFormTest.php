<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\Form\ProcDecryptForm;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcJsonFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

require_once __DIR__ . '/../../../../inc/proc.functions.inc';

/**
 * Unit tests for the ProcDecryptForm class.
 *
 * @group proc
 *
 * @SuppressWarnings(PHPMD)
 */
class ProcDecryptFormTest extends UnitTestCase {

  /**
   * The ProcDecryptForm instance.
   *
   * @var \Drupal\proc\Form\ProcDecryptForm
   */
  protected $procDecryptForm;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The current path mock.
   *
   * @var \Drupal\Core\Path\CurrentPathStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentPath;

  /**
   * The ProcKeyManager mock.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $procKeyManager;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The logger mock.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The current user mock.
   *
   * @var \Drupal\Core\Session\AccountProxy|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The file repository mock.
   *
   * @var \Drupal\file\FileRepository|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileRepository;

  /**
   * The file usage mock.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileUsage;

  /**
   * The renderer mock.
   *
   * @var \Drupal\Core\Render\Renderer|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $renderer;

  /**
   * The request stack mock.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  protected $request;
  protected $query;
  protected $formState;
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock all dependencies.
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->currentPath = $this->createMock(CurrentPathStack::class);
    $this->procKeyManager = $this->createMock(ProcKeyManagerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->currentUser = $this->createMock(AccountProxy::class);
    $this->fileRepository = $this->createMock(FileRepository::class);
    $this->fileUsage = $this->createMock(DatabaseFileUsageBackend::class);
    $this->renderer = $this->createMock(Renderer::class);
    $this->requestStack = $this->createMock(RequestStack::class);

    // Mock the request and its query parameters.
    $this->request = $this->createMock(Request::class);
    $this->query = $this->createMock(ParameterBag::class);
    $this->request->query = $this->query;

    // Simulate the query parameters for the test.
    $this->query->method('all')->willReturn([
      'proc_in_mode' => ProcInterface::INPUT_MODE_FILE,
      'proc_standalone_mode' => FALSE,
      'proc_cache_password_mode' => 2,
      'proc_form_triggering_field' => 'edit-field-proc-multiple-0-target-id',
      'proc_field_name' => 'field_proc_multiple',
      'proc_decryption_mode' => 1,
      'proc_decryption_trigger_fields' => 0,
      'host_page_url' => '/node/1234/edit',
      '_wrapper_format' => 'drupal_dialog',
    ]);

    // Mock the request stack to return the request.
    $this->requestStack->method('getCurrentRequest')->willReturn($this->request);

    // Mock the getKeys method in ProcKeyManager.
    $this->procKeyManager->method('getKeys')->willReturn([
      'encrypted_private_key' => 'private_key',
      'public_key' => 'public_key',
      'keyring_cid' => 'keyring_cid',
      'keyring_type' => 'type',
    ]);
    // Use the custom entity mock.
    $entity = $this->createMock(CustomEntityMock::class);
    $entity->method('hasField')->willReturn(TRUE);
    $entity->method('getFieldDefinition')->willReturn($this->createMock(FieldDefinitionInterface::class));
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('load')->willReturn($entity);
    $this->entityTypeManager->method('getStorage')->willReturn($entity_storage);

    // Create the ProcDecryptForm instance with mocked dependencies.
    $this->procDecryptForm = new ProcDecryptForm(
      $this->configFactory,
      $this->moduleHandler,
      $this->currentPath,
      $this->procKeyManager,
      $this->entityTypeManager,
      $this->logger,
      $this->procKeyManager,
      $this->currentUser,
      $this->fileRepository,
      $this->fileUsage,
      $this->renderer,
      $this->requestStack,
      $this->createMock(Environment::class),
      $this->createMock(FileSystem::class),
      $this->createMock(ProcJsonFileService::class)
    );

    // Set up the container with necessary services.
    $container = new ContainerBuilder();
    $string_translation = $this->createMock(TranslationInterface::class);
    $container->set('string_translation', $string_translation);
    $container->set('request_stack', $this->requestStack);
    $container->set('config.factory', $this->configFactory);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('current_user', $this->currentUser);
    \Drupal::setContainer($container);
  }

  /**
   * Tests the buildForm method.
   *
   * @throws \Random\RandomException
   */
  public function testBuildForm() {
    // Mock the current path.
    $this->currentPath->expects($this->once())
      ->method('getPath')
      // Relative path with initial trailing slash:
      ->willReturn('/proc/123,124');

    // Mock the config factory.
    $config = $this->createMock(Config::class);

    $this->configFactory->expects($this->atLeastOnce())
      ->method('get')
      ->with('proc.settings')
      ->willReturn($config);

    // Mock the current user:
    $user = $this->createMock(AccountProxy::class);
    // Set the user ID.
    $user->expects($this->once())
      ->method('id')
      ->willReturn(456);
    // Set the user email.
    $user->expects($this->once())
      ->method('getEmail')
      ->willReturn('test@test.test');
    // Set the user account name.
    $user->expects($this->once())
      ->method('getAccountName')
      ->willReturn('test');

    // Mock the ProcKeyManager.
    $this->procKeyManager->expects($this->once())
      ->method('getPrivKeyMetadata')
      ->willReturn([
        'proc_uid' => $user->id(),
        'proc_pass' => 'hash_base64',
        'proc_email' => $user->getEmail(),
        'proc_name' => $user->getAccountName(),
      ]);

    $this->form = $this->getMockBuilder(ProcDecryptForm::class)
      ->setConstructorArgs([
        $this->configFactory,
        $this->moduleHandler,
        $this->currentPath,
        $this->procKeyManager,
        $this->entityTypeManager,
        $this->logger,
        $this->procKeyManager,
        $this->currentUser,
        $this->fileRepository,
        $this->fileUsage,
        $this->renderer,
        $this->requestStack,
        $this->createMock(Environment::class),
        $this->createMock(FileSystem::class),
        $this->createMock(ProcJsonFileService::class),
      ])
      ->onlyMethods(['getCipher'])
      ->getMock();

    // Create a mock FormStateInterface.
    $form_state = $this->createMock(FormStateInterface::class);

    // We only need to test the buildForm method. There is no data being
    // submitted on decryption.
    $form = [];
    $result = $this->form->buildForm($form, $form_state);

    // Assert the form structure.
    $this->assertArrayHasKey('#attached', $result);
    $this->assertArrayHasKey('library', $result['#attached']);
    $this->assertArrayHasKey('drupalSettings', $result['#attached']);

    $this->assertEquals('proc/openpgpjs', $result['#attached']['library'][0]);
    $this->assertEquals('proc/proc-decrypt', $result['#attached']['library'][1]);

    // Assert the drupalSettings.
    $drupalSettings = $result['#attached']['drupalSettings']['proc'];

    $this->assertEquals('type', $drupalSettings['proc_keyring_type']);
    $this->assertEquals('private_key', $drupalSettings['proc_privkey']);
    $this->assertEquals(['123', '124'], $drupalSettings['proc_ids']);
    $this->assertEquals('hash_base64', $drupalSettings['proc_pass']);
    $this->assertEquals('edit-field-proc-multiple-0-target-id', $drupalSettings['proc_form_triggering_field']);
    $this->assertEquals('field_proc_multiple', $drupalSettings['proc_field_name']);
  }

  /**
   * Tests the denyAccess method.
   */
  public function testDenyAccess() {
    $this->expectException(AccessDeniedHttpException::class);
    $this->procDecryptForm->denyAccess();
  }

}
