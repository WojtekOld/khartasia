<?php

namespace Drupal\bibcite_entity\Form;

use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirm merge of bibliographic entities.
 */
class MergeConfirmForm extends ConfirmFormBase {

  /**
   * This entity will be merged to target.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $source;

  /**
   * Source entity will be merged to this one.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $target;

  /**
   * The field name for filtering.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Extenstion list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $extensionList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Routing\CurrentRouteMatch $current_route */
    $current_route = $container->get('current_route_match');
    /** @var \Drupal\Core\Extension\ModuleExtensionList $extension_list */
    $extension_list = $container->get('extension.list.module');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $manager */
    $manager = $container->get('entity_type.manager');
    return new static($current_route, $manager, $extension_list);
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteMatchInterface $route_match, EntityTypeManagerInterface $manager, ModuleExtensionList $extension_list) {
    $parameter_name = $route_match->getRouteObject()->getOption('_bibcite_entity_type_id');

    $this->source = $route_match->getParameter($parameter_name);
    $this->target = $route_match->getParameter("{$parameter_name}_target");
    $this->entityTypeManager = $manager;
    $this->extensionList = $extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bibcite_entity_merge_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to merge @source to @target?', [
      '@source' => $this->source->label(),
      '@target' => $this->target->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->source->toUrl('bibcite-merge-form');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $field_name = NULL) {
    $this->fieldName = $field_name;

    $statistic = $this->getAuthoredReferencesStatistic();

    $form['references'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('This operation will cause changes in these references'),
      'items' => [
        '#markup' => $this->t('No one reference will be changed.'),
      ],
    ];

    if (count($statistic['entities']) > 0) {
      $items = array_map(function (ReferenceInterface $reference) {
        return $reference->label();
      }, $statistic['entities']);

      $form['references']['items'] = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }

    if ($statistic['count'] > 0) {
      $form['references']['count'] = [
        '#markup' => $this->t('and @count more', ['@count' => $statistic['count']]),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => t('Merging'),
      'operations' => [
        [
          'bibcite_entity_merge_entity', [
            $this->source->id(),
            $this->target->id(),
            $this->source->getEntityTypeId(),
            $this->fieldName,
          ],
        ],
        [
          'bibcite_entity_merge_entity_delete', [
            $this->source->id(),
            $this->source->getEntityTypeId(),
            $this->fieldName,
          ],
        ],
      ],
      'finished' => 'bibcite_entity_merge_entity_finished',
      'file' => $this->extensionList->getPath('bibcite_entity') . '/bibcite_entity.batch.inc',
    ];

    batch_set($batch);
  }

  /**
   * Find references and get statistic data.
   *
   * @return array
   *   Statistic data with first 10 objects and count of another references.
   */
  private function getAuthoredReferencesStatistic() {
    $storage = $this->entityTypeManager->getStorage('bibcite_reference');

    $range = 10;

    $query = $storage->getQuery()->accessCheck();
    $query->condition($this->fieldName, $this->source->id());
    $query->range(0, $range);

    $entities = $storage->loadMultiple($query->execute());
    $count = $query->range()->count()->execute();

    return [
      'entities' => $entities,
      'count' => ($count > $range) ? $count - $range : 0,
    ];
  }

}
