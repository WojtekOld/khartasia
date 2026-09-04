<?php

namespace Drupal\proc\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystem;
use Drupal\file\FileRepository;
use Drupal\proc\ProcInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service class for handling JSON file operations related to proc.
 */
class ProcJsonFileService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected FileSystem $fileSystem;

  /**
   * The file repository.
   *
   * @var \Drupal\file\Entity\FileRepository
   */
  protected ?FileRepository $fileRepository = NULL;

  /**
   * ProcEncryptForm form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   * @param ?Drupal\file\FileRepository $file_repository
   *   The file repository.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    FileSystem $fileSystem,
    FileRepository $file_repository,
  ) {
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->fileRepository = $file_repository;
  }

  /**
   * Create a new instance of the service.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('file.repository'),
    );
  }

  /**
   * Save JSON content as separate files if needed.
   *
   * @param null|string $json_content
   *   JSON content to be saved.
   *
   * @return array
   *   Array of file IDs.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Random\RandomException
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function saveJsonFiles(null|string $json_content): array {
    $config = $this->configFactory->get('proc.settings');

    if (!$config) {
      return [];
    }

    $stream_wrapper = $config->get('proc-stream-wrapper');

    $json_fids = [];
    $file_id = FALSE;

    if ($config->get('proc-enable-stream-wrapper') === ProcInterface::ENABLE_STREAM_WRAPPER_STORAGE && !empty($stream_wrapper)) {

      $block_size = $config->get('proc-file-block-size');
      $blocks_split_enabled = $config->get('proc-enable-block-size');

      $this->createDirectory($stream_wrapper);
      if ($json_content) {
        if ($blocks_split_enabled && !empty($block_size)) {
          $blocks_texts = $this->splitContentIntoBlocks($json_content, $block_size);
          foreach ($blocks_texts as $block_text) {
            $json_fids[] = $this->writeJsonBlock($stream_wrapper, $block_text);
          }
        }

        if (!$blocks_split_enabled || empty($block_size)) {
          $file_id = $this->writeJsonBlock($stream_wrapper, $json_content);
        }
      }
    }

    return ['json_fids' => $json_fids, 'file_id' => $file_id];
  }

  /**
   * Create directory if it does not exist.
   *
   * @param string $directory
   *   The directory path.
   */
  public function createDirectory(string $directory): void {
    if (!is_dir($directory)) {
      $this->fileSystem->mkdir($directory, NULL, TRUE);
    }
  }

  /**
   * Split content into blocks based on block size.
   *
   * @param string $json_content
   *   JSON content to be split.
   * @param int $block_size
   *   Size of each block.
   *
   * @return array
   *   Array of content blocks.
   */
  public function splitContentIntoBlocks(string $json_content, int $block_size): array {
    $lines = explode("\n", $json_content);
    $content_lines_number = count($lines);
    $lines_size_ratio = $content_lines_number / $block_size;
    $blocks = intval($lines_size_ratio);
    $remaining = $content_lines_number % $block_size;

    if ($remaining > 0) {
      $blocks++;
    }

    $blocks_lines = [];
    $content_line_index = 0;
    $blocks_index = 0;

    while ($blocks_index < $blocks) {
      $line_in_block_index = 0;
      while ($line_in_block_index < $block_size) {
        if (isset($lines[$content_line_index])) {
          $blocks_lines[$blocks_index][] = $lines[$content_line_index];
        }
        $content_line_index++;
        $line_in_block_index++;
      }
      $blocks_index++;
    }
    $blocks_texts = [];
    foreach ($blocks_lines as $block_index => $block_lines) {
      foreach ($block_lines as $block_line) {
        $blocks_texts[$block_index] = $blocks_texts[$block_index] . "\n" . $block_line;
      }
    }

    return $blocks_texts;
  }

  /**
   * Write JSON block to a file and return the file ID.
   *
   * @param string $json_dest
   *   Destination directory for JSON files.
   * @param string $block_text
   *   JSON block content.
   *
   * @return int
   *   File ID of the written JSON block.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Random\RandomException
   */
  public function writeJsonBlock(string $json_dest, string $block_text): int {
    $json_filename = $this->hashBase64($this->generateRandomString(32)) . '.json';
    $jsonFid = $this->fileRepository->writeData(
      $block_text,
      "$json_dest/$json_filename"
    );

    return $jsonFid->id();
  }

  /**
   * Hashes data and returns a URL-safe base64-encoded string.
   *
   * This method replicates the logic of Crypt::hashBase64().
   *
   * @param string|null $data
   *   The data to hash.
   *
   * @return string|null
   *   A base-64 encoded sha-256 hash.
   *
   * @see Crypt::hashBase64()
   */
  public function hashBase64(string|null $data): string|null {
    return $this->replaceUrlUnsafeCharacters(base64_encode(hash('sha256', $data, TRUE)));
  }

  /**
   * Replaces characters that are unsafe in URLs.
   *
   * This method replicates the logic of Crypt::hashBase64(),
   *  Crypt::randomBytesBase64().
   *
   * @param string $text
   *   The text to modify.
   *
   * @return string|null
   *   A string with + replaced with -, / with _ and
   *    any = padding characters removed.
   *
   * @see Crypt::hashBase64()
   * @see Crypt::randomBytesBase64()
   */
  public function replaceUrlUnsafeCharacters(string $text): string|null {
    // Modify the hash so it's safe to use in URLs.
    return preg_replace(['/\+/', '/\//', '/=/'], ['-', '_', ''], $text);
  }

  /**
   * Generates a cryptographically secure random string.
   *
   * This method replicates the logic of Crypt::randomBytesBase64() to ensure
   * backward compatibility with existing data in production environments.
   *
   * @param int $count
   *   The number of random bytes to generate.
   *
   * @return string|null
   *   A URL-safe base64-encoded random string.
   *
   * @see Crypt::randomBytesBase64()
   *
   * @throws \Random\RandomException
   */
  public function generateRandomString(int $count): string|null {
    return $this->replaceUrlUnsafeCharacters(base64_encode(random_bytes($count)));
  }

}
