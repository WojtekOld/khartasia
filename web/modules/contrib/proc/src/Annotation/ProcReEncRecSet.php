<?php

namespace Drupal\proc\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a ProcReEncRecSet annotation object.
 *
 * @see \Drupal\proc\ProcReEncRecSetPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class ProcReEncRecSet extends Plugin {
  /**
   * ProcReEncRecSet defines the wished set of recipients.
   *
   * @ingroup plugin_translatable
   */
  public Translation $description;

}
