<?php

namespace Drupal\proc;

use Drupal\Component\Plugin\PluginBase;

/**
 * Provides a base implementation for a Proc Relabeling plugin.
 *
 * @see \Drupal\proc\Annotation\ProcReEncRecSet
 * @see ProcReEncRecSetInterface
 */
abstract class ProcReEncRecSetBase extends PluginBase implements ProcReEncRecSetInterface {

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function setWishedRecipients(array $proc_ids): array;

}
