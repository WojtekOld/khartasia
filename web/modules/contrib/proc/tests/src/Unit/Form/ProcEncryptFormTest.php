<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\Form\ProcEncryptForm;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcJsonFileService;
use Drupal\proc\UrlParser;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\UserInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require_once __DIR__ . '/../../../../inc/proc.functions.inc';

/**
 * Unit tests for the ProcEncryptForm class.
 *
 * @group proc
 *
 * @SuppressWarnings(PHPMD)
 */
class ProcEncryptFormTest extends TestCase {

  protected $fileSystem;
  protected $fileRepository;
  protected $entityTypeManager;
  protected $currentUser;
  protected $configFactory;
  protected $request;
  protected $currentPath;
  protected $moduleHandler;
  protected $fileUsage;
  protected $procKeyManager;
  protected $renderer;
  protected $logger;
  protected $urlParser;
  protected $form;
  protected $formState;
  protected $procEncryptForm;
  protected $current_user;
  protected $query;
  protected $headers;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->createMock(FileSystem::class);
    $this->fileRepository = $this->createMock(FileRepository::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->current_user = $this->createMock(AccountProxy::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->request = $this->createMock(Request::class);
    $this->currentPath = $this->createMock(CurrentPathStack::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->fileUsage = $this->createMock(DatabaseFileUsageBackend::class);
    $this->procKeyManager = $this->createMock(ProcKeyManagerInterface::class);
    $this->renderer = $this->createMock(Renderer::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->urlParser = $this->createMock(UrlParser::class);
    $this->formState = $this->createMock(FormStateInterface::class);

    // Mock the current user ID.
    $this->current_user->method('id')->willReturn('1');

    // Mock the entity storage and query.
    $entity_storage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $entity_storage->method('getQuery')->willReturn($this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class));
    $user_storage = $this->createMock(\Drupal\user\UserStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->willReturnCallback(
      function ($entity_type_id) use ($entity_storage, $user_storage) {
        return $entity_type_id === 'user' ? $user_storage : $entity_storage;
      }
    );

    // Set up the request and query parameters BEFORE creating the form.
    $this->query = $this->createMock(ParameterBag::class);
    $this->query->method('get')
      ->willReturnCallback(function ($key) {
        switch ($key) {
          case 'proc_in_mode':
            return ProcInterface::INPUT_MODE_FILE;
          case 'proc_standalone_mode':
            return 'TRUE';
          default:
            return NULL;
        }
      });
    $this->query->method('all')->willReturn([]);
    $this->request->query = $this->query;

    $this->headers = $this->createMock(ParameterBag::class);
    $this->headers->method('get')->with('referer')->willReturn('http://example.com/node/1');
    $this->request->headers = $this->headers;

    $environment = new Environment();

    $request_stack = $this->createMock(RequestStack::class);
    $request_stack->method('getCurrentRequest')->willReturn($this->request);

    $this->procEncryptForm = new ProcEncryptForm(
      $this->fileSystem,
      $this->fileRepository,
      $this->entityTypeManager,
      $this->current_user,
      $this->configFactory,
      $this->request,
      $this->currentPath,
      $this->moduleHandler,
      $this->fileUsage,
      $this->procKeyManager,
      $this->renderer,
      $this->logger,
      $this->urlParser,
      $environment,
      $this->createMock(ProcJsonFileService::class),
      $this->createMock(PrivateTempStoreFactory::class)
    );

    // Initialize the form property.
    $this->form = $this->procEncryptForm;

    // Mock the request stack and set it in the container.
    $request_stack = $this->createMock(RequestStack::class);
    $request_stack->method('getCurrentRequest')->willReturn($this->request);

    // Mock the entity_field.manager service.
    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);

    // Mock the field definitions.
    $field_definitions = $this->createMock('Drupal\Core\Field\FieldDefinitionInterface');
    $entity_field_manager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn(['field_name' => $field_definitions]);

    // Mock the messenger service.
    $messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');

    // Mock other necessary services.
    $string_translation = $this->createMock(TranslationInterface::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $current_user = $this->createMock(AccountProxyInterface::class);

    // Mock the current user.
    $current_user->method('id')
      ->willReturn(1);

    $container = new ContainerBuilder();
    $container->set('string_translation', $string_translation);
    $container->set('request_stack', $request_stack);
    $container->set('config.factory', $config_factory);
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('messenger', $messenger);
    $container->set('entity_field.manager', $entity_field_manager);
    $container->set('current_user', $current_user);
    \Drupal::setContainer($container);
  }

  /**
   * Tests the getFormId method.
   */
  public function testBuildForm() {
    $form = [];
    $form_state = $this->formState;

    // Simulate different input modes.
    $input_modes = [
      ProcInterface::INPUT_MODE_FILE,
      ProcInterface::INPUT_MODE_TEXTFIELD,
      ProcInterface::INPUT_MODE_TEXTAREA,
    ];

    foreach ($input_modes as $input_mode) {
      // Set the query parameter using the mocked request.
      $this->query->method('get')
        ->with('proc_in_mode')
        ->willReturn($input_mode);

      $built_form = $this->form->buildForm($form, $form_state);
      $this->assertArrayHasKey('source_file_name', $built_form);
    }

    // Test standalone mode.
    $this->query->method('get')
      ->with('proc_standalone_mode')
      ->willReturn('TRUE');

    $built_form = $this->form->buildForm($form, $form_state);
    $this->assertArrayNotHasKey('submit-proc', $built_form);
    $this->assertArrayHasKey('actions', $built_form);
  }

  /**
   * Tests the getFormId method.
   */
  public function testValidateForm() {
    $form = [];
    $form_state = $this->getMockBuilder(FormStateInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Test invalid cipher text.
    $invalid_cipher_text = "InvalidCipherText";
    $form_state->expects($this->atLeastOnce())
      ->method('getValue')
      ->willReturnMap([
        ['cipher_text', NULL, $invalid_cipher_text],
        ['source_file_size', NULL, '1024'],
        ['source_file_name', NULL, 'test.txt'],
        ['generation_timestamp', NULL, '2023-01-01 00:00:00'],
      ]);
    $form_state->expects($this->once())
      ->method('setErrorByName')
      ->with('cipher_text', $this->anything());

    $this->procEncryptForm->validateForm($form, $form_state);

    // Reset the mock to avoid interference between test cases.
    $form_state = $this->getMockBuilder(FormStateInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Test valid cipher text.
    $valid_cipher_text = "-----BEGIN PGP MESSAGE-----\nValidCipherText\n-----END PGP MESSAGE-----";
    $form_state->expects($this->atLeastOnce())
      ->method('getValue')
      ->willReturnMap([
        ['cipher_text', NULL, $valid_cipher_text],
        ['source_file_size', NULL, '1024'],
        ['source_file_name', NULL, 'test.txt'],
        ['generation_timestamp', NULL, '2023-01-01 00:00:00'],
      ]);
    $form_state->expects($this->never())
      ->method('setErrorByName');

    $this->procEncryptForm->validateForm($form, $form_state);
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */

  /**
   * Tests the submitForm method.
   */
  public function testSubmitForm(): void {
    // Mock the form state values.
    $form_state_values = [
      'cipher_text' => 'encrypted_text',
      'source_file_name' => 'test_file.txt',
      'source_file_size' => '1024',
      'source_file_type' => 'text/plain',
      'source_file_last_change' => '2023-01-01',
      'browser_fingerprint' => 'fingerprint',
      'generation_timestamp' => '2023-01-01 00:00:00',
      'generation_timespan' => '0',
      'host_page_url' => 'http://example.com',
      'signed' => '1',
      'fetcher_endpoint' => 'http://example.com/fetcher',
    ];
    $this->formState->method('getValues')->willReturn($form_state_values);

    // Mock the storage for recipients.
    $this->formState->method('getStorage')->willReturn([1, 2, 3]);

    // Mock the config factory to return a config object.
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnMap([
      ['proc-auto-filter-recipients', ProcInterface::STRATEGY_PERMISSIVE],
      ['proc-suppress-encryption-success-msg', FALSE],
      ['proc-suppress-decrypt-link', FALSE],
    ]);
    $this->configFactory->method('get')->willReturn($config);

    // Mock the entity type manager to return a proc entity.
    $proc_entity = $this->createMock(EntityInterface::class);
    $proc_entity->method('id')->willReturn(1);
    // $proc_entity->method('getOwnerId')->willReturn(1);
    //    $proc_entity->method('get')->willReturn($this->createMock(\Drupal\Core\Field\FieldItemListInterface::class));
    $this->entityTypeManager->method('getStorage')->willReturn($this->createMock(EntityStorageInterface::class));
    // $this->entityTypeManager->getStorage('proc')->method('load')->willReturn($proc_entity);
    // Mock the user storage to return a user entity.
    $user_entity = $this->createMock(UserInterface::class);
    $user_entity->method('id')->willReturn(1);
    $user_entity->method('label')->willReturn('test_user');
    $this->entityTypeManager->getStorage('user')->method('loadMultiple')->willReturn([1 => $user_entity]);

    // Call the submitForm method.
    $form = ['test' => 'test'];
    $complete_form = $this->form->buildForm($form, $this->formState);

    $this->form->submitForm($complete_form, $this->formState);

    // Assert that the form submission did not throw any exceptions.
    $this->assertTrue(TRUE);
  }

  /**
   * Tests the setMetadataMessage method.
   *
   * @covers ::setMetadataMessage
   */
  public function testSetMetadataMessage(): void {
    $response = new AjaxResponse();
    $file_name = 'testfile.txt';
    $proc_object_label = 'testfile.txt';
    $field_name = 'proc-file';
    $proc_id = '1';
    $proc_entity = $this->createMock('Drupal\proc\Entity\Proc');
    $new_proc_entity = $this->createMock('Drupal\proc\Entity\Proc');

    $complete_form = [
      '#attached' => [
        'drupalSettings' => [
          'proc' => [
            'proc_field_delta' => '0',
            'proc_form_triggering_field' => 'edit-proc-file-0',
          ],
        ],
      ],
    ];

    $this->formState->expects($this->any())
      ->method('getCompleteForm')
      ->willReturn($complete_form);

    $this->procEncryptForm->setMetadataMessage(
      $response,
      $file_name,
      $proc_object_label,
      $this->formState,
      $field_name,
      $proc_id,
      $proc_entity,
      $new_proc_entity
    );

    $commands = $response->getCommands();
    $this->assertCount(2, $commands);
    // Assert that array $commands contains the correct commands.
    $this->assertEquals('insert', $commands[0]['command']);
    $this->assertEquals('invoke', $commands[1]['command']);
  }

  /**
   * Tests the getRecipientUsers method.
   */
  public function testGetRecipientUsers() {
    $recipients_set_ids = [1, 2, 3];
    $result = $this->procEncryptForm->getRecipientUsers($recipients_set_ids);

    // Assert that the correct recipient users are returned.
    $this->assertArrayHasKey('recipient_users', $result);
    $this->assertArrayHasKey('rejected_users', $result);
  }

  /**
   * Tests the isFileInputMode method.
   */
  public function testIsFileInputMode() {
    $_GET['proc_in_mode'] = ProcInterface::INPUT_MODE_TEXTFIELD;
    $this->assertFalse($this->procEncryptForm->isFileInputMode());
  }

  /**
   * Tests the denyAccess method.
   */
  public function testBuildFormWithMissingQueryParameters() {
    $form = [];
    $form_state = $this->formState;

    // Simulate missing query parameters.
    unset($_GET['proc_in_mode']);
    $built_form = $this->procEncryptForm->buildForm($form, $form_state);

    // Assert that the form is still built correctly.
    $this->assertArrayHasKey('submit-proc', $built_form['actions']);
  }

}
