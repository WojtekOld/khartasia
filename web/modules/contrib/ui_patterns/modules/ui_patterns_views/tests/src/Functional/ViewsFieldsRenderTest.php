<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_views\Functional;

use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Tests\ui_patterns\Functional\UiPatternsFunctionalTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests views field visibility options and multi-row rendering.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_views')]
#[RunTestsInSeparateProcesses]
final class ViewsFieldsRenderTest extends UiPatternsFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ui_patterns',
    'ui_patterns_test',
    'ui_patterns_field_formatters',
    'ui_patterns_views',
    'views',
    'views_ui',
    'block',
  ];

  /**
   * Hidden and excluded fields, hide_empty sources, and per-row values.
   */
  public function testFieldVisibilityAndRows(): void {
    $this->createTestContentContentType();
    $assert_session = $this->assertSession();
    $node_1 = $this->createTestContentNode('page', [
      'title' => ['value' => 'first_node_title'],
    ]);

    // A second field, after title, with no value: views renders its "0" empty
    // text, and empty_zero makes that output count as empty for hide_empty.
    $config = $this->loadConfigFixture(__DIR__ . '/../../fixtures/config/views.view.test.row_style.yml');
    $fields = &$config['display']['default']['display_options']['fields'];
    $fields['field_string_1'] = [
      'id' => 'field_string_1',
      'table' => 'node__field_string_1',
      'field' => 'field_string_1',
      'entity_type' => 'node',
      'entity_field' => 'field_string_1',
      'plugin_id' => 'field',
      'label' => '',
      'exclude' => FALSE,
      'empty' => '0',
      'hide_empty' => FALSE,
      'empty_zero' => TRUE,
      'hide_alter_empty' => TRUE,
      'click_sort_column' => 'value',
      'type' => 'string',
      'settings' => ['link_to_entity' => FALSE],
      'group_column' => 'value',
      'group_columns' => [],
      'group_rows' => TRUE,
      'delta_limit' => 0,
      'delta_offset' => 0,
      'delta_reversed' => FALSE,
      'delta_first_last' => FALSE,
      'multi_type' => 'separator',
      'separator' => ', ',
      'field_api_classes' => FALSE,
    ];
    $row_options = &$config['display']['page_1']['display_options']['row']['options'];
    $row_options['hide_empty'] = FALSE;
    $row_options['ui_patterns'] = [
      'component_id' => 'ui_patterns_test:test-wrapper-component',
      'variant_id' => NULL,
      'slots' => [
        'wrapper' => [
          'sources' => [
            [
              'source_id' => 'view_field',
              'source' => ['ui_patterns_views_field' => 'field_string_1'],
            ],
          ],
        ],
      ],
    ];

    // "0" is rendered when nothing hides it.
    $this->importAndVisit($config);
    $assert_session->elementTextEquals('css', '.ui-patterns-slots-wrapper', '0');

    // The row plugin hide_empty option hides it.
    $row_options['hide_empty'] = TRUE;
    $this->importAndVisit($config);
    $assert_session->elementTextEquals('css', '.ui-patterns-slots-wrapper', '');

    // The field own hide_empty option hides it too.
    $row_options['hide_empty'] = FALSE;
    $fields['field_string_1']['hide_empty'] = TRUE;
    $this->importAndVisit($config);
    $assert_session->elementTextEquals('css', '.ui-patterns-slots-wrapper', '');

    // An excluded field is not rendered.
    $fields['field_string_1']['hide_empty'] = FALSE;
    $fields['field_string_1']['exclude'] = TRUE;
    $this->importAndVisit($config);
    $assert_session->elementTextEquals('css', '.ui-patterns-slots-wrapper', '');

    // Each row renders its own values.
    $fields['field_string_1']['exclude'] = FALSE;
    $row_options['ui_patterns']['slots']['wrapper']['sources'][0]['source']['ui_patterns_views_field'] = 'title';
    $node_2 = $this->createTestContentNode('page', [
      'title' => ['value' => 'second_node_title'],
      'field_string_1' => ['value' => 'string_of_second_node'],
    ]);
    $node_3 = $this->createTestContentNode('page', [
      'title' => ['value' => 'third_node_title'],
    ]);
    $this->importAndVisit($config);
    $assert_session->elementsCount('css', '.ui-patterns-wrapper', 3);
    foreach (['first_node_title', 'second_node_title', 'third_node_title'] as $title) {
      $assert_session->pageTextContains($title);
    }

    // The style renders all the fields of each row when uses_fields is on.
    $config = $this->loadConfigFixture(__DIR__ . '/../../fixtures/config/views.view.test.style.yml');
    $fields_style = &$config['display']['default']['display_options']['fields'];
    $fields_style['field_string_1'] = $fields['field_string_1'];
    $config['display']['page_1']['display_options']['style'] = [
      'type' => 'ui_patterns',
      'options' => [
        'uses_fields' => TRUE,
        'ui_patterns' => [
          'ui_patterns' => [
            'component_id' => 'ui_patterns_test:test-wrapper-component',
            'variant_id' => NULL,
            'slots' => [
              'wrapper' => [
                'sources' => [
                  [
                    'source_id' => 'view_rows',
                    'source' => ['ui_patterns_views_field' => ''],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
    $this->importAndVisit($config);
    $assert_session->pageTextContains('second_node_title');
    $assert_session->pageTextContains('string_of_second_node');

    // An excluded field disappears from the rows.
    $fields_style['field_string_1']['exclude'] = TRUE;
    $this->importAndVisit($config);
    $assert_session->pageTextContains('second_node_title');
    $assert_session->pageTextNotContains('string_of_second_node');

    $node_1->delete();
    $node_2->delete();
    $node_3->delete();
  }

  /**
   * Imports the view and visits its page.
   */
  private function importAndVisit(array $config): void {
    $this->importConfigFixture('views.view.test', $config);
    \Drupal::service(RouteBuilderInterface::class)->rebuild();
    $this->drupalGet('test');
  }

}
