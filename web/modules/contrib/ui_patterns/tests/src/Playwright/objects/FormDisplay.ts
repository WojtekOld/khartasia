import type { Drupal } from './Drupal'

// Form displays of ui_patterns_ui, saved over drush.

/** Saves a display over drush, values carried as base64 JSON. */
export async function createDisplay(drupal: Drupal, values: Record<string, unknown>): Promise<void> {
  const json = Buffer.from(JSON.stringify(values)).toString('base64')
  await drupal.drush(`php:eval "\\Drupal\\ui_patterns_ui\\Entity\\ComponentFormDisplay::create(json_decode(base64_decode('${json}'), TRUE))->save();"`)
}
