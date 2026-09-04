<?php

declare(strict_types=1);

namespace Drupal\security_review\Plugin\SecurityCheck;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\security_review\Attribute\SecurityCheck;
use Drupal\security_review\CheckResult;
use Drupal\security_review\SecurityCheckBase;
use Drupal\views\Entity\View;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks for Views that do not check access.
 */
#[SecurityCheck(
  id: 'views_access',
  title: new TranslatableMarkup('Views Access'),
  description: new TranslatableMarkup('Checks for Views that do not check access.'),
  namespace: new TranslatableMarkup('Security Review'),
  success_message: new TranslatableMarkup('Views are access controlled.'),
  failure_message: new TranslatableMarkup('There are Views that do not provide any access checks.'),
  info_message: new TranslatableMarkup('Module views is not enabled.'),
  help: [
    new TranslatableMarkup('Views can check if the user is allowed access to the content. It is recommended that all Views implement some amount of access control, at a minimum checking for the permission "access content".'),
  ]
)]
class ViewsAccess extends SecurityCheckBase {

  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->extensionPathResolver = $container->get('extension.path.resolver');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function run(bool $cli = FALSE, &$sandbox = []): float {
    // If views is not enabled, return with INFO.
    if (!$this->moduleHandler->moduleExists('views')) {
      $this->createResult(CheckResult::INFO);
      return 1;
    }

    $config = $this->securityReview->getCheckSettings($this->pluginId);
    $ignore_default = $config['ignore_default'] ?? FALSE;
    $hushed_views = $config['hushed_views'] ?? [];

    if (!isset($sandbox['vids'])) {
      try {
        $vids = $this->entityTypeManager->getStorage('view')
          ->getQuery()
          ->accessCheck()
          ->execute();
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        $this->messenger()->addError('Error running views_access check.');
        return 1;
      }
      $sandbox = [];
      $sandbox['vids'] = $vids;
      $sandbox['progress'] = 0;
      $sandbox['max'] = count($vids);
      $sandbox['findings'] = [];
      $sandbox['hushed'] = [];
    }

    // 5 at a time.
    $ids = array_slice($sandbox['vids'], $sandbox['progress'], 5);
    $views = View::loadMultiple($ids);
    $default = NULL;

    foreach ($views as $view) {
      if (in_array($view->id(), $hushed_views)) {
        // The entire view is hushed.
        $sandbox['hushed'][$view->id()] = $view->id();
        $sandbox['progress']++;
        continue;
      }
      elseif ($view->status()) {
        foreach ($view->get('display') as $display_name => $display) {
          $access = $display['display_options']['access'] ?? $default;
          if ($display_name == 'default') {
            $default = $access;
            if ($ignore_default) {
              continue;
            }
          }

          if (in_array($view->id() . ':' . $display_name, $hushed_views)) {
            // Individually hushed.
            $sandbox['hushed'][$view->id()][] = $display_name;
          }
          elseif ((isset($access) && $access['type'] == 'none')) {
            // Access is not controlled for this display.
            $sandbox['findings'][$view->id()][] = $display_name;
          }
        }
      }
      $sandbox['progress']++;
    }

    // Have we finished?
    if ($sandbox['progress'] == $sandbox['max']) {
      $result = CheckResult::SUCCESS;
      if (!empty($sandbox['findings'])) {
        $result = CheckResult::FAIL;
      }
      $this->createResult($result, $sandbox['findings'], NULL, $sandbox['hushed']);

      return 1;
    }

    // Report we are not finished and provide an estimation of the
    // completion level we reached.
    return $sandbox['progress'] / $sandbox['max'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->securityReview->getCheckSettings($this->pluginId);
    $ignore_default = $config['ignore_default'] ?? FALSE;
    $form = [];
    $form['ignore_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore default view'),
      '#description' => $this->t('Check to ignore default views.'),
      '#default_value' => $ignore_default,
    ];

    $hushed_views = $config['hushed_views'] ?? [];
    $form['hushed_views'] = [
      '#type' => 'textarea',
      '#title' => t('Hushed view'),
      '#description' => t('Enter views or displays to ignore i.e. "security_review_test" or "security_review_test:page_1". One entry a line.'),
      '#default_value' => implode("\n", $hushed_views),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array $values): void {
    if (isset($values['ignore_default'])) {
      $values['ignore_default'] = (bool) $values['ignore_default'];
    }
    if (isset($values['hushed_views'])) {
      $values['hushed_views'] = array_filter(explode("\n", str_replace("\r", "\n", trim($values['hushed_views']))));
    }
    $this->securityReview->setCheckSettings($this->pluginId, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getDetails(array $findings, array $hushed = [], bool $returnString = FALSE): array|string {
    $output = $returnString ? '' : [];

    if (empty($findings) && empty($hushed)) {
      return $output;
    }

    $views_ui_enabled = $this->moduleHandler->moduleExists('views_ui');
    $paragraphs = [];
    $paragraphs[] = $this->t('The following View displays do not check access.');
    $items = [];
    foreach ($findings as $view_id => $displays) {
      /** @var \Drupal\views\Entity\View $view */
      $view = View::load($view_id);

      foreach ($displays as $display) {
        $label = $view->label() . ': ' . $display;
        $items[] = $views_ui_enabled ?
          Link::createFromRoute($label, 'entity.view.edit_display_form', ['view' => $view_id, 'display_id' => $display])->toString() : $label;
      }
    }

    $hushed_items = [];
    foreach ($hushed as $view_id => $displays) {
      /** @var \Drupal\views\Entity\View $view */
      $view = View::load($view_id);

      if ($view_id === $displays) {
        $label = $view->label() . ': Whole view';
        $hushed_items[] = $views_ui_enabled ? Link::createFromRoute($label, 'entity.view.edit_display_form', [
          'view' => $view_id,
          'display_id' => 'default',
        ])->toString() : $label;
      }
      else {
        foreach ($displays as $display) {
          $label = $view->label() . ': ' . $display;
          $hushed_items[] = $views_ui_enabled ? Link::createFromRoute($label, 'entity.view.edit_display_form', [
            'view' => $view_id,
            'display_id' => $display,
          ])->toString() : $label;
        }
      }
    }

    if ($returnString) {
      $output .= implode("", $paragraphs) . implode("", $items) . implode("", $hushed_items);
    }
    else {
      $output[] = [
        '#theme' => 'check_evaluation',
        '#additional_paragraphs' => $paragraphs,
        '#finding_items' => $items,
        '#hushed_items' => $hushed_items,
      ];
    }

    return $output;
  }

}
