<?php

declare(strict_types=1);

namespace Drupal\security_review\Plugin\SecurityCheck;

use Drupal\Core\Link;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\security_review\Attribute\SecurityCheck;
use Drupal\security_review\CheckResult;
use Drupal\security_review\SecurityCheckBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Checks whether the private files' directory is under the web root.
 */
#[SecurityCheck(
  id: 'private_files',
  title: new TranslatableMarkup('Private files'),
  description: new TranslatableMarkup("Checks whether the private files' directory is under the web root."),
  namespace: new TranslatableMarkup('Security Review'),
  success_message: new TranslatableMarkup('Private files directory is outside the web server root.'),
  failure_message: new TranslatableMarkup('Private files is enabled but the specified directory is not secure outside the web server root.'),
  info_message: new TranslatableMarkup('Private files is not enabled.'),
  help: [
    new TranslatableMarkup("If you have Drupal's private files feature enabled you should move the files directory outside of the web server\'s document root. Drupal will secure access to files that it renders the link to, but if a user knows the actual system path they can circumvent Drupal\'s private files feature. You can protect against this by specifying a files directory outside of the webserver root."),
  ]
)]
class PrivateFiles extends SecurityCheckBase {

  /**
   * StreamWrapper service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->streamWrapperManager = $container->get('stream_wrapper_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(bool $cli = FALSE): void {
    $result = CheckResult::SUCCESS;

    // Check if stream wrapper is used.
    if ($wrapper = $this->streamWrapperManager->getViaScheme('private')) {
      $file_directory_path = $wrapper->realPath();
    }
    // Fallback to the previous approach.
    else {
      $base = PrivateStream::basePath();
      $file_directory_path = !is_null($base) ? realpath($base) : NULL;
    }

    $filesystem = new Filesystem();
    if (empty($file_directory_path)) {
      // Private files feature is not enabled.
      $result = CheckResult::INFO;
    }
    elseif (
      // Make a relative path from the Drupal root to the private files path; if
      // the relative path doesn't start with, '../', it's most likely contained
      // in the Drupal root.
      !str_starts_with($filesystem->makePathRelative($file_directory_path, DRUPAL_ROOT), '../') &&
      // Double check that the private files path does not start with the Drupal
      // root path in case no relative path could be generated, e.g. the private
      // files path is on another drive or network share. In those cases, the
      // Filesystem component will just return an absolute path. Also note the
      // use of \DIRECTORY_SEPARATOR to ensure we don't match an adjacent
      // private files directory that starts with the Drupal directory name.
      str_starts_with($file_directory_path, DRUPAL_ROOT . DIRECTORY_SEPARATOR)
    ) {
      // Path begins at the root.
      $result = CheckResult::FAIL;
    }

    $this->createResult($result, ['path' => $file_directory_path]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDetails(array $findings, array $hushed = [], bool $returnString = FALSE): array|string {
    $output = $returnString ? '' : [];

    if (empty($findings)) {
      return $output;
    }

    $paragraphs = [];
    $paragraphs[] = $this->t('Your files directory is not outside of the server root.');
    $paragraphs[] = Link::createFromRoute(
      $this->t('Edit the files directory path.'),
      'system.file_system_settings'
    );

    if ($returnString) {
      $output .= $this->t('Private files directory: @path', ['@path' => $findings['path']]);
    }
    else {
      $output[] = [
        '#theme' => 'check_evaluation',
        '#finding_items' => $paragraphs,
      ];
    }

    return $output;
  }

}
