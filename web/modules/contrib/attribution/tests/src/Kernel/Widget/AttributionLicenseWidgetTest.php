<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Widget;

use Drupal\attribution\Plugin\Field\FieldWidget\AttributionLicenseWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionLicenseWidget.
 *
 * @group attribution
 */
#[CoversClass(AttributionLicenseWidget::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionLicenseWidgetTest extends AttributionWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getWidgetPluginId(): string {
    return 'attribution_license';
  }

  /**
   * {@inheritdoc}
   */
  protected function assertWidgetSpecificElementsPresent(): void {
    $element = $this->buildFormElement($this->getEmptyItems(), 0);

    $this->assertSame('select', $element['license']['#type']);
    $this->assertArrayHasKey('cc_by_4_0', $element['license']['#options']);

    $this->assertArrayNotHasKey('source', $element);
    $this->assertArrayNotHasKey('author', $element);
  }

}
