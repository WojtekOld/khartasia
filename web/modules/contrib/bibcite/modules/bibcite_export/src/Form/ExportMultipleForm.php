<?php

namespace Drupal\bibcite_export\Form;

use Drupal\bibcite\Plugin\BibciteFormatManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Export multiple reference entities.
 */
class ExportMultipleForm extends ConfirmFormBase {

  /**
   * The array of entities to export.
   *
   * @var array
   */
  protected array $entityInfo = [];

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Bibcite format manager service.
   *
   * @var \Drupal\bibcite\Plugin\BibciteFormatManagerInterface
   */
  protected BibciteFormatManagerInterface $formatManager;

  /**
   * Extenstion list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $extensionList;

  /**
   * Construct new ExportMultipleForm object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\bibcite\Plugin\BibciteFormatManagerInterface $format_manager
   *   The bibcite format manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list
   *   Module extension list service
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, BibciteFormatManagerInterface $format_manager, AccountInterface $current_user, ModuleExtensionList $extension_list) {
    $this->tempStore = $temp_store_factory->get('bibcite_export_multiple');
    $this->formatManager = $format_manager;
    $this->currentUser = $current_user;
    $this->extensionList = $extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore */
    $tempstore = $container->get('tempstore.private');
    /** @var \Drupal\bibcite\Plugin\BibciteFormatManagerInterface $format_manager */
    $format_manager = $container->get('plugin.manager.bibcite_format');
    /** @var \Drupal\Core\Session\AccountInterface $current_user */
    $current_user = $container->get('current_user');
    /** @var \Drupal\Core\Extension\ModuleExtensionList $extension_list */
    $extension_list = $container->get('extension.list.module');
    return new static(
      $tempstore,
      $format_manager,
      $current_user,
      $extension_list
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bibcite_export_multiple';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Select the format to export these references.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.bibcite_reference.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Export');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->entityInfo = $this->tempStore->get($this->currentUser->id());

    $form['entities'] = [
      '#theme' => 'item_list',
      '#items' => $this->entityInfo,
    ];

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Export format'),
      '#options' => array_map(function ($format) {
        return $format['label'];
      }, $this->formatManager->getExportDefinitions()),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $format = $this->formatManager->createInstance($form_state->getValue('format'));
    $entity_type = 'bibcite_reference';

    $ids = array_keys($this->entityInfo);
    $chunks = array_chunk($ids, 100);

    $operations = [];
    foreach ($chunks as $chunk) {
      $operations[] = [
        'bibcite_export_batch_list', [$chunk, $entity_type, $format],
      ];
    }

    $batch = [
      'title' => t('Export references'),
      'operations' => $operations,
      'file' => $this->extensionList->getPath('bibcite_export') . '/bibcite_export.batch.inc',
      'finished' => 'bibcite_export_batch_finished',
    ];

    batch_set($batch);
  }

}
