<?php

namespace Drupal\term_glossary\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a term glossary handler annotation object.
 *
 * @Annotation
 */
class TermGlossaryHandler extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the handler type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

}
