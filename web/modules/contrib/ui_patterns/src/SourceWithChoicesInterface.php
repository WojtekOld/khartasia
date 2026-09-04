<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

/**
 * Interface for source plugins that have choices.
 */
interface SourceWithChoicesInterface {

  /**
   * Gets the choices for that source, given the context.
   *
   * @return array
   *   Keys are choice IDs, values are arrays with label, original_id,
   *   group and provider.
   */
  public function getChoices(): array;

  /**
   * Get the source settings for a choice.
   *
   * Carries the choice ID, plus any selection which has no plugin level
   * default: the field formatter picked by the entity field source, typically.
   * Plugin defaults are left out, they are merged at instantiation.
   * Callers may iterate over every choice, so this must stay cheap.
   *
   * @param string $choice_id
   *   The choice ID.
   *
   * @return array
   *   The minimal source settings, storable and renderable as they are.
   */
  public function getChoiceSettings(string $choice_id): array;

  /**
   * Get the choice from settings.
   *
   * @param array $settings
   *   The settings to get the choice from.
   *
   * @return string
   *   The choice id.
   */
  public function getChoice(array $settings): string;

}
