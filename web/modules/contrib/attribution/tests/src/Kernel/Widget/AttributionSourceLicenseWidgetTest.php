<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Widget;

use Drupal\attribution\Plugin\Field\FieldWidget\AttributionSourceLicenseWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionSourceLicenseWidget.
 *
 * @group attribution
 */
#[CoversClass(AttributionSourceLicenseWidget::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionSourceLicenseWidgetTest extends AttributionWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getWidgetPluginId(): string {
    return 'attribution_source_license';
  }

  /**
   * {@inheritdoc}
   */
  protected function assertWidgetSpecificElementsPresent(): void {
    $element = $this->buildFormElement($this->getEmptyItems(), 0);

    $this->assertSame('container', $element['source']['#type']);
    $this->assertContains('container-inline', $element['source']['#attributes']['class']);
    $this->assertSame('textfield', $element['source']['source_name']['#type']);
    $this->assertSame('url', $element['source']['source_link']['#type']);

    $this->assertSame('select', $element['license']['#type']);
    $this->assertArrayHasKey('cc_by_4_0', $element['license']['#options']);

    $this->assertArrayNotHasKey('author', $element);
  }

}
