<?php

namespace Drupal\bibcite\Plugin\BibCiteProcessor;

use Drupal\bibcite\Attribute\BibCiteProcessor;
use Drupal\bibcite\Plugin\BibCiteProcessorBase;
use Drupal\bibcite\Plugin\BibCiteProcessorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Seboettg\CiteProc\CiteProc;
use Seboettg\CiteProc\Util\Variables;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a style provider based on citeproc-php library.
 *
 * @BibCiteProcessor(
 *   id = "citeproc-php",
 *   label = @Translation("Citeproc PHP"),
 * )
 */
#[
  BibCiteProcessor(
    id: "citeproc-php",
    label: new TranslatableMarkup("Citeproc PHP"),
  )
]

class CiteprocPhp extends BibCiteProcessorBase implements BibCiteProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Render citation by citeproc-php library');
  }

  /**
   * {@inheritdoc}
   */
  public function render($data, $csl, $lang) {
    $cite_proc = new CiteProc($csl, $lang);

    if (!$data instanceof \stdClass) {
      $data = json_decode(json_encode($data));
    }

    $data = $this->processDates($data);

    try {
      return preg_replace('/(\\n|\r)( *)/', '', $cite_proc->render([$data]));
    }
    catch (\Exception $e) {
      $label = $data->{'citation-label'} ?? '';
      $this->logWarning($label, $e->getMessage());
      return '';
    }
  }

  /**
   * Strips CSL date fields whose month or day values are out of range.
   *
   * Malformed dates trigger exceptions in citeproc-php. Removing the
   * field lets the rest of the citation render with a blank date instead.
   */
  protected function processDates(\stdClass $data): \stdClass {

    foreach (Variables::DATE_VARIABLES as $field) {
      if (!isset($data->$field->{'date-parts'}) || !is_array($data->$field->{'date-parts'})) {
        continue;
      }

      foreach ($data->$field->{'date-parts'} as $parts) {
        if (!is_array($parts)) {
          $year = (int) $parts;
        }
        else {
          $year = (int) $parts[0];
        }
        // Use fallback of 1 simply for validation checking.
        $month = isset($parts[1]) ? (int) $parts[1] : 1;
        $day   = isset($parts[2]) ? (int) $parts[2] : 1;

        if (!checkdate($month, $day, $year)) {
          $label = $data->{'citation-label'} ?? '';
          $this->logWarning($label, "field \"$field\" has invalid values (month=$month, day=$day)");
          unset($data->$field);
          break;
        }
      }
    }

    return $data;
  }

  /**
   * Logs a watchdog warning with a link to the offending reference.
   */
  protected function logWarning(string $label, string $detail): void {
    if (preg_match('/(\d+)$/', $label, $m)) {
      \Drupal::logger('bibcite')->warning(
        'Invalid date data in reference <a href="/bibcite/reference/@id">@label</a>: @detail',
        ['@id' => $m[1], '@label' => $label, '@detail' => $detail]
      );
    }
    else {
      \Drupal::logger('bibcite')->warning(
        'Invalid data in reference "@label": @detail',
        ['@label' => $label ?: 'unknown', '@detail' => $detail]
      );
    }
  }

}
