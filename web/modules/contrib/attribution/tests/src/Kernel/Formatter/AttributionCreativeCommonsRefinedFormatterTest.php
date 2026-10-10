<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionCreativeCommonsRefinedFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionCreativeCommonsRefinedFormatter.
 *
 * @group attribution
 */
#[CoversClass(AttributionCreativeCommonsRefinedFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionCreativeCommonsRefinedFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_creative_commons_refined';
  }

}
