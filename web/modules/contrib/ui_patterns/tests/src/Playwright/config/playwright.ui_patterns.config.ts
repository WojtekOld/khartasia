/*
 * ui_patterns tests configuration.
 */
export default {
  testComponentId: 'ui_patterns_test:test-component',
  testComponentLabel: 'UI Patterns Test component',
  testModules: [
    'ui_patterns_test',
    'ui_patterns_blocks',
    'menu_link_content',
    'block',
    'dblog',
  ],
  // Content model for the plugin types that carry an entity context.
  contentModules: [
    'node',
    'taxonomy',
    'field',
    'field_ui',
    'ui_patterns_field_formatters',
    'layout_builder',
    'ui_patterns_layouts',
    'views',
    'views_ui',
    'ui_patterns_views',
    'ckeditor5',
    'ui_patterns_ckeditor5',
    'ui_patterns_test_content',
  ],
}
