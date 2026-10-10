<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Widget;

use Drupal\attribution\Plugin\Field\FieldWidget\AttributionSourceAuthorLicenseWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionSourceAuthorLicenseWidget.
 *
 * @group attribution
 */
#[CoversClass(AttributionSourceAuthorLicenseWidget::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionSourceAuthorLicenseWidgetTest extends AttributionWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getWidgetPluginId(): string {
    return 'attribution_source_author_license';
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

    $this->assertSame('container', $element['author']['#type']);
    $this->assertContains('container-inline', $element['author']['#attributes']['class']);
    $this->assertSame('textfield', $element['author']['author_name']['#type']);
    $this->assertSame('url', $element['author']['author_link']['#type']);

    $this->assertSame('select', $element['license']['#type']);
    $this->assertArrayHasKey('cc_by_4_0', $element['license']['#options']);
    // The field is not required in the test fixture, so an empty
    // "please choose" option is prepended.
    $this->assertArrayHasKey('', $element['license']['#options']);
  }

}
