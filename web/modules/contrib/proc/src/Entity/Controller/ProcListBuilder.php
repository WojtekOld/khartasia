<?php

namespace Drupal\proc\Entity\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\proc\ByteSizeFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for proc entity.
 *
 * @ingroup proc
 */
class ProcListBuilder extends EntityListBuilder implements FormInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $stringTranslation;

  /**
   * The byte size formatter service.
   *
   * @var \Drupal\proc\ByteSizeFormatter
   */
  protected ByteSizeFormatter $byteSizeFormatter;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The entities being listed, keyed by ID.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $entities = [];

  /**
   * Constructs a new ProcListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $stringTranslation
   *   The string translation service.
   * @param \Drupal\proc\ByteSizeFormatter $byteSizeFormatter
   *   The byte size formatter service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store factory.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entityTypeManager,
    DateFormatterInterface $date_formatter,
    RedirectDestinationInterface $redirect_destination,
    TranslationManager $stringTranslation,
    ByteSizeFormatter $byteSizeFormatter,
    FormBuilderInterface $form_builder,
    PrivateTempStoreFactory $temp_store_factory,
  ) {
    parent::__construct(
      $entity_type,
      $entityTypeManager->getStorage($entity_type->id())
    );
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
    $this->stringTranslation = $stringTranslation;
    $this->byteSizeFormatter = $byteSizeFormatter;
    $this->formBuilder = $form_builder;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ): ProcListBuilder|EntityListBuilder|EntityHandlerInterface|static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('redirect.destination'),
      $container->get('string_translation'),
      $container->get('proc.byte_size_formatter'),
      $container->get('form_builder'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'id' => $this->t('Proc ID'),
      'label' => [
        'data' => $this->t('Label'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'owner' => [
        'data' => $this->t('Owner'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'type' => $this->t('Type'),
      'status' => [
        'data' => $this->t('Status'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'created' => [
        'data' => $this->t('Created'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'changed' => [
        'data' => $this->t('Changed'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'size' => [
        'data' => $this->t('Size'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function buildRow(EntityInterface $entity): array {
    $age_flag = '';
    // Proc entities less than 30 days old are considered new:
    $thirty_days_ago = \Drupal::time()->getRequestTime() - (30 * 24 * 60 * 60);
    if ($entity->getCreated() > $thirty_days_ago) {
      $age_flag = $this->t('New');
    }
    $content_row = [];
    $content_row['id'] = $entity->id();
    $content_row['label']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#suffix' => ' ' . $age_flag,
      '#url' => $entity->toUrl(),
    ];

    $content_row['owner'] = $entity->getOwner() ? $entity->getOwner()->getAccountName() : $this->t('Anonymous');
    $content_row['type'] = $entity->getType();
    $content_row['status'] = $entity->getStatus() ? $this->t('published') : $this->t('not published');
    $content_row['created'] = $this->dateFormatter->format((int) $entity->getCreated(), 'short');
    $content_row['changed'] = $entity->getChangedTime() ? $this->dateFormatter->format($entity->getChangedTime(), 'short') : $this->t('not changed');
    if (isset($entity->getMeta()[0])) {
      $content_row['size'] = $entity->getType() == 'cipher'
        ? $this->byteSizeFormatter->formatSize(intval($entity->getMeta()[0]['source_file_size']))
        : $this->byteSizeFormatter->formatSize(intval($entity->getMeta()[0]['key_size']));
    }
    return $content_row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Render the listing as a form so bulk operations (delete) are available.
   */
  public function render(): array {
    return $this->formBuilder->getForm($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_admin_overview_form';
  }

  /**
   * {@inheritdoc}
   *
   * Builds the overview table with a selection checkbox per row and a "Delete"
   * bulk action, matching the core admin/content pattern.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->entities = $this->load();

    $options = [];
    foreach ($this->entities as $id => $entity) {
      $options[$id] = $this->buildRow($entity);
    }

    $form['procs'] = [
      '#type' => 'tableselect',
      '#header' => $this->buildHeader(),
      '#options' => $options,
      '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#button_type' => 'primary',
    ];

    // Provide the standard pager below the actions.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_filter((array) $form_state->getValue('procs'));
    if (empty($selected)) {
      $form_state->setErrorByName('procs', $this->t('No items selected. Select one or more items to delete.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * Stages the selected proc entities in the shared temp store and redirects to
   * the generic entities deletion confirmation form, exactly as core does for
   * content entities.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_ids = array_keys(array_filter((array) $form_state->getValue('procs')));

    $entity_type_id = $this->entityType->id();
    $selection = [];
    $entities = $this->storage->loadMultiple($selected_ids);
    foreach ($entities as $id => $entity) {
      $langcode = $entity->language()->getId();
      $selection[$id][$langcode] = $langcode;
    }

    $this->tempStoreFactory
      ->get('entity_delete_multiple_confirm')
      ->set(\Drupal::currentUser()->id() . ':' . $entity_type_id, $selection);

    $form_state->setRedirect('entity.' . $entity_type_id . '.delete_multiple_form', [
      'entity_type_id' => $entity_type_id,
    ]);
  }

}
