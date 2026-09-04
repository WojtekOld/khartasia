<?php

namespace Drupal\proc\Entity\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\proc\ByteSizeFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for proc entity.
 *
 * @ingroup proc
 */
class ProcListBuilder extends EntityListBuilder {

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
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entityTypeManager,
    DateFormatterInterface $date_formatter,
    RedirectDestinationInterface $redirect_destination,
    TranslationManager $stringTranslation,
    ByteSizeFormatter $byteSizeFormatter,
  ) {
    parent::__construct(
      $entity_type,
      $entityTypeManager->getStorage($entity_type->id())
    );
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
    $this->stringTranslation = $stringTranslation;
    $this->byteSizeFormatter = $byteSizeFormatter;
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
      $container->get('proc.byte_size_formatter')
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
    $content_row['title']['data'] = [
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

}
