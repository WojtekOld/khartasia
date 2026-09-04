<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'menu',
  label: new TranslatableMarkup('Menu'),
  description: new TranslatableMarkup('Provides a generic Menu source..'),
  prop_types: ['links']
)]
class MenuSource extends SourcePluginBase {

  use MenuTreeSourceTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $plugin = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $plugin->menuLinkTree = $container->get(MenuLinkTreeInterface::class);
    $plugin->menuActiveTrail = $container->get(MenuActiveTrailInterface::class);
    $plugin->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'menu' => NULL,
      'level' => 1,
      'depth' => 0,
      'set_active_trail' => FALSE,
      // TRUE: the missing value in existing configurations means expanded.
      'expand_all_items' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $menu_id = $this->getSetting('menu');
    if (!$menu_id) {
      return [];
    }
    return $this->buildMenuTree(
      $menu_id,
      (int) $this->getSetting('level'),
      (int) $this->getSetting('depth'),
      (bool) $this->getSetting('set_active_trail'),
      (bool) $this->getSetting('expand_all_items')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#options' => ['' => '(None)'] + $this->getMenuList(),
      '#default_value' => $this->getSetting('menu'),
    ];
    $options = \range(0, $this->menuLinkTree()->maxDepth());
    unset($options[0]);
    $form['level'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial visibility level'),
      '#default_value' => $this->getSetting('level'),
      '#options' => $options,
    ];
    $options[0] = $this->t('Unlimited');
    $form['depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of levels to display'),
      '#default_value' => $this->getSetting('depth'),
      '#options' => $options,
      '#description' => $this->t(
        'This maximum number includes the initial level and the final display is dependant of the component template.'
      ),
    ];
    $form['expand_all_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expand all menu links'),
      '#default_value' => (bool) $this->getSetting('expand_all_items'),
      '#description' => $this->t('Override the option found on each menu link used for expanding children and instead display the whole menu tree as expanded.'),
    ];
    $form['set_active_trail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set the active trail'),
      '#default_value' => (bool) $this->getSetting('set_active_trail'),
      '#description' => $this->t('Set the in_active_trail property on the menu links of the current page and its ancestors. This feature has a performance impact and should only be enabled when the menu appearance should differ based on the current page. Always enabled when the menu links are not all expanded.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterComponent(array $element): array {
    $menu_id = $this->getSetting('menu');
    if (!$menu_id) {
      return $element;
    }
    $element = $this->addMenuCacheTags($element, [$menu_id]);
    if ($this->getSetting('set_active_trail') || !$this->getSetting('expand_all_items')) {
      $element = $this->addMenuActiveTrailCacheContexts($element, [$menu_id]);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $menu_id = $this->getSetting('menu');
    if (!$menu_id) {
      return $dependencies;
    }
    $this->menuConfigDependencies($dependencies, [$menu_id]);
    return $dependencies;
  }

}
