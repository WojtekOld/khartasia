<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for ui_patterns_test.
 */
class UiPatternsTestTokensHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $type = [
      'name' => $this->t('Node Tests'),
      'description' => $this->t('Tokens related to individual content items, or "nodes".'),
      'needs-data' => 'entity_test',
    ];
    // Core tokens for nodes.
    $entity['id'] = [
      'name' => $this->t('Entity ID'),
      'description' => $this->t('The unique ID of the content item, or "entity_test".'),
    ];
    return [
      'types' => [
        'node' => $type,
      ],
      'tokens' => [
        'node' => $entity,
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    if ($type === 'node' && !empty($data['node'])) {
      $entity = $data['node'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          // Simple key values on the node.
          case 'id':
            $replacements[$original] = $entity->id();
            break;

          case 'name':
            $replacements[$original] = $entity->name();
            break;
        }
      }
    }
    return $replacements;
  }

  /**
   * Implements hook_component_info_alter().
   */
  #[Hook('component_info_alter')]
  public function componentInfoAlter(array &$definitions): void {
    if (isset($definitions['ui_patterns_test:test-component'])) {
      $definitions['ui_patterns_test:test-component']['variants']['hook'] = ['title' => 'Hook altered'];
    }
  }

}
