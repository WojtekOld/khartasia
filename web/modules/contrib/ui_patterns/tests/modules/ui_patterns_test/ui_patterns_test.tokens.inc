<?php

/**
 * @file
 * Builds placeholder replacement tokens for entity-related data.
 */

declare(strict_types=1);

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ui_patterns_test\Hook\UiPatternsTestTokensHooks;

/**
 * Implements hook_token_info().
 */
#[LegacyHook]
function ui_patterns_test_token_info(): array {
  return Drupal::service(UiPatternsTestTokensHooks::class)->tokenInfo();
}

/**
 * Implements hook_tokens().
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
#[LegacyHook]
function ui_patterns_test_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  return Drupal::service(UiPatternsTestTokensHooks::class)->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
