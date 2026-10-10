<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\Component\ComponentValidator as SdcComponentValidator;
use JsonSchema\Validator;

/**
 * Annotates a definition before core validates it.
 *
 * Core rejects a prop typed only by `$ref: ui-patterns://…`, and a module
 * component without props, to which we add the attributes prop.
 */
class ComponentValidator extends SdcComponentValidator {

  public function __construct(
    protected SdcComponentValidator $decorated,
    protected ComponentDefinitionAnnotator $annotator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setValidator(?Validator $validator = NULL): void {
    $this->decorated->setValidator($validator);
  }

  /**
   * {@inheritdoc}
   */
  public function validateDefinition(array $definition, bool $enforce_schemas): bool {
    return $this->decorated->validateDefinition($this->annotator->annotateDefinition($definition), $enforce_schemas);
  }

  /**
   * {@inheritdoc}
   */
  public function validateProps(array $context, Component $component): bool {
    return $this->decorated->validateProps($context, $component);
  }

}
