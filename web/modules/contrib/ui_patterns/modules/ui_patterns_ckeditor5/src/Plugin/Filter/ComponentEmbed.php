<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5\Plugin\Filter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ExtensionType;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns_ckeditor5\HostEntity;
use Drupal\ui_patterns_ckeditor5\RenderingFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the components embedded by the CKEditor 5 plugin.
 *
 * Each <drupal-component> tag is replaced by the rendered component. The
 * data-component-settings attribute carries the component configuration as
 * saved by the dialog form, JSON encoded.
 */
#[Filter(
  id: 'component_embed',
  title: new TranslatableMarkup('Embed components'),
  description: new TranslatableMarkup('Renders the components embedded with the <code>&lt;drupal-component&gt;</code> tag.'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 100,
  settings: [
    'allowed_components' => [],
  ],
)]
class ComponentEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $componentPluginManager,
    protected RendererInterface $renderer,
    protected LoggerChannelInterface $logger,
    protected HostEntity $hostEntity,
    protected ModuleExtensionList $moduleList,
    protected ThemeExtensionList $themeList,
    protected RenderingFilter $renderingFilter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.sdc'),
      $container->get('renderer'),
      $container->get('logger.channel.ui_patterns'),
      $container->get('ui_patterns_ckeditor5.host_entity'),
      $container->get('extension.list.module'),
      $container->get('extension.list.theme'),
      $container->get('ui_patterns_ckeditor5.rendering_filter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);
    if (\stripos($text, '<drupal-component') === FALSE) {
      return $result;
    }
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query('//drupal-component[normalize-space(@data-component-id)!=""]');
    if ($nodes === FALSE || $nodes->count() === 0) {
      return $result;
    }
    foreach ($nodes as $node) {
      if ($node instanceof \DOMElement) {
        $this->processNode($node, $result);
      }
    }
    $result->setProcessedText(Html::serialize($dom));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $allowed = $this->getAllowedComponentIds();
    $form['allowed_components'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed components'),
      '#description' => $this->t('If none are selected, all components are allowed.'),
      '#tree' => TRUE,
      '#element_validate' => [[static::class, 'validateAllowedComponents']],
    ];
    foreach ($this->getComponentOptionsByProvider() as $provider => $group) {
      $form['allowed_components'][$provider] = [
        '#type' => 'details',
        '#title' => $group['label'],
        '#open' => (bool) \array_intersect(\array_keys($group['options']), $allowed),
        'components' => [
          '#type' => 'checkboxes',
          '#options' => $group['options'],
          '#default_value' => $allowed,
        ],
      ];
    }
    return $form;
  }

  /**
   * Stores the checked component IDs as a flat list.
   */
  public static function validateAllowedComponents(array $element, FormStateInterface $form_state): void {
    $ids = [];
    $values = (array) $form_state->getValue($element['#parents']);
    \array_walk_recursive($values, static function ($value) use (&$ids): void {
      if (\is_string($value) && $value !== '') {
        $ids[] = $value;
      }
    });
    $form_state->setValueForElement($element, $ids);
  }

  /**
   * The allowed component IDs, all when empty.
   *
   * @return string[]
   *   The component IDs.
   */
  public function getAllowedComponentIds(): array {
    return \array_values(\array_filter($this->settings['allowed_components'] ?? [], '\is_string'));
  }

  /**
   * Whether the text format allows a component.
   *
   * A replacing component counts as the one it replaces: the editor stores
   * that ID.
   */
  public function isComponentAllowed(string $component_id): bool {
    $allowed = $this->getAllowedComponentIds();
    if ($allowed === []) {
      return TRUE;
    }
    $definition = $this->componentPluginManager->getDefinition($component_id, FALSE) ?? [];
    return \in_array($definition['replaces'] ?? $component_id, $allowed, TRUE);
  }

  /**
   * The selectable components by extension, sorted by extension label.
   *
   * @return array<string, array{label: string, options: array<string, string>}>
   *   The extension label and the component labels keyed by component ID,
   *   keyed by extension name.
   */
  protected function getComponentOptionsByProvider(): array {
    $groups = [];
    foreach ($this->componentPluginManager->getNegotiatedSortedDefinitions() as $component_id => $definition) {
      $provider = $definition['provider'];
      $groups[$provider]['label'] ??= $this->extensionLabel($definition);
      $groups[$provider]['options'][$component_id] = (string) $definition['label'];
    }
    \uasort($groups, static fn (array $a, array $b): int => \strnatcasecmp($a['label'], $b['label']));
    return $groups;
  }

  /**
   * The label of the extension providing a component.
   */
  protected function extensionLabel(array $definition): string {
    $list = ($definition['extension_type'] ?? NULL) === ExtensionType::Theme ? $this->themeList : $this->moduleList;
    return (string) $list->getName($definition['provider']);
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $long
   *   Whether to return the long tip.
   */
  public function tips($long = FALSE) {
    if ($long) {
      return (string) $this->t('You can embed components with the <code>&lt;drupal-component&gt;</code> tag. The <code>data-component-id</code> attribute is the component ID, for example <code>my_theme:card</code>. The optional <code>data-component-settings</code> attribute is the JSON encoded component configuration, as saved by the editor.');
    }
    return (string) $this->t('You can embed components (using the <code>&lt;drupal-component&gt;</code> tag).');
  }

  /**
   * Replaces one <drupal-component> node by the rendered component.
   */
  protected function processNode(\DOMElement $node, FilterProcessResult &$result): void {
    $component_id = $node->getAttribute('data-component-id');
    if (!$this->componentPluginManager->hasDefinition($component_id)) {
      $this->logger->error('Embedded component "@component_id" does not exist.', ['@component_id' => $component_id]);
      static::replaceNodeContent($node, '');
      return;
    }
    if (!$this->isComponentAllowed($component_id)) {
      $this->logger->warning('Embedded component "@component_id" is not allowed by the text format.', ['@component_id' => $component_id]);
      static::replaceNodeContent($node, '');
      return;
    }
    $settings = Json::decode($node->getAttribute('data-component-settings') ?: '[]');
    $build = [
      '#type' => 'component',
      '#component' => $component_id,
      '#ui_patterns' => \is_array($settings) ? $settings : [],
      '#source_contexts' => $this->hostEntity->getContexts(),
      '#prefix' => '<div class="drupal-component">',
      '#suffix' => '</div>',
    ];
    // Placeholders must survive until the final render and the metadata must
    // reach $result instead of bubbling to the caller, hence render() inside
    // an own render context, as core's media_embed filter does.
    $this->renderingFilter->push($this);
    try {
      $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
        return $this->renderer->render($build);
      });
    }
    catch (\Throwable $e) {
      // The settings are editable text: a malformed configuration must not
      // break the page.
      $this->logger->error('Embedded component "@component_id" can not be rendered: @message', [
        '@component_id' => $component_id,
        '@message' => $e->getMessage(),
      ]);
      static::replaceNodeContent($node, '');
      return;
    }
    finally {
      $this->renderingFilter->pop();
    }
    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, (string) $markup);
  }

  /**
   * Replaces a DOM node by the nodes of the given HTML.
   */
  protected static function replaceNodeContent(\DOMNode $node, string $content): void {
    $document = $node->ownerDocument;
    $parent = $node->parentNode;
    if ($document === NULL || $parent === NULL) {
      return;
    }
    if ($content !== '') {
      $body = Html::load($content)->getElementsByTagName('body')->item(0);
      $replacement_nodes = $body ? $body->childNodes : [];
    }
    else {
      $replacement_nodes = [$document->createTextNode('')];
    }
    foreach ($replacement_nodes as $replacement_node) {
      $parent->insertBefore($document->importNode($replacement_node, TRUE), $node);
    }
    $parent->removeChild($node);
  }

}
