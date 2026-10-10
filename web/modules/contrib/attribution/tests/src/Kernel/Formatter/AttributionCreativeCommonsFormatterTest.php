<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionCreativeCommonsFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionCreativeCommonsFormatter.
 *
 * @group attribution
 */
#[CoversClass(AttributionCreativeCommonsFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionCreativeCommonsFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_creative_commons';
  }

}
