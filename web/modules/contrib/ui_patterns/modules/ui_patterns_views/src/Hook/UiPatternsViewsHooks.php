<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_patterns_views\Plugin\views\display_extender\ComponentDisplayExtender;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for ui_patterns_views.
 */
class UiPatternsViewsHooks {

  /**
   * Implements hook_config_schema_info_alter().
   *
   * The component option of ComponentDisplayExtender, on every display.
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    if (!isset($definitions['views_display']['mapping'])) {
      return;
    }
    $definitions['views_display']['mapping'][ComponentDisplayExtender::OPTION] = [
      'type' => 'ui_patterns_component',
      'label' => 'Component',
    ];
    $definitions['views_display']['mapping']['defaults']['mapping'][ComponentDisplayExtender::OPTION] = [
      'type' => 'boolean',
      'label' => 'Component',
    ];
  }

  /**
   * Implements hook_ajax_render_alter().
   *
   * A Views UI dialog opens at 75% of the window; one holding a component
   * form takes nearly the whole window.
   */
  #[Hook('ajax_render_alter')]
  public function ajaxRenderAlter(array &$data): void {
    foreach ($data as &$command) {
      if ($this->isViewsUiDialogWithComponentForm($command)) {
        $command['dialogOptions']['width'] = '95%';
        $command['dialogOptions']['height'] = '95%';
      }
    }
  }

  /**
   * Whether an AJAX command opens a Views UI dialog holding a component form.
   *
   * @param array $command
   *   The command.
   *
   * @return bool
   *   TRUE for such a dialog.
   */
  protected function isViewsUiDialogWithComponentForm(array $command): bool {
    if (($command['command'] ?? '') !== 'openDialog') {
      return FALSE;
    }
    $classes = (string) ($command['dialogOptions']['classes']['ui-dialog'] ?? '');
    // The component select of every component form.
    return \str_contains($classes, 'views-ui-dialog') && \str_contains((string) ($command['data'] ?? ''), '[component_id]"');
  }

  /**
   * Implements hook_views_post_render().
   *
   * Renders a display through its component, when it has one.
   */
  #[Hook('views_post_render')]
  public function viewsPostRender(ViewExecutable $view, array &$output): void {
    $extender = $view->getDisplay()->getExtenders()[ComponentDisplayExtender::ID] ?? NULL;
    if (!$extender instanceof ComponentDisplayExtender) {
      return;
    }
    $build = $extender->buildRenderable();
    if ($build !== NULL) {
      $output = $build;
    }
  }

}
