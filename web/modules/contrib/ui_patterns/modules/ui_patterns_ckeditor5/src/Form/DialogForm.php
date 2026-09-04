<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\filter\FilterFormatInterface;
use Drupal\ui_patterns_ckeditor5\HostEntity;
use Drupal\ui_patterns_ckeditor5\Plugin\Filter\ComponentEmbed;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dialog form inserting or editing a component in the editor.
 *
 * Returns the component configuration to the JavaScript plugin through
 * EditorDialogSave, as the data-component-id and data-component-settings
 * attributes of the <drupal-component> tag.
 */
final class DialogForm extends FormBase {

  /**
   * ID of the wrapper replaced by the AJAX callbacks.
   */
  protected const AJAX_WRAPPER = 'ui-patterns-ckeditor5-dialog-form-wrapper';

  public function __construct(
    protected ComponentPluginManager $componentPluginManager,
    protected HostEntity $hostEntity,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('plugin.manager.sdc'),
      $container->get('ui_patterns_ckeditor5.host_entity'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ui_patterns_ckeditor5_dialog_form';
  }

  /**
   * Title callback of the dialog route.
   */
  public function title(Request $request): TranslatableMarkup {
    $definition = $this->getEditedDefinition($request->getPayload()->all());
    return $definition
      ? $this->t('Edit component: @label', ['@label' => $definition['name'] ?? $definition['id']])
      : $this->t('Insert component');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormatInterface $filter_format = NULL): array {
    $request = $this->getRequest();
    // The JavaScript plugin posts the host entity and the edited component
    // when it opens the dialog. The AJAX rebuilds of the component form post
    // the form values only, so the form carries them as hidden values.
    $input = $form_state->getUserInput();
    $request_values = \is_array($input['host'] ?? NULL) ? $input['host'] : $request->getPayload()->all();
    $form['host'] = ['#tree' => TRUE];
    foreach (['entity_type', 'entity_id', 'entity_bundle', 'component_config'] as $key) {
      $form['host'][$key] = [
        '#type' => 'hidden',
        '#value' => \is_scalar($request_values[$key] ?? NULL) ? (string) $request_values[$key] : '',
      ];
    }
    $component_config = $this->getComponentConfig($request_values, $form_state);

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . self::AJAX_WRAPPER . '">';
    $form['#suffix'] = '</div>';
    if ($definition = $this->getEditedDefinition($request_values)) {
      $form['edited'] = [
        '#type' => 'item',
        '#title' => $definition['name'] ?? $definition['id'],
        '#description' => $definition['description'] ?? '',
      ];
    }
    // Check the posted ID too: when editing, it comes from a hidden field,
    // not from the selector.
    $filter = $this->getFilter($filter_format);
    $component_id = $component_config['component_id'] ?? NULL;
    if (\is_string($component_id) && !$filter->isComponentAllowed($component_id)) {
      $form['not_allowed'] = [
        '#theme' => 'status_messages',
        '#message_list' => ['warning' => [$this->t('This component is not allowed by the text format.')]],
      ];
      return $form;
    }
    $form['component'] = [
      '#type' => 'component_form',
      '#component_id' => $component_id,
      '#component_filter' => $filter->getAllowedComponentIds() ?: NULL,
      '#source_contexts' => $this->hostEntity->getContextsFromValues($request_values),
      // AJAX rebuilds of the sources must post to the dialog route.
      '#ajax_url' => $request->getRequestUri(),
      '#default_value' => $component_config,
    ];
    $form['actions'] = ['#type' => 'actions'];
    if (isset($component_config['component_id'])) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Embed'),
        '#button_type' => 'primary',
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
          'wrapper' => self::AJAX_WRAPPER,
          'disable-refocus' => TRUE,
        ],
      ];
    }
    return $form;
  }

  /**
   * AJAX callback: gives the component configuration to the editor.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state): AjaxResponse|array {
    $component_config = $form_state->getValue('component');
    if ($form_state->getErrors() || empty($component_config['component_id'])) {
      return $form['component'];
    }
    $response = new AjaxResponse();
    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'data-component-id' => $component_config['component_id'],
        'data-component-settings' => Json::encode($component_config),
      ],
    ]));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // The AJAX callback does the work.
  }

  /**
   * The configuration of the edited component, empty when inserting.
   *
   * Posted by the JavaScript plugin as the component_config parameter, JSON
   * encoded, when the dialog opens; the form state has the values on the
   * AJAX rebuilds.
   */
  protected function getComponentConfig(array $request_values, FormStateInterface $form_state): array {
    $component_config = $form_state->getValue('component');
    if (\is_array($component_config) && $component_config !== []) {
      return $component_config;
    }
    $sent = $request_values['component_config'] ?? NULL;
    $decoded = \is_string($sent) ? Json::decode($sent) : NULL;
    return \is_array($decoded) ? $decoded : [];
  }

  /**
   * The component_embed filter of the text format.
   */
  protected function getFilter(?FilterFormatInterface $filter_format): ComponentEmbed {
    $filter = $filter_format?->filters('component_embed');
    if (!$filter instanceof ComponentEmbed) {
      throw new NotFoundHttpException();
    }
    return $filter;
  }

  /**
   * The definition of the edited component, NULL when inserting.
   */
  protected function getEditedDefinition(array $request_values): ?array {
    $sent = $request_values['component_config'] ?? NULL;
    $decoded = \is_string($sent) ? Json::decode($sent) : NULL;
    $component_id = \is_array($decoded) ? ($decoded['component_id'] ?? NULL) : NULL;
    if (!\is_string($component_id) || !$this->componentPluginManager->hasDefinition($component_id)) {
      return NULL;
    }
    return $this->componentPluginManager->getDefinition($component_id);
  }

}
