<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Formatter;

use Drupal\attribution\Plugin\Field\FieldFormatter\AttributionPlainOnelineFormatter;
use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionPlainOnelineFormatter.
 *
 * @group attribution
 */
#[CoversClass(AttributionPlainOnelineFormatter::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionPlainOnelineFormatterTest extends AttributionFormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFormatterPluginId(): string {
    return 'attribution_plain_oneline';
  }

  /**
   * Plain oneline has no wrapper element and never sets #attributes.
   */
  protected function assertCssClasses(): void {
    $elements = $this->viewElementsForValues(['license' => 'gpl_2_0_or_later']);
    $this->assertArrayNotHasKey('#attributes', $elements[0]);
  }

  /**
   * Plain oneline exposes #creation but never attaches CSS classes for it.
   */
  protected function assertCreationValuesPassedToRenderArray(): void {
    $elements = $this->viewElementsForValues(['license' => 'cc_by_4_0']);
    $this->assertArrayNotHasKey('#creation', $elements[0]);

    $elements = $this->viewElementsForValues([
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_tool' => 'Midjourney v6',
    ]);
    $this->assertArrayHasKey('#creation', $elements[0]);
    $this->assertSame(AttributionItem::CREATION_TYPE_AI_GENERATED, $elements[0]['#creation']['type']);
    $this->assertArrayNotHasKey('#attributes', $elements[0]);
  }

}
