<?php

declare(strict_types=1);

namespace Drupal\ui_icons_menu\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Url;

/**
 * Hook implementations for ui_icons_menu.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
class UiIconsMenuHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ('help.page.ui_icons_menu' === $route_name) {
      $output = '';
      $output .= '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t('The UI Icons Menu module overtakes the core default widget for menu link content entities, allowing you to set icons on menu links.') . '</p>';
      return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   *
   * @todo set settings options, position and required as menu setting or global
   * for all menus.
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() !== 'menu_link_content') {
      return;
    }

    $type = 'icon_link_widget';
    if ($this->moduleHandler->moduleExists('ui_icons_field_link_attributes')) {
      $type = 'icon_link_attributes_widget';
    }

    $fields['link']->setDisplayOptions('form', [
      'type' => $type,
      // Allow icon position and settings.
      'settings' => [
        'icon_required' => FALSE,
        'icon_position' => TRUE,
        'show_settings' => TRUE,
      ],
    ]);
  }

  /**
   * Implements hook_preprocess_menu().
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    // Ignore preprocess if there are no items or the menu is a navigation one.
    if (empty($variables['items']) ||
      str_starts_with($variables['theme_hook_original'] ?? '', 'navigation_menu__')
    ) {
      return;
    }
    $this->processMenuItems($variables['items']);
  }

  /**
   * Implements hook_link_alter().
   *
   * Allow the icon to be displayed on the menu administration context in
   * /admin/structure/menu/manage.
   */
  #[Hook('link_alter')]
  public function linkAlter(array &$variables): void {
    if (!isset($variables['url']) || !isset($variables['text'])) {
      return;
    }

    if (!$icon = $variables['url']->getOption('icon')) {
      return;
    }

    // Check if the link is from menu.
    if (isset($variables['options']['ui_icons_processed'])) {
      return;
    }

    // Do not handle link handled by field link in IconLinkFormatter.
    if ($variables['url']->getOption('ui_icons_processed')) {
      return;
    }

    // Do not handle link if no position found, possible if we have an icon but
    // the display is not set.
    if (!$icon_display = $variables['url']->getOption('icon_display') ?? NULL) {
      return;
    }

    $variables['url']->setOption('ui_icons_processed', TRUE);
    $this->generateMarkup($variables['text'], $icon['target_id'], $icon['settings'] ?? [], $icon_display);
  }

  /**
   * Implements hook_navigation_menu_link_tree_alter().
   */
  #[Hook('navigation_menu_link_tree_alter')]
  public function navigationMenuLinkTreeAlter(array &$tree): void {
    foreach ($tree as $item) {
      $definition = $item->link->getPluginDefinition()['options']['icon'] ?? NULL;
      if (!isset($definition['target_id'])) {
        continue;
      }

      [$icon_pack, $icon] = explode(':', $definition['target_id']);
      $definition['settings'] = NestedArray::mergeDeep($definition['settings'][$icon_pack] ?? [], ['class' => 'toolbar-button__icon']);

      $item->options['icon'] = $definition + ['pack_id' => $icon_pack, 'icon_id' => $icon];
    }
  }

  /**
   * Handle menu items to add our icon.
   *
   * @param array $items
   *   The menu items.
   */
  protected function processMenuItems(array &$items): void {
    foreach ($items as &$item) {
      if (empty($item['url'])) {
        continue;
      }

      $this->processMenuItem($item);

      if (!empty($item['below'])) {
        $this->processMenuItems($item['below']);
      }
    }
  }

  /**
   * Handle a single menu item.
   *
   * @param array $item
   *   The menu item.
   */
  protected function processMenuItem(array &$item): void {
    // Being extra defensive on the menu as other themes/modules can alter in
    // unknown ways.
    if (!isset($item['url'])) {
      return;
    }

    if (!$item['url'] instanceof Url) {
      return;
    }

    /** @var \Drupal\Core\Url $url */
    $url = &$item['url'];

    if (!$icon = $url->getOption('icon')) {
      return;
    }

    if (empty($icon['target_id'])) {
      return;
    }
    if ($url->getOption('ui_icons_processed')) {
      return;
    }

    $url->setOption('ui_icons_processed', TRUE);
    $this->generateMarkup($item['title'], $icon['target_id'] ?? '', $icon['settings'] ?? [], $url->getOption('icon_display') ?? 'before');
  }

  /**
   * Helper to generate the expected markup for the link with icon.
   *
   * @param mixed $text
   *   The text reference to generate markup for.
   * @param string $icon_full_id
   *   The icon full id.
   * @param array $icon_settings
   *   The icon settings.
   * @param string $icon_display
   *   The icon position, `icon_only`, `after` or default `before`.
   */
  protected function generateMarkup(mixed &$text, string $icon_full_id, array $icon_settings, string $icon_display = 'before'): void {
    $icon_renderable = IconDefinition::getRenderable($icon_full_id, $icon_settings);
    $icon = $this->renderer->renderInIsolation($icon_renderable);

    switch ($icon_display) {
      case 'before':
        $text = new FormattableMarkup('@icon <span class="ui-icons-menu-text">@title</span>', [
          '@title' => $text,
          '@icon' => $icon,
        ]);
        break;

      case 'after':
        $text = new FormattableMarkup('<span class="ui-icons-menu-text">@title</span> @icon', [
          '@title' => $text,
          '@icon' => $icon,
        ]);
        break;

      default:
        $text = new FormattableMarkup('@icon', [
          '@icon' => $icon,
        ]);
        break;
    }
  }

}
