<?php

namespace Drupal\proc;

use Drupal\Component\Plugin\PluginBase;

/**
 * Provides a base implementation for a Proc Relabeling plugin.
 *
 * @see \Drupal\proc\Annotation\ProcRelabelling
 * @see ProcRelabellingInterface
 */
abstract class ProcRelabellingBase extends PluginBase implements ProcRelabellingInterface {

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function relabel(array $context): string;

}
