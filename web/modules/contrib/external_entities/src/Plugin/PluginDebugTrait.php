<?php

namespace Drupal\external_entities\Plugin;

/**
 * Provides default implementations for plugin debug features.
 */
trait PluginDebugTrait {

  /**
   * Debug level of current instance.
   *
   * @var int
   */
  protected $debugLevel;

  /**
   * {@inheritdoc}
   */
  public function getDebugLevel() :int {
    return $this->debugLevel ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugLevel(int $debug_level = 1) {
    $this->debugLevel = $debug_level;
  }

}
