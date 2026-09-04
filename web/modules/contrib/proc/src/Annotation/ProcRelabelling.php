<?php

namespace Drupal\proc\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a ProcRelabelling annotation object.
 *
 * @see \Drupal\proc\ProcRelabellingPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class ProcRelabelling extends Plugin {
  /**
   * ProcRelabelling allow the relabeling of a file cipher text just created.
   *
   * By default, the label of the proc entity is the source file's name.
   * However, you might want to change this in order to not reveal the source
   * file's name for users without access to the decryption but with access to
   * a form where the proc entity is referenced. Sometimes user may encrypt
   * sensitive data in the content of a file but expose some sensitive data
   * in the file's name.
   *
   * @ingroup plugin_translatable
   */
  public Translation $description;

}
