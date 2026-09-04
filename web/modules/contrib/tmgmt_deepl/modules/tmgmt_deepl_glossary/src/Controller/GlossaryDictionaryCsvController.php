<?php

namespace Drupal\tmgmt_deepl_glossary\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for CSV download of glossary dictionaries.
 */
class GlossaryDictionaryCsvController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @codeCoverageIgnoreStart
    $instance = parent::create($container);
    $instance->fileSystem = $container->get('file_system');
    return $instance;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Downloads a glossary dictionary as a CSV file.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface $deepl_ml_glossary_dictionary
   *   The glossary dictionary entity.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The CSV file response.
   *
   * @throws \Exception
   *   Thrown if temporary file creation fails.
   */
  public function download(DeeplMultilingualGlossaryDictionaryInterface $deepl_ml_glossary_dictionary): BinaryFileResponse {
    // Generate CSV content.
    $csv_content = $this->generateCsvContent($deepl_ml_glossary_dictionary);

    // Generate filename.
    $filename = $this->generateFileName($deepl_ml_glossary_dictionary);

    // Create temporary file using Drupal's temporary directory scheme.
    $temp_file = $this->fileSystem->tempnam('temporary://', 'glossary_dictionary_csv_');
    if ($temp_file === FALSE) {
      throw new \Exception('Failed to create temporary file for CSV download.');
    }

    // @codeCoverageIgnoreStart
    /** @var string $temp_file */
    $this->fileSystem->saveData($csv_content, $temp_file, FileExists::Replace);

    // Create and return the binary file response.
    $response = new BinaryFileResponse($temp_file);
    $response->setContentDisposition('attachment', $filename);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');

    return $response;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Generates CSV content from the glossary dictionary entity.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface $entity
   *   The glossary dictionary entity.
   *
   * @return string
   *   The CSV content.
   */
  protected function generateCsvContent(DeeplMultilingualGlossaryDictionaryInterface $entity): string {
    /** @var array<string, string> $entries */
    $entries = $entity->getEntries();

    // Create CSV using a simple approach.
    $csv_lines = [];

    // Add entries as CSV lines.
    foreach ($entries as $source => $target) {
      $csv_lines[] = $source . ',' . $target;
    }

    return implode("\n", $csv_lines);
  }

  /**
   * Generates the CSV filename.
   *
   * Filename format: dictionary_{SOURCE_LANG}_{TARGET_LANG}_{DATETIME}.csv.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface $entity
   *   The glossary dictionary entity.
   *
   * @return string
   *   The generated filename.
   */
  protected function generateFileName(DeeplMultilingualGlossaryDictionaryInterface $entity): string {
    $source_lang = $entity->getSourceLanguage();
    $target_lang = $entity->getTargetLanguage();
    $datetime = date('Y-m-d_H-i-s');

    return "dictionary_{$source_lang}_{$target_lang}_{$datetime}.csv";
  }

}
