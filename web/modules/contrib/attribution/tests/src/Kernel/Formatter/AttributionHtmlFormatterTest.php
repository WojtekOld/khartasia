<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionHtmlFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionHtmlFormatter.
 *
 * @group attribution
 */
#[CoversClass(AttributionHtmlFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionHtmlFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_html';
  }

}
