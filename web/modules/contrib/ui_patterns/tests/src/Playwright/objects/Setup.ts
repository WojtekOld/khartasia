import type { Drupal } from './Drupal'
import config from '../playwright.config.loader'

// The install phase shared by the specs: enable the modules a spec needs.
// Case 1 gets them from PlaywrightTestSetup.php already; this makes Case 2
// (an existing site) match, and states the dependency in the spec.

/**
 * Enables the test modules.
 *
 * @param content Also enable the content model (node, taxonomy, formatters,
 *   ui_patterns_test_content) the entity plugin types need.
 */
export async function enableModules(drupal: Drupal, { content = false }: { content?: boolean } = {}): Promise<void> {
  const modules = content ? [...config.testModules, ...config.contentModules] : config.testModules
  await drupal.drush(`pm:enable -y ${modules.join(' ')}`)
}

/**
 * Lists the component display extender in the views settings.
 *
 * hook_install() does it on a fresh site; an existing site installed before
 * the extender existed needs it too.
 */
export async function enableViewsDisplayExtender(drupal: Drupal): Promise<void> {
  const php = [
    "\\$c = \\Drupal::configFactory()->getEditable('views.settings');",
    "\\$e = \\$c->get('display_extenders') ?: [];",
    "if (!in_array('ui_patterns', \\$e, TRUE)) { \\$e[] = 'ui_patterns'; \\$c->set('display_extenders', \\$e)->save(); }",
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}
