<?php

namespace Drupal\external_entities\Plugin;

/**
 * Interface for plugin debug features.
 */
interface PluginDebugInterface {

  /**
   * Returns debug level for current instance.
   *
   * @return int
   *   The debug level. 0 means debugging is off.
   */
  public function getDebugLevel() :int;

  /**
   * Sets debug level for current instance.
   *
   * @param int $debug_level
   *   Sets the debug level. A non-0 value means debugging is enabled while 0
   *   disables  debugging. Default to 1 (enabled).
   */
  public function setDebugLevel(int $debug_level = 1);

}
