<?php

namespace Drupal\bibcite_import\Form;

use Drupal\bibcite\Plugin\BibciteFormatManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Common import form.
 */
class ImportForm extends FormBase {

  /**
   * Bibcite format manager service.
   *
   * @var \Drupal\bibcite\Plugin\BibciteFormatManagerInterface
   */
  protected $formatManager;

  /**
   * Serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * Extenstion list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $extensionList;

  /**
   * Import form constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   Import plugins manager.
   * @param \Drupal\bibcite\Plugin\BibciteFormatManagerInterface $format_manager
   *   Bibcite format manager service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list
   *   Module extension list service
   */
  public function __construct(SerializerInterface $serializer, BibciteFormatManagerInterface $format_manager, ModuleExtensionList $extension_list) {
    $this->serializer = $serializer;
    $this->formatManager = $format_manager;
    $this->extensionList = $extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Symfony\Component\Serializer\SerializerInterface $serializer */
    $serializer = $container->get('serializer');
    /** @var \Drupal\bibcite\Plugin\BibciteFormatManagerInterface $format_manager */
    $format_manager = $container->get('plugin.manager.bibcite_format');
    /** @var \Drupal\Core\Extension\ModuleExtensionList $extension_list */
    $extension_list = $container->get('extension.list.module');
    return new static(
      $serializer,
      $format_manager,
      $extension_list
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bibcite_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['file'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
      '#multiple' => FALSE,
    ];
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => array_map(function ($definition) {
        return $definition['label'];
      }, $this->formatManager->getImportDefinitions()),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    if (!empty($all_files['file'])) {
      /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file_upload */
      $file_upload = $all_files['file'];
      if ($file_upload->isValid()) {
        $form_state->setValue('file', $file_upload->getRealPath());
        $format_id = $form_state->getValue('format');
        $format = $this->formatManager->getDefinition($format_id);
        try {
          $data = file_get_contents($form_state->getValue('file'));

          $decoded = $this->serializer->decode($data, $format_id);
          $form_state->setValue('decoded', $decoded);
        }
        catch (\Exception $exception) {
          $err_string = $this->t('@format file content is not valid.<br>%ex', ['@format' => $format['label'], '%ex' => $exception->getMessage()]);
          $form_state->setErrorByName('file', $err_string);
        }
        return;
      }
    }
    else {
      $form_state->setErrorByName('file', $this->t('The file could not be uploaded.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $format_id = $form_state->getValue('format');
    /** @var \Drupal\bibcite\Plugin\BibciteFormatInterface $format */
    $format = $this->formatManager->createInstance($format_id);

    $decoded = $form_state->getValue('decoded');
    $chunks = array_chunk($decoded, 50);

    $batch = [
      'title' => t('Import reference data'),
      'operations' => [],
      'finished' => 'bibcite_import_batch_finished',
      'file' => $this->extensionList->getPath('bibcite_import') . '/bibcite_import.batch.inc',
    ];

    foreach ($chunks as $chunk) {
      $batch['operations'][] = [
        'bibcite_import_batch_callback', [$chunk, $format],
      ];
    }

    batch_set($batch);
  }

}
