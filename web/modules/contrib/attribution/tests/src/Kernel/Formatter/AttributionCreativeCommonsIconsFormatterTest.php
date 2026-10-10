<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionCreativeCommonsIconsFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionCreativeCommonsIconsFormatter.
 *
 * @group attribution
 */
#[CoversClass(AttributionCreativeCommonsIconsFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionCreativeCommonsIconsFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_creative_commons_icons';
  }

  /**
   * Tests the attribution--license-icons CSS class specific to this formatter.
   */
  public function testIconsExtraCssClass(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0']);
    $this->assertContains('attribution--license-icons', $elements[0]['#attributes']['class']);
  }

}
