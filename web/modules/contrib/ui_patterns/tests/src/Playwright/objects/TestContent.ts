import type { Drupal } from './Drupal'

// Content from ui_patterns_test_content, seeded over drush so every run, on a
// fresh or a reused site, starts from the same articles and terms.

/** Two terms, two articles; returns the id of the article referencing everything. */
export async function seedArticles(drupal: Drupal): Promise<string> {
  const php = [
    "\\$nodes = \\Drupal::entityTypeManager()->getStorage('node');",
    "\\$terms = \\Drupal::entityTypeManager()->getStorage('taxonomy_term');",
    "\\$nodes->delete(\\$nodes->loadByProperties(['type' => 'test_article']));",
    "\\$terms->delete(\\$terms->loadByProperties(['vid' => 'test_tags']));",
    "\\$blue = \\$terms->create(['vid' => 'test_tags', 'name' => 'Drupal', 'field_test_color' => 'blue']); \\$blue->save();",
    "\\$green = \\$terms->create(['vid' => 'test_tags', 'name' => 'Playwright', 'field_test_color' => 'green']); \\$green->save();",
    "\\$second = \\$nodes->create(['type' => 'test_article', 'title' => 'Second article', 'field_test_summary' => 'Summary of the second article', 'field_test_tags' => [\\$green->id()]]); \\$second->save();",
    "\\$first = \\$nodes->create(['type' => 'test_article', 'title' => 'First article', 'field_test_summary' => 'Summary of the first article', 'field_test_tags' => [\\$blue->id(), \\$green->id()], 'field_test_related' => \\$second->id()]); \\$first->save();",
    'print \\$first->id();',
  ].join(' ')
  return (await drupal.drush(`ev "${php}"`)).trim()
}

/** The article display as shipped: plain formatters, no layout builder. */
export async function resetArticleDisplay(drupal: Drupal): Promise<void> {
  const php = [
    "\\$display = \\Drupal::service('entity_display.repository')->getViewDisplay('node', 'test_article');",
    '\\$display->disableLayoutBuilder()->save();',
    "\\$display->setComponent('field_test_tags', ['type' => 'entity_reference_label', 'settings' => ['link' => TRUE]])->save();",
    // Layout builder keeps unsaved changes in a shared tempstore.
    "\\Drupal::keyValueExpirable('tempstore.shared.layout_builder.section_storage.defaults')->delete('node.test_article.default');",
  ].join(' ')
  await drupal.drush(`ev "${php}"`)
}

/** Layout builder on the article display, defaults only. */
export async function enableLayoutBuilder(drupal: Drupal): Promise<void> {
  await drupal.drush(
    `ev "\\Drupal::service('entity_display.repository')->getViewDisplay('node', 'test_article')->enableLayoutBuilder()->setOverridable(FALSE)->save();"`,
  )
}

/** The articles view as shipped: default style, no leftover tempstore. */
export async function resetArticlesView(drupal: Drupal): Promise<void> {
  const php = [
    "\\$config = \\Drupal::configFactory()->getEditable('views.view.test_articles');",
    "\\$config->set('display.default.display_options.style', ['type' => 'default', 'options' => []])->save();",
    // The views UI reads from a shared tempstore which shadows config edits.
    "\\Drupal::keyValueExpirable('tempstore.shared.views')->delete('test_articles');",
  ].join(' ')
  await drupal.drush(`ev "${php}"`)
}

