<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataAggregator;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\external_entities\DataAggregator\DataAggregatorBase;
use Drupal\external_entities\DataAggregator\DataAggregatorInterface;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Form\XnttSubformState;
use Drupal\external_entities\Attribute\DataAggregator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities data aggregator that only uses a single storage client.
 *
 * @DataAggregator(
 *   id = "single",
 *   label = @Translation("Single storage client"),
 *   description = @Translation("To work with only one storage client source.")
 * )
 */
#[DataAggregator(
  id: 'single',
  label: new TranslatableMarkup('Single storage client'),
  description: new TranslatableMarkup('To work with only one storage client source.'),
)]
class SingleStorageClient extends DataAggregatorBase implements DataAggregatorInterface {

  use MessengerTrait;

  /**
   * External entity type restrictions.
   *
   * @var array
   */
  protected $locks;

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) :array {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'SingleStorageClient::loadMultiple() called without proper initialization of the external entity type member.'
      );
      return [];
    }
    return $this->getStorageClient(0)->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) :int {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'SingleStorageClient::save() called without proper initialization of the external entity type member.'
      );
      throw new EntityStorageException(
        'SingleStorageClient::save() called without proper initialization of the external entity type member.'
      );
    }
    return $this->getStorageClient(0)->save($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'SingleStorageClient::delete() called without proper initialization of the external entity type member.'
      );
      return;
    }
    $this->getStorageClient(0)->delete($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'SingleStorageClient::query() called without proper initialization of the external entity type member.'
      );
      return [];
    }
    return $this->getStorageClient(0)->query(
      $parameters,
      $sorts,
      $start,
      $length,
      $unhandled_filters
    );
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(
    array $parameters = [],
    array &$unhandled_filters = [],
  ) :int {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'SingleStorageClient::countQuery() called without proper initialization of the external entity type member.'
      );
      return [];
    }
    return $this->getStorageClient(0)->countQuery(
      $parameters,
      $unhandled_filters
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) :array {
    // Check if external entity type has locks.
    if (!empty($this->externalEntityType)
       && is_a($this->externalEntityType, ConfigurableExternalEntityTypeInterface::class)
    ) {
      $this->locks = $this->externalEntityType->getLocks() ?? [];
    }

    $da_id = ($form['#attributes']['id'] ??= uniqid('da', TRUE));
    $form['storage_clients'] ??= [
      '#type' => 'container',
      '#attributes' => [
        'id' => $da_id . '_scs',
      ],
      0 => [
        '#type' => 'container',
        '#attributes' => [
          'id' => $da_id . '_sc_0',
        ],
        'id' => [],
        'config' => [
          '#attributes' => [
            'id' => $da_id . '_sc_0_conf',
          ],
        ],
      ],
    ];
    $form = $this->buildStorageClientSelectForm($form, $form_state);
    $form = $this->buildStorageClientConfigForm($form, $form_state);
    return $form;
  }

  /**
   * Build a form element for selecting a storage client.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The storage client selection form added to the given base form.
   */
  public function buildStorageClientSelectForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $storage_clients = $this->getAvailableStorageClients();
    if (empty($storage_clients)) {
      return $form;
    }

    $storage_client_options = [];
    // Manage restrictions.
    $allowed_plugins = [];
    if (!empty($this->externalEntityType)
       && is_a($this->externalEntityType, ConfigurableExternalEntityTypeInterface::class)
    ) {
      // Check if external entity type has locks.
      $locks = $this->externalEntityType->getLocks() ?? [];
      if (!empty($locks['lock_data_aggregator']['lock_storage_clients'][0]['allow_plugins'])) {
        $allowed_plugins = $locks['lock_data_aggregator']['lock_storage_clients'][0]['allow_plugins'];
      }
    }
    $group_name_lookup = [
      'rest' => '' . $this->t('Rest clients'),
      'files' => '' . $this->t('File clients'),
      'ql' => '' . $this->t('Query Language clients'),
      'others' => '' . $this->t('Other clients'),
    ];
    foreach (array_keys($group_name_lookup) as $group) {
      foreach (($storage_clients[$group] ?? []) as $storage_client_id => $storage_client) {
        // Manage plugin restrictions.
        if ($allowed_plugins && empty($allowed_plugins[$storage_client_id])) {
          continue 1;
        }
        $group_name = $group_name_lookup[$group];
        $storage_client_options[$group_name][$storage_client_id] =
          $storage_client->getLabel();
      }
    }
    // Sort each category.
    foreach ($storage_client_options as $group_name => $group) {
      $storage_client_options[$group_name]['#sort_options'] = TRUE;
    }

    $current_storage_client_id =
      $form_state->getValue(['storage_clients', 0, 'id'])
      ?? ($this->getConfiguration()['storage_clients'][0]['id'] ?? '')
      ?: DataAggregatorBase::DEFAULT_STORAGE_CLIENT;

    $form['storage_clients'][0]['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Storage client'),
      '#description' => $this->t(
        'Choose a storage client to use, then configure it below.'
      ),
      '#default_value' => $current_storage_client_id,
      '#options' => $storage_client_options,
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => [
        'class' => ['xntt-field'],
        'autocomplete' => 'off',
      ],
      '#label_attributes' => ['class' => ['xntt-label']],
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => ($form['storage_clients'][0]['#attributes']['id'] ??= uniqid('sc', TRUE)),
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    return $form;
  }

  /**
   * Build a form element for configuring a field property mapping.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The storage client config form added to the given base form.
   */
  public function buildStorageClientConfigForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $config = $this->getConfiguration();
    $storage_client_id =
      $form_state->getValue(['storage_clients', 0, 'id'])
      ?? ($config['storage_clients'][0]['id'] ?? '')
      ?: DataAggregatorBase::DEFAULT_STORAGE_CLIENT;

    // Check if selected client id is compatible with saved config.
    $storage_clients = $this->getAvailableStorageClients();
    $sc_config = [];
    if (($storage_client_id === ($config['storage_clients'][0]['id'] ?? '#'))
      || ($storage_clients['#group'][$storage_client_id] === ($storage_clients['#group'][$config['storage_clients'][0]['id'] ?? ''] ?? ''))
    ) {
      $sc_config += $config['storage_clients'][0]['config'];
    }
    $sc_config += $this->getStorageClientDefaultConfiguration();

    // Make sure we got a default form structure.
    $form['storage_clients'][0]['config'] ??= [
      '#type' => 'container',
      '#attributes' => [
        'id' => ($form['#attributes']['id'] ??= uniqid('da', TRUE))
        . '_sc_0_conf',
      ],
    ];
    // Generate a new storage client instance.
    $storage_client = $this->storageClientManager->createInstance($storage_client_id, $sc_config);
    $storage_client_form_state = XnttSubformState::createForSubform(['storage_clients', 0, 'config'], $form, $form_state);
    $form['storage_clients'][0]['config'] = $storage_client->buildConfigurationForm(
      $form['storage_clients'][0]['config'],
      $storage_client_form_state
    );

    if (!empty($this->locks['lock_data_aggregator']['lock_storage_clients'][0]['lock_config'])) {
      // Not editable.
      $form['storage_clients'][0]['config']['#disabled'] = TRUE;
      $form['storage_clients'][0]['config']['#attributes']['class'][] = 'xntt-disabled';
      $form['storage_clients'][0]['config']['#description'] = $this->t('This storage client configuration cannot be edited.');
    }
    if (
      !empty(
        $this->locks['lock_data_aggregator']['lock_storage_clients'][0]['hide_config']
      )
    ) {
      $form['storage_clients'][0]['#type'] = 'hidden';
    }

    return $form;
  }

}
