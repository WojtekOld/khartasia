<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\IconPreview;
use Drupal\ui_icons\IconSearch;
use Drupal\ui_icons_picker\Ajax\UpdateIconSelectionCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an icon picker selector form.
 */
final class IconSelectForm extends FormBase {

  private const AJAX_WRAPPER_ID = 'icon-results-wrapper';
  private const MESSAGE_WRAPPER_ID = 'icon-message-wrapper';
  private const NUM_PER_PAGE = 247;
  private const PREVIEW_ICON_SIZE = 32;

  /**
   * The icon search service.
   *
   * @var \Drupal\ui_icons\IconSearch
   */
  protected IconSearch $iconSearch;

  /**
   * Plugin manager for icons pack discovery and definitions.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected IconPackManagerInterface $pluginManagerIconPack;

  public function __construct(
    IconPackManagerInterface $pluginManagerIconPack,
    IconSearch $iconSearch,
  ) {
    $this->pluginManagerIconPack = $pluginManagerIconPack;
    $this->iconSearch = $iconSearch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.icon_pack'),
      $container->get('ui_icons.search'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_icons_picker_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $options = NULL): array {
    if (!$dialog_options = $this->resolveDialogOptions()) {
      return [];
    }
    ['wrapper_id' => $wrapper_id, 'allowed_icon_pack' => $allowed_icon_pack] = $dialog_options;

    if (!$modal_state = static::getModalState($form_state)) {
      $icon_list = $this->pluginManagerIconPack->getIcons($allowed_icon_pack);
      $modal_state = [
        'page' => 0,
        'icon_list' => $icon_list,
        'total_available' => count($icon_list),
      ];
      static::setModalState($form_state, $modal_state);
    }

    $input = $form_state->getUserInput();
    $query = $input['filter'] ?? '';
    $icon_list = $modal_state['icon_list'] ?? [];
    $total_available = $modal_state['total_available'] ?? 0;

    if (empty($query)) {
      $icons = array_keys($icon_list);
      $pager = $this->createPager($modal_state['page'], $total_available);
    }
    else {
      $icons = $this->iconSearch->search($query, $allowed_icon_pack, $total_available);
      $pager = $this->createPager($modal_state['page'], count($icons));
    }

    $icons = array_slice($icons, $pager['offset'], self::NUM_PER_PAGE);

    $form['#prefix'] = '<div id="' . self::AJAX_WRAPPER_ID . '"><div id="' . self::MESSAGE_WRAPPER_ID . '"></div>';
    $form['#suffix'] = '</div>';

    $form['wrapper_id'] = [
      '#type' => 'hidden',
      '#value' => $wrapper_id,
    ];

    $ajax_settings = [
      'callback' => [$this, 'searchAjax'],
      'wrapper' => self::AJAX_WRAPPER_ID,
      'effect' => 'fade',
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    $form['filters'] = $this->buildFilters($input['filter'] ?? '', $ajax_settings);

    if (empty($icons)) {
      $form['list'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No icon found, please adjust your filters and try again.'),
      ];
      return $form;
    }

    $form['list'] = $this->buildIconList($icons);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
      '#submit' => [],
      '#ajax' => [
        'callback' => [$this, 'selectIconAjax'],
        'event' => 'click',
        'disable-refocus' => TRUE,
      ],
      '#attributes' => [
        'class' => [
          'icon-ajax-select-submit',
          'hidden',
        ],
      ],
    ];

    if ($pager['total_page'] <= 1) {
      return $form;
    }

    $form['pagination'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pagination'],
      ],
    ];

    $form['pagination']['page_previous'] = $pager['page_previous'];
    $form['pagination']['page_next'] = $pager['page_next'];
    $form['pagination']['page_info'] = $pager['page_info'];

    return $form;
  }

  /**
   * Reads the modal dialog options the picker was opened with.
   *
   * @return array{wrapper_id: string, allowed_icon_pack: array}|null
   *   The options, or NULL when they are missing, in which case a redirect to
   *   the front page has already been sent.
   */
  private function resolveDialogOptions(): ?array {
    $request = $this->getRequest();
    $wrapper_id = NULL;

    if ($request->query->has('dialogOptions')) {
      $options = $request->query->all('dialogOptions');
      $wrapper_id = $options['query']['wrapper_id'] ?? NULL;
    }

    if (NULL === $wrapper_id) {
      $this->redirect('<front>')->send();
      return NULL;
    }

    $allowed_icon_pack = $options['query']['allowed_icon_pack'] ?? '';

    return [
      'wrapper_id' => $wrapper_id,
      'allowed_icon_pack' => empty($allowed_icon_pack) ? [] : explode('+', $allowed_icon_pack),
    ];
  }

