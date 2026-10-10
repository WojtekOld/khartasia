<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionPlainFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionPlainFormatter.
 *
 * @group attribution
 */
#[CoversClass(AttributionPlainFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionPlainFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_plain';
  }

}
