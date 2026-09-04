<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\PropTypePluginManager;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Drupal\ui_patterns\UiPatternsNormalizerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'block',
  label: new TranslatableMarkup('Block'),
  description: new TranslatableMarkup('A block plugin from an allow list.'),
  prop_types: ['slot']
)]
class BlockSource extends SourcePluginBase implements SourceWithChoicesInterface {

  /**
   * Block to be rendered.
   *
   * @var \Drupal\Core\Block\BlockPluginInterface|null
   */
  protected $block;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PropTypePluginManager $propTypeManager,
    ContextRepositoryInterface $contextRepository,
    RouteMatchInterface $routeMatch,
    SampleEntityGeneratorInterface $sampleEntityGenerator,
    ModuleHandlerInterface $moduleHandler,
    Token $token,
    UiPatternsNormalizerInterface $normalizer,
    protected BlockManagerInterface $blockManager,
    protected PluginFormFactoryInterface $pluginFormFactory,
    protected ContextHandlerInterface $contextHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $propTypeManager, $contextRepository, $routeMatch, $sampleEntityGenerator, $moduleHandler, $token, $normalizer);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(PropTypePluginManager::class),
      $container->get(ContextRepositoryInterface::class),
      $container->get(RouteMatchInterface::class),
      $container->get(SampleEntityGeneratorInterface::class),
      $container->get(ModuleHandlerInterface::class),
      $container->get(Token::class),
      $container->get(UiPatternsNormalizerInterface::class),
      $container->get(BlockManagerInterface::class),
      $container->get(PluginFormFactoryInterface::class),
      $container->get(ContextHandlerInterface::class),
    );
    $instance->currentUser = $container->get(AccountInterface::class);
    return $instance;
  }

  /**
   * Alter the element after the form is built.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The altered element.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  public static function afterBuildBlockForm(array $element, FormStateInterface $form_state): array {
    $element_settings = $form_state->getValue($element['#parents']);
    $plugin_id = $element_settings['plugin_id'];
    if (!$plugin_id) {
      return $element;
    }
    $form = $form_state->getCompleteForm();
    $subform_state = SubformState::createForSubform($element[$plugin_id], $form, $form_state);
    $block = \Drupal::service(BlockManagerInterface::class)->createInstance($plugin_id, []);
    if ($block instanceof BlockPluginInterface) {
      $block->submitConfigurationForm($element, $subform_state);
      // Throwaway save, only to cast the settings through the block config
      // schema. A private dispatcher keeps it away from config.save listeners:
      // test sites validate every saved object against its full schema.
      $config = new Config('block.block.ui_patterns', new NullStorage(), new EventDispatcher(), \Drupal::service(TypedConfigManagerInterface::class));
      $config->setData([
        'plugin' => $plugin_id,
        'settings' => $block->getConfiguration(),
      ])->save();
      $form_state->setValue(\array_merge($element['#parents'], [$plugin_id]), $config->getRawData()['settings']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'plugin_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $this->block = $this->getBlock($this->getSetting('plugin_id') ?? '');
    if (!$this->block) {
      return [];
    }
    // Same gate as a placed block: blockAccess() and the visibility
    // conditions decide. The access cacheability is kept either way.
    $access = $this->block->access($this->currentUser, TRUE);
    $this->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return [];
    }
    $this->addCacheableDependency($this->block);
    $build = $this->block->build();
    if (!\is_array($build)) {
      return [];
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $plugin_id = $form_state->getValue('plugin_id');
    $block = $this->getBlock($plugin_id);
    if ($block) {
      $subform_state = SubformState::createForSubform($form[$plugin_id], $form, $form_state);
      $this->getPluginForm($block)->validateConfigurationForm($form[$plugin_id], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $this->buildBlockCreateForm($form, $form_state);
    return $form;
  }

  /**
   * Build the form to create a block.
   */
  protected function buildBlockCreateForm(array &$form, FormStateInterface $form_state): void {
    $options = $this->getBlockOptions();
    $wrapper_id = Html::getId(\implode('_', $this->formArrayParents ?? []) . '_block-create-form-ajax');
    $plugin_id = $this->getSetting('plugin_id') ?? '';
    $form['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Block'),
      '#options' => $options,
      '#default_value' => $plugin_id,

      '#ajax' => [
        'callback' => [__CLASS__, 'onBlockPluginIdChange'],
        'wrapper' => $wrapper_id,
        'method' => 'replaceWith',
        // 'callback' => [static::class, 'onBlockPluginIdChange'],
      ],
      '#executes_submit_callback' => FALSE,
      '#empty_value' => '',
      '#empty_option' => $this->t('- None -'),
      '#required' => FALSE,
      // Read by ComponentFormBase::resetSwitchedSiblingsInput().
      '#ui_patterns_resets_siblings' => TRUE,
    ];
    $form[$plugin_id] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      '#tree' => TRUE,
    ];
    $block = $this->getBlock($plugin_id);
    if ($block) {
      $form['#tree'] = TRUE;
      // $form['#process'] = [ '::validateForm'];
      $form[$plugin_id] = [];
      $subform_state = SubformState::createForSubform($form[$plugin_id], $form, $form_state);
      $form[$plugin_id] = $this->getPluginForm($block)->buildConfigurationForm($form[$plugin_id], $subform_state);
      $form[$plugin_id]['#tree'] = TRUE;
      $form[$plugin_id]['#prefix'] = '<div id="' . $wrapper_id . '">' . ($form[$plugin_id]['#prefix'] ?? '');
      $form[$plugin_id]['#suffix'] = ($form[$plugin_id]['#suffix'] ?? '') . '</div>';
      $hide_elements = [
        'label',
        'label_display',
        'admin_label',
      ];
      foreach ($hide_elements as $hide_element) {
        if (isset($form[$plugin_id][$hide_element])) {
          $form[$plugin_id][$hide_element]['#access'] = FALSE;
        }
      }
      $form['#after_build'][] = [static::class, 'afterBuildBlockForm'];
    }
  }

  /**
   * Retrieves the plugin form for a given block.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   The plugin form for the block.
   */
  protected function getPluginForm(BlockPluginInterface $block) {
    if ($block instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($block, 'configure');
    }
    return $block;
  }

  /**
   * Gets the plugin for this component.
   *
   * @param string|null $plugin_id
   *   The plugin ID.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The plugin.
   */
  public function getBlock(?string $plugin_id) {
    if (!$plugin_id) {
      return NULL;
    }
    $block_configuration = $this->getSetting($plugin_id) ?? [];
    /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
    $plugin = $this->blockManager->createInstance($plugin_id, $block_configuration);
    if ($plugin instanceof ContextAwarePluginInterface && !$this->applyContexts($plugin)) {
      return NULL;
    }
    // Custom patch for LB FieldBlock blocks.
    return $plugin;
  }

  /**
   * Propagate the contexts known by this source to a block instance.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $plugin
   *   The block plugin.
   *
   * @return bool
   *   FALSE when a required context has no value, so the block cannot be built.
   */
  protected function applyContexts(ContextAwarePluginInterface $plugin): bool {
    $contexts = $this->context;
    $plugin_contexts = $plugin->getContexts();
    $plugin_definition = $plugin->getPluginDefinition();
    $provider = ($plugin_definition instanceof PluginDefinitionInterface) ? $plugin_definition->getProvider() : ($plugin_definition['provider'] ?? '');
    if ($provider === 'layout_builder' && \array_key_exists('view_mode', $plugin_contexts)) {
      $plugin->setContextValue('view_mode', EntityDisplayBase::CUSTOM_MODE);
    }
    foreach ($contexts as $context_name => $context) {
      if (!\array_key_exists($context_name, $plugin_contexts)) {
        $plugin->setContext($context_name, $context);
      }
      else {
        $plugin->setContextValue($context_name, $context->getContextValue());
      }
    }
    try {
      $this->contextHandler->applyContextMapping($plugin, $contexts);
    }
    catch (MissingValueContextException) {
      // The context is known but carries no value: nothing to render, and the
      // result stays cacheable.
      return FALSE;
    }
    catch (ContextException) {
      // The context is gone, so its cacheability is unknown.
      $this->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Ajax callback for fields with AJAX callback to update form substructure.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The replaced form substructure.
   */
  public static function onBlockPluginIdChange(array $form, FormStateInterface $form_state): array {
    $triggeringElement = $form_state->getTriggeringElement();
    // Dynamically return the dependent ajax for elements based on the
    // triggering element. This shouldn't be done statically because
    // settings forms may be different, e.g. for layout builder, core, ...
    if (!empty($triggeringElement['#array_parents'])) {
      $subformKeys = $triggeringElement['#array_parents'];
      // Remove the triggering element itself and add the 'block' below key.
      \array_pop($subformKeys);
      // Return the subform:
      $subform = NestedArray::getValue($form, $subformKeys);
      $plugin_id = $subform['plugin_id']['#value'];
      $form_state->setRebuild();
      return $subform[$plugin_id];
    }
    return [];
  }

  /**
   * Return blocks list.
   *
   * @see BlockLibraryController::listBlocks()
   * @see FilteredPluginManagerTrait::getFilteredDefinitions()
   * @see layout_builder_plugin_filter_block__block_ui_alter()
   * @see layout_builder_plugin_filter_block__layout_builder_alter()
   * @see layout_builder_plugin_filter_block_alter()
   *
   * @return array
   *   Definitions of blocks
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  protected function listBlockDefinitions(): array {
    $context_for_block_discovery = $this->context;
    $definitions = $this->blockManager->getFilteredDefinitions('ui_patterns', $context_for_block_discovery, []);
    // Filter plugins based on the flag 'ui_patterns_compatibility'.
    // @see function ui_patterns_plugin_filter_block__ui_patterns_alter
    // from ui_patterns.module file
    $definitions = \array_filter($definitions, static function ($definition, $plugin_id) {
      return \is_array($definition) && (!isset($definition['_ui_patterns_compatible']) || $definition['_ui_patterns_compatible']);
    }, \ARRAY_FILTER_USE_BOTH);
    // Filter based on contexts.
    $definitions = $this->contextHandler->filterPluginDefinitionsByContexts($context_for_block_discovery, $definitions);
    // Order by category, and then by admin label.
    return $this->blockManager->getSortedDefinitions($definitions);
  }

  /**
   * Get options for block select.
   */
  protected function getBlockOptions(): array {
    $choices = $this->getChoices();
    $options = [];
    foreach ($choices as $choice_id => $choice) {
      $category = $choice['group'] ?? 'Other';
      if (!\array_key_exists($category, $options)) {
        $options[$category] = [];
      }
      $options[$category][$choice_id] = $choice['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getChoices(): array {
    $definitions = $this->listBlockDefinitions();
    $choices = [];
    foreach ($definitions as $plugin_id => $definition) {
      $choice = [
        'label' => $definition['admin_label'] ?? $definition['label'] ?? $definition['id'],
        'original_id' => $plugin_id,
        'provider' => $definition['provider'] ?? NULL,
        'group' => $definition['category'] ?? NULL,
      ];
      if ($choice['label'] instanceof MarkupInterface) {
        $choice['label'] = (string) $choice['label'];
      }
      if ($choice['group'] instanceof MarkupInterface) {
        $choice['group'] = (string) $choice['group'];
      }
      $choices[$plugin_id] = $choice;
    }
    return $choices;
  }

  /**
   * {@inheritdoc}
   */
  public function getChoiceSettings(string $choice_id): array {
    return [
      'plugin_id' => $choice_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getChoice(array $settings): string {
    return $settings['plugin_id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if (!$this->block) {
      $this->block = $this->getBlock($this->getSetting('plugin_id') ?? '');
    }
    if (!$this->block) {
      return $dependencies;
    }
    SourcePluginBase::mergeConfigDependencies($dependencies, $this->getPluginDependencies($this->block));
    return $dependencies;
  }

}