  /**
   * Builds the search filter elements.
   *
   * @param string $default_value
   *   Current filter query.
   * @param array $ajax_settings
   *   Ajax settings shared with the pager.
   *
   * @return array
   *   The filters container render array.
   */
  private function buildFilters(string $default_value, array $ajax_settings): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
      'filter' => [
        '#type' => 'search',
        '#title' => $this->t('Filter'),
        '#placeholder' => $this->t('Filter by name'),
        '#title_display' => 'invisible',
        '#default_value' => $default_value,
        '#attributes' => [
          'class' => ['icon-filter-input'],
        ],
      ],
      'search' => [
        '#type' => 'submit',
        '#submit' => [[$this, 'searchSubmit']],
        '#ajax' => $ajax_settings,
        '#value' => $this->t('Search'),
        '#attributes' => [
          'class' => ['icon-ajax-search-submit', 'hidden'],
        ],
      ],
    ];
  }

  /**
   * Builds the radio grid of icons.
   *
   * Radios carry no preview so the modal opens as fast as possible, js
   * library.js fills them in lazily.
   *
   * @param array $icons
   *   Icon full ids, or `value`/`label` pairs when they come from a search.
   *
   * @return array
   *   The list container render array.
   */
  private function buildIconList(array $icons): array {
    // Add the generic mass preview library.
    // Set a specific key to have the list of icons to load for preview.
    $list = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-picker-modal__content'],
      ],
      '#attached' => [
        'library' => [
          'ui_icons_picker/library',
          'ui_icons/ui_icons.preview',
        ],
        'drupalSettings' => [
          'ui_icons_preview_data' => [
            'icon_full_ids' => $icons,
            'settings' => ['size' => self::PREVIEW_ICON_SIZE],
            'target_input_label' => TRUE,
          ],
        ],
      ],
    ];

    foreach ($this->pluginManagerIconPack->getDefinitions() as $pack_definition) {
      if (isset($pack_definition['library'])) {
        $list['#attached']['library'][] = $pack_definition['library'];
      }
    }

    // Empty icon to allow deletion of selection.
    $options = [
      '_none_' => '<img src="/core/themes/claro/images/icons/e34f4f/crossout.svg" title="Select none" width="32" height="32">',
    ];
    foreach ($icons as $icon_data) {
      if (is_array($icon_data)) {
        $options[$icon_data['value']] = $icon_data['label'];
        continue;
      }
      $options[$icon_data] = '<img src="' . IconPreview::SPINNER_ICON . '" title="' . $icon_data . '" width="32" height="32">';
    }

    $list['icon_full_id'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#attributes' => [
        'class' => ['icon-preview-load'],
      ],
    ];

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    if (!isset($triggering_element['#parents'])) {
      return;
    }

    $clicked_button = end($triggering_element['#parents']);
    if ('submit' !== $clicked_button) {
      return;
    }

    $icon_full_id = $form_state->getValue('icon_full_id');
    if (!$icon_full_id) {
      $form_state->setError($form['list'], $this->t('Pick an icon to insert.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Submission handler for the "Previous page" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function previousPageSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page']--;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Next page" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function nextPageSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page']++;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Search" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function searchSubmit(array $form, FormStateInterface $form_state): void {
    $modal_state = self::getModalState($form_state);
    $modal_state['page'] = 0;
    self::setModalState($form_state, $modal_state);

    $form_state->setRebuild();
  }

  /**
   * When searching, simply return a ajax response.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to replace the form.
   */
  public function searchAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    return $this->ajaxRenderFormAndMessages($form);
  }

  /**
   * Renders form and status messages and returns an ajax response.
   *
   * Used for both submission buttons.
   *
   * @param array $form
   *   The form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to replace the form.
   */
  protected function ajaxRenderFormAndMessages(array &$form): AjaxResponse {
    $response = new AjaxResponse();
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $renderer = \Drupal::service('renderer');

    $status_messages = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $output = (string) $renderer->renderRoot($form);
    $messages = (string) $renderer->renderRoot($status_messages);

    $message_wrapper_id = '#' . self::MESSAGE_WRAPPER_ID;

    $response->setAttachments($form['#attached']);
    $response->addCommand(new ReplaceCommand('', $output));
    $response->addCommand(new HtmlCommand($message_wrapper_id, ''));
    $response->addCommand(new AppendCommand($message_wrapper_id, $messages));

    return $response;
  }

  /**
   * Handles the AJAX request to select an icon.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function selectIconAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $errors = $form_state->getErrors();
    if ($errors) {
      return self::ajaxRenderFormAndMessages($form);
    }
    $response = new AjaxResponse();

    $icon_full_id = $form_state->getValue('icon_full_id');
    $wrapper_id = $form_state->getValue('wrapper_id');

    // Allow remove the value to delete.
    if ('_none_' === $icon_full_id) {
      $icon_full_id = '';
    }

    $response->addCommand(new UpdateIconSelectionCommand($icon_full_id, $wrapper_id));
    $response->addCommand(new CloseModalDialogCommand(TRUE));

    return $response;
  }

  /**
   * Retrieves the modal state from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state from which to retrieve the modal state.
   *
   * @return mixed
   *   The modal state value.
   */
  public static function getModalState(FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), ['list_state']);
  }

  /**
   * Sets the modal state in the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state in which to set the modal state.
   * @param array $field_state
   *   The modal state value to set.
   */
  public static function setModalState(FormStateInterface $form_state, array $field_state): void {
    NestedArray::setValue($form_state->getStorage(), ['list_state'], $field_state);
  }

  /**
   * Create the pager list.
   *
   * @param int $current_page
   *   The current page.
   * @param int $total
   *   The icons total.
   *
   * @return array
   *   The pager information and form elements.
   */
  private function createPager(int $current_page, int $total): array {
    $ajax_pager = [
      'callback' => [$this, 'searchAjax'],
      'wrapper' => self::AJAX_WRAPPER_ID,
    ];

    $total_page = (int) round($total / self::NUM_PER_PAGE) + 1;
    $arg = ['@current_page' => $current_page + 1, '@total_page' => $total_page];

    return [
      'current_page' => $current_page + 1,
      'total_page' => $total_page,
      'offset' => self::NUM_PER_PAGE * $current_page,
      'total' => $total,
      // Form elements.
      'page_previous' => [
        '#type' => 'submit',
        '#value' => $this->t('Previous page'),
        '#submit' => [[$this, 'previousPageSubmit']],
        '#ajax' => $ajax_pager,
        '#disabled' => !($current_page > 0),
      ],
      'page_next' => [
        '#type' => 'submit',
        '#value' => $this->t('Next page'),
        '#submit' => [[$this, 'nextPageSubmit']],
        '#ajax' => $ajax_pager,
        '#disabled' => !($total_page > $current_page + 1),
      ],
      'page_info' => [
        '#markup' => $this->t('Page @current_page/@total_page', $arg),
      ],
    ];
  }

}
