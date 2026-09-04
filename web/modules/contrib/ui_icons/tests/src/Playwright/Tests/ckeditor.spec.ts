import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import { Drupal } from '../objects/Drupal'
import config from '../playwright.config.loader'

// Port of the CKEditor 5 icon plugin FunctionalJavascript coverage: insert an
// icon from the toolbar, then edit it in place from the widget balloon.
// The icon pack, its icons and its settings defaults come from
// tests/modules/ui_icons_test/ui_icons_test.icons.yml.
// @see \Drupal\ui_icons_ckeditor5\Plugin\CKEditor5Plugin\IconPlugin
// @see \Drupal\ui_icons_ckeditor5\Form\IconDialog

const PACK_ID = 'test_path'
const FORMAT_ID = 'test_format'
const BUNDLE_ID = 'page'

type IconSettings = { width: number; height: number; title: string }

/**
 * Create the text format with the icon filter and its CKEditor 5 instance.
 *
 * The format is weighted first so it is the one selected by default in the
 * node form, which is what loads CKEditor 5 with the icon button.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @returns {Promise<void>}
 */
async function createTextFormat (drupal: Drupal): Promise<void> {
  const php = [
    `if (!\\Drupal\\filter\\Entity\\FilterFormat::load('${FORMAT_ID}')) {`,
    `\\Drupal\\filter\\Entity\\FilterFormat::create([`,
    `'format' => '${FORMAT_ID}',`,
    `'name' => 'Test format',`,
    `'weight' => -10,`,
    `'filters' => ['icon_embed' => ['status' => TRUE, 'settings' => ['allowed_icon_pack' => ['${PACK_ID}' => '${PACK_ID}']]]],`,
    `])->save();`,
    `\\Drupal\\editor\\Entity\\Editor::create([`,
    `'editor' => 'ckeditor5',`,
    `'format' => '${FORMAT_ID}',`,
    `'image_upload' => ['status' => FALSE],`,
    `'settings' => ['toolbar' => ['items' => ['icon', 'sourceEditing']], 'plugins' => ['ckeditor5_sourceEditing' => ['allowed_tags' => []]]],`,
    `])->save();`,
    `}`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

/**
 * Create the content type with a formatted body field.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @returns {Promise<void>}
 */
async function createContentType (drupal: Drupal): Promise<void> {
  const php = [
    `if (!\\Drupal\\node\\Entity\\NodeType::load('${BUNDLE_ID}')) {`,
    `\\Drupal\\node\\Entity\\NodeType::create(['type' => '${BUNDLE_ID}', 'name' => 'Page'])->save();`,
    `\\Drupal\\field\\Entity\\FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_long'])->save();`,
    `\\Drupal\\field\\Entity\\FieldConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'bundle' => '${BUNDLE_ID}', 'label' => 'Body'])->save();`,
    `\\Drupal::service('entity_display.repository')->getFormDisplay('node', '${BUNDLE_ID}')->setComponent('body', ['type' => 'text_textarea'])->save();`,
    `\\Drupal::service('entity_display.repository')->getViewDisplay('node', '${BUNDLE_ID}')->setComponent('body', ['label' => 'hidden', 'type' => 'text_default'])->save();`,
    `}`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

/**
 * Open the node form and wait for CKEditor 5 to be ready.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Promise<void>}
 */
async function openEditor (page: Page): Promise<void> {
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
  await expect(page.locator('.ck-editor__editable')).toBeVisible()
  // Ensure that CKEditor 5 is focused, the icon command is enabled only when
  // the selection is inside the editable.
  await page.locator('.ck-content').click()
}

/**
 * Fill the (already open) icon dialog and save it.
 *
 * Works for both the insert and the edit dialog, since they share the same
 * form.
 *
 * @param {Page} page - The Playwright page.
 * @param {string} iconId - The full icon id (pack_id:icon_id) to select.
 * @param {string} filename - The expected preview filename, used to wait for the ajax refresh.
 * @param {IconSettings|null} settings - Extractor settings to fill, or null to keep the defaults.
 * @returns {Promise<void>}
 */
async function fillIconDialogAndSave (page: Page, iconId: string, filename: string, settings: IconSettings | null = null): Promise<void> {
  const modal = page.locator('#drupal-modal')

  // The icon has to be picked in the autocomplete results: the element only
  // rebuilds itself, and its extractor settings sub-form, on the ajax bound to
  // the 'autocompleteclose' event.
  await modal.locator('[name="icon[icon_id]"]').fill(iconId)
  // The result list is appended to the body, outside of the modal.
  const results = page.locator('ul.ui-autocomplete li')
  await expect(results).toHaveCount(1)
  await results.first().click()

  // Wait until the autocomplete preview reflects the chosen icon, which means
  // the settings sub-form has been (re)built.
  await expect(modal.locator(`.ui-icons-preview-icon img[src$="${filename}"]`)).toBeVisible()

  if (settings) {
    await modal.locator('.ui-icons-settings-wrapper details summary').click()
    for (const [ key, value ] of Object.entries(settings)) {
      await modal.locator(`[name="icon[icon_settings][${PACK_ID}][${key}]"]`).fill(String(value))
    }
  }

  await page.locator('.ui-dialog-buttonpane').getByRole('button', { name: 'Save' }).click()
  await expect(modal).toBeHidden()
}

/**
 * Read the `<drupal-icon>` elements from the CKEditor 5 data.
 *
 * This is the markup handed over to the text filter, ie. what gets stored.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Promise<{id: string, settings: IconSettings}[]>} The icons found in the editor data.
 */
async function getEditorIcons (page: Page): Promise<{ id: string | null; settings: Record<string, unknown> }[]> {
  return await page.evaluate(() => {
    const editable = document.querySelector('.ck-editor__editable') as any
    const data = editable.ckeditorInstance.getData()
    const dom = new DOMParser().parseFromString(data, 'text/html')
    return [ ...dom.querySelectorAll('drupal-icon') ].map(element => ({
      id: element.getAttribute('data-icon-id'),
      settings: JSON.parse(element.getAttribute('data-icon-settings') || '{}'),
    }))
  })
}

/**
 * Assert the rendered icon markup, from the icon pack template.
 *
 * @param {Locator} icon - Locator on the icon `img` element.
 * @param {string} filename - The expected filename the 'src' ends with.
 * @param {string} iconClass - The expected 'class' attribute.
 * @param {IconSettings} settings - The expected settings printed as attributes.
 * @returns {Promise<void>}
 */
async function expectIcon (icon: Locator, filename: string, iconClass: string, settings: IconSettings): Promise<void> {
  await expect(icon).toHaveAttribute('src', new RegExp(`${filename.replace('.', '\\.')}$`))
  await expect(icon).toHaveAttribute('class', iconClass)
  for (const [ key, value ] of Object.entries(settings)) {
    await expect(icon).toHaveAttribute(key, String(value))
  }
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'node', 'text', 'ckeditor5', 'ui_icons_ckeditor5', 'ui_icons_test' ])
  await createTextFormat(drupal)
  await createContentType(drupal)
  await drupal.loginAsAdmin()
})

// The default settings case never opens the settings details: the icon must
// still be rendered with the defaults declared by the icon pack.
const insertCases: { name: string; iconId: string; filename: string; iconClass: string; fillSettings: boolean; settings: IconSettings }[] = [
  {
    name: 'default settings',
    iconId: `${PACK_ID}:foo`,
    filename: 'foo.png',
    iconClass: 'icon icon-foo',
    fillSettings: false,
    settings: { width: 32, height: 33, title: 'Default title' },
  },
  {
    name: 'changed settings',
    iconId: `${PACK_ID}:bar`,
    filename: 'bar.png',
    iconClass: 'icon icon-bar',
    fillSettings: true,
    settings: { width: 98, height: 99, title: 'Test title' },
  },
]

for (const data of insertCases) {
  test(`Insert an icon with ${data.name}`, { tag: [ '@base' ] }, async ({ page, drupal }) => {
    await test.step(`Open the icon dialog from the toolbar`, async () => {
      await openEditor(page)

      const button = page.getByRole('button', { name: 'Insert Icon' })
      await expect(button).toBeEnabled()
      await button.click()

      await expect(page.locator('#drupal-modal')).toBeVisible()
      await expect(page.locator('[name="icon[icon_id]"]')).toBeVisible()
    })

    await test.step(`Select the icon and save the dialog`, async () => {
      await fillIconDialogAndSave(page, data.iconId, data.filename, data.fillSettings ? data.settings : null)
    })

    await test.step(`The icon is previewed in the editor`, async () => {
      await expectIcon(page.locator('.ck-content .drupal-icon span img'), data.filename, data.iconClass, data.settings)
    })

    await test.step(`The editor data holds the drupal-icon tag`, async () => {
      const icons = await getEditorIcons(page)
      expect(icons).toHaveLength(1)
      expect(icons[0].id).toBe(data.iconId)
      for (const [ key, value ] of Object.entries(data.settings)) {
        // Because of json we lost types.
        expect(String(icons[0].settings[key])).toBe(String(value))
      }
    })

    await test.step(`The saved node renders the icon`, async () => {
      await page.locator('[name="title[0][value]"]').fill('My test content')
      await page.locator('[data-drupal-selector="edit-submit"]').click()
      await drupal.expectMessage('has been created')

      await expectIcon(page.locator('.drupal-icon img'), data.filename, data.iconClass, data.settings)
    })
  })
}

test('Edit an icon in place from the widget toolbar', { tag: [ '@base' ] }, async ({ page }) => {
  const iconId1 = `${PACK_ID}:foo`
  const iconId2 = `${PACK_ID}:bar`
  const initialSettings: IconSettings = { width: 40, height: 41, title: 'Initial title' }
  const updatedSettings: IconSettings = { width: 80, height: 81, title: 'Updated title' }

  await test.step(`Insert an icon with custom settings`, async () => {
    await openEditor(page)
    await page.getByRole('button', { name: 'Insert Icon' }).click()
    await expect(page.locator('#drupal-modal')).toBeVisible()
    await fillIconDialogAndSave(page, iconId1, 'foo.png', initialSettings)

    await expect(page.locator('.ck-content .drupal-icon span img')).toBeVisible()
    expect(await getEditorIcons(page)).toHaveLength(1)
  })

  await test.step(`Open the edit dialog from the widget toolbar`, async () => {
    await page.locator('.ck-widget.drupal-icon').click()
    await expect(page.locator('[aria-label="Icon toolbar"]')).toBeVisible()
    await page.locator('[aria-label="Icon toolbar"]').getByRole('button', { name: 'Edit' }).click()
    await expect(page.locator('#drupal-modal')).toBeVisible()
  })

  await test.step(`The dialog is pre-filled with the existing icon`, async () => {
    await expect(page.locator('[name="icon[icon_id]"]')).toHaveValue(iconId1)

    // Regression guard for the #default_settings keying: they must be keyed by
    // pack id, otherwise the extractor sub-form comes back empty. The fields
    // live inside a collapsed <details>, so read the values without toggling
    // it open.
    for (const [ key, value ] of Object.entries(initialSettings)) {
      await expect(page.locator(`[name="icon[icon_settings][${PACK_ID}][${key}]"]`)).toHaveValue(String(value))
    }
  })

  await test.step(`Change the icon and its settings`, async () => {
    await fillIconDialogAndSave(page, iconId2, 'bar.png', updatedSettings)
  })

  await test.step(`The widget is updated in place, not duplicated`, async () => {
    await expect(page.locator('.ck-content .drupal-icon span img[src$="bar.png"]')).toBeVisible()

    const icons = await getEditorIcons(page)
    expect(icons, 'Editing replaces the icon instead of inserting a new one.').toHaveLength(1)
    expect(icons[0].id).toBe(iconId2)
    for (const [ key, value ] of Object.entries(updatedSettings)) {
      // Because of json we lost types.
      expect(String(icons[0].settings[key])).toBe(String(value))
    }
  })
})
