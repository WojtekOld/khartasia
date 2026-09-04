<?php

namespace Drupal\proc\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\CleanUpExpiredUpdateJobService;
use Drupal\proc\Service\ProcJsonFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Component\Utility\Environment;

/**
 * Update content.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcMyUpdateForm extends ProcUpdateForm {

  /**
   * Update.
   *
   * @See \Drupal\proc\ProcInterface::PROC_ENCRYPTION_LIBRARIES
   */
  const OPERATION = 3;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected ?LoggerInterface $logger;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The environment service.
   *
   * @var \Drupal\Component\Utility\Environment
   */
  protected Environment $environment;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected FileSystem $fileSystem;

  /**
   * The JSON file service.
   *
   * @var \Drupal\proc\Service\ProcJsonFileService
   */
  protected ProcJsonFileService $jsonFileService;

  /**
   * The clean up expired update job service.
   *
   * @var \Drupal\proc\Service\CleanUpExpiredUpdateJobService
   */
  protected CleanUpExpiredUpdateJobService $cleanUpExpiredUpdateJob;

  /**
   * ProcEncryptForm form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path.
   * @param \Drupal\proc\ProcKeyManagerInterface $procKeyManager
   *   The ProcKeyManager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param ?Drupal\file\FileRepository $file_repository
   *   The file repository.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   * @param \Drupal\Component\Utility\Environment $environment
   *   The environment service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   * @param \Drupal\proc\Service\ProcJsonFileService $json_file_service
   *   The JSON file service.
   * @param \Drupal\proc\Service\CleanUpExpiredUpdateJobService $clean_up_expired_update_job
   *   The clean up expired update job service.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    CurrentPathStack $currentPathStack,
    ProcKeyManagerInterface $procKeyManager,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerInterface $logger,
    Renderer $renderer,
    AccountProxy $current_user,
    FileRepository $file_repository,
    DatabaseFileUsageBackend $file_usage,
    Environment $environment,
    Connection $database,
    FileSystem $fileSystem,
    ProcJsonFileService $json_file_service,
    CleanUpExpiredUpdateJobService $clean_up_expired_update_job,
  ) {
    parent::__construct(
      $config_factory,
      $module_handler,
      $currentPathStack,
      $procKeyManager,
      $entityTypeManager,
      $logger,
      $renderer,
      $current_user,
      $file_repository,
      $file_usage,
      $environment,
      $database,
      $fileSystem,
      $json_file_service,
      $clean_up_expired_update_job
    );
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('path.current'),
      $container->get('proc.key_manager'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('proc'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('proc.environment'),
      $container->get('database'),
      $container->get('file_system'),
      $container->get('proc.proc_json_file_service'),
      $container->get('proc.clean_up_expired_update_job')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'proc_update_form';
  }

  /**
   * Title for update form.
   */
  public function getTitle(): TranslatableMarkup {
    return $this->t('My re-encryption tasks');
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateCiphersData(): array {
    try {
      $keyring = $this->procKeyManager->getKeys($this->currentUser->id(), 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new AccessDeniedHttpException('Error retrieving keyring for user.');
    }
    if (!$keyring) {
      throw new AccessDeniedHttpException('No keyring found for user.');
    }
    $meta = $keyring['keyring_entity']->get('meta')->getValue()[0] ?? [];
    if (empty($meta['update_jobs'])) {
      throw new AccessDeniedHttpException('No update jobs found for user.');
    }
    $update_jobs = $meta['update_jobs'];
    $requested_csv = '';
    $route_components = explode('/', $this->currentPathStack->getPath());
    if (isset($route_components[3])) {
      $requested_csv = $this->getCsvArgument($route_components[3]) ?? '';
    }
    if ($requested_csv) {
      $requested_procs = array_keys($this->getCipher(
        $requested_csv, FALSE
      )['ciphers']);
      if (empty(array_diff($requested_procs, $update_jobs))) {
        $update_jobs = $requested_procs;
      }
    }
    // Message the count of remaining update jobs.
    $this->messenger()->addStatus($this->t('You still have @count content items remaining to update.', [
      '@count' => count($update_jobs),
    ]));

    $this->applyMaxFilesLimit($update_jobs);
    $count = count($update_jobs);
    $this->messenger()->addStatus($this->formatPlural(
      $count,
      'There is 1 content item selected for update now.',
      'There are @count content items selected for update now.',
      ['@count' => $count]
    ));

    $ciphers_data = $this->fetchCiphersData($update_jobs);
    $this->applyMaxSizeLimit($ciphers_data);

    if (empty($ciphers_data['pubkey'])) {
      throw new AccessDeniedHttpException('No public key found for user.');
    }

    return [
      'ciphers_data' => $ciphers_data,
      'common_data' => $this->procKeyManager->getPrivKeyMetadata($ciphers_data['privkey']),
    ];
  }

  /**
   * Apply the maximum files limit to the update jobs.
   *
   * @param array $update_jobs
   *   The update jobs to apply the limit to.
   */
  private function applyMaxFilesLimit(array &$update_jobs): void {
    $settings = $this->configFactory->get('proc.settings');
    $maxFiles = $settings->get('proc-max-files-update');

    if ($maxFiles && is_numeric($maxFiles)) {
      $maxFiles = (int) $maxFiles;
      if ($maxFiles < count($update_jobs)) {
        $update_jobs = array_slice($update_jobs, 0, $maxFiles);
      }
    }
  }

  /**
   * Fetch the ciphers data based on the update jobs.
   *
   * @param array $updateJobs
   *   The update jobs to fetch data for.
   *
   * @return array
   *   The ciphers data.
   */
  private function fetchCiphersData(array $updateJobs): array {
    return $this->getCipher(
      $this->getCsvArgument(implode(',', $updateJobs)),
      FALSE
    );
  }

  /**
   * Apply the maximum size limit to the ciphers data.
   *
   * @param array $ciphers_data
   *   The ciphers data to apply the limit to.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  private function applyMaxSizeLimit(array &$ciphers_data): void {
    $maxSize = $this->configFactory->get('proc.settings')->get('proc-max-size-update')
      ?: round(($this->environment->getUploadMaxSize() / 1000000) / 4);

    $sumSizes = 0.0;
    $allowedIds = [];

    $toFloat = fn($value): float => is_object($value) && method_exists($value, '__toString')
      ? (float) $value->__toString()
      : (float) $value;

    foreach ($ciphers_data['ciphers'] as $cipherId => $cipherData) {
      $sizeRaw = $cipherData['source_file_size'] ?? 0;
      $sumSizes += $toFloat($sizeRaw) / 1000000;
      if ($sumSizes > $maxSize) {
        break;
      }
      $allowedIds[] = $cipherId;
    }
    if (empty($allowedIds)) {
      throw new AccessDeniedHttpException('The total size of files exceeds the maximum allowed size for update.');
    }
    if (count($allowedIds) < count($ciphers_data['ciphers'])) {
      $ciphers_data['ciphers'] = array_intersect_key(
        $ciphers_data['ciphers'],
        array_flip($allowedIds)
      );
    }
  }

}
