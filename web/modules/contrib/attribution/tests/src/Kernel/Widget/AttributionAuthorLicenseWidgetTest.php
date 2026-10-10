<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Widget;

use Drupal\attribution\Plugin\Field\FieldWidget\AttributionAuthorLicenseWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionAuthorLicenseWidget.
 *
 * @group attribution
 */
#[CoversClass(AttributionAuthorLicenseWidget::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionAuthorLicenseWidgetTest extends AttributionWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getWidgetPluginId(): string {
    return 'attribution_author_license';
  }

  /**
   * {@inheritdoc}
   */
  protected function assertWidgetSpecificElementsPresent(): void {
    $element = $this->buildFormElement($this->getEmptyItems(), 0);

    $this->assertSame('container', $element['author']['#type']);
    $this->assertContains('container-inline', $element['author']['#attributes']['class']);
    $this->assertSame('textfield', $element['author']['author_name']['#type']);
    $this->assertSame('url', $element['author']['author_link']['#type']);

    $this->assertSame('select', $element['license']['#type']);
    $this->assertArrayHasKey('cc_by_4_0', $element['license']['#options']);

    $this->assertArrayNotHasKey('source', $element);
  }

}
