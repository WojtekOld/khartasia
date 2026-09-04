<?php

namespace Drupal\security_review\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Security Check attribute object.
 *
 * Plugin Namespace: Plugin\security_review\securityCheck.
 *
 * @see \Drupal\security_review\SecurityCheckBase
 * @see \Drupal\security_review\SecurityCheckPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SecurityCheck extends Plugin {

  /**
   * Constructs a SecurityCheck attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title of the check.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the check.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $namespace
   *   The check namespace.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $success_message
   *   The success message of the check.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $failure_message
   *   The failure message of the check.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $info_message
   *   (Optional) The info message of the check.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $warning_message
   *   (Optional) The warning message of the check.
   * @param array|null $help
   *   (Optional) help text for the check.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly TranslatableMarkup $description,
    public readonly TranslatableMarkup $namespace,
    public readonly TranslatableMarkup $success_message,
    public readonly ?TranslatableMarkup $failure_message = NULL,
    public readonly ?TranslatableMarkup $info_message = NULL,
    public readonly ?TranslatableMarkup $warning_message = NULL,
    public readonly ?array $help = NULL,
  ) {}

}
