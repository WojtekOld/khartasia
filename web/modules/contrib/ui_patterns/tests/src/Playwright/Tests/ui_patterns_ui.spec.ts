import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { Audit } from '../objects/Audit'
import type { Drupal } from '../objects/Drupal'
import { select } from '../objects/ComponentForm'
import { createDisplay } from '../objects/FormDisplay'
import { enableModules } from '../objects/Setup'
import { resetArticleDisplay, seedArticles } from '../objects/TestContent'

// Form displays (ui_patterns_ui) over AJAX: the admin table, then the display
// selector inside a block form. One long test: login and setup are done once.
//
// Covered: a row moved to Content offers the source selector at once, a cog
// edits widget settings and the summary follows, the display selector
// switches forms and keeps its defaults, then save, reopen, and render.

const component = config.testComponentId
const editor = 'ui_patterns_test.test-component.editor'
const alpha = 'ui_patterns_test.test-component.alpha'
// Html::getId() of a display id: dots dropped, underscores dashed.
const field = (display: string, path: string) => `[name*="[value_${display.replace(/[._]/g, m => (m === '.' ? '' : '-'))}]${path}"]`

/** Removes the blocks and displays of the test component: later specs expect its plain form. */
async function cleanup(drupal: Drupal): Promise<void> {
  await drupal.drush(
    `ev "\\$s = \\Drupal::entityTypeManager()->getStorage('block'); \\$s->delete(\\$s->loadByProperties(['plugin' => 'ui_patterns:${component}'])); \\$s = \\Drupal::entityTypeManager()->getStorage('component_form_display'); \\$s->delete(\\$s->loadByProperties(['component_id' => '${component}']));"`,
  )
}

test('UI Patterns UI: form displays over AJAX', { tag: ['@base', '@ui_patterns_ui'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let theme = 'claro'

  await test.step('0. Setup: modules, displays, cleanup, watchdog marker, admin login', async () => {
    await enableModules(drupal)
    theme = (await drupal.drush(`ev "print \\Drupal::config('system.theme')->get('default');"`)).trim()
    await cleanup(drupal)
    const rowOf = (region: string, source_id: string | null, extra: Record<string, unknown> = {}, weight = 0) => ({
      region,
      weight: String(weight),
      parent: '',
      widget_settings: {},
      third_party_settings: {},
      ...(source_id === null ? {} : { source_id }),
      ...extra,
    })
    await createDisplay(drupal, {
      label: 'Editor',
      component_id: component,
      form_mode_name: 'editor',
      status: true,
      content: {
        variant: rowOf('configure', 'select', { source: { value: 'other' } }),
        string: rowOf('content', 'textfield', { widget_settings: { title: 'Custom title', required: 1 } }, 1),
        boolean_with_default_true: rowOf('content', '', {}, 2),
        slot: rowOf('content', '', {}, 3),
      },
      hidden: { integer: true },
    })
    await createDisplay(drupal, {
      label: 'Alpha',
      component_id: component,
      form_mode_name: 'alpha',
      status: true,
      content: { string: rowOf('content', 'textfield') },
      hidden: {},
    })
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Admin table: a row moved to Content offers the source selector', async () => {
    await page.goto(`admin/structure/component/${component}/form-display/editor`)
    const sourceSelect = page.locator('select[name="fields[integer][source_id]"]')
    await expect(sourceSelect.locator('option[value=""]')).toHaveCount(0)
    // Tabledrag hides the region select behind the row weights toggle, and
    // Field UI only refreshes a row over AJAX on drag and drop: pick the
    // region, then refresh the row the way a drop does.
    await page.locator('.tabledrag-toggle-weight').first().click()
    await page.locator('select[name="fields[integer][region]"]').selectOption('content')
    await page.evaluate(() => {
      const w = window as unknown as { Drupal: { fieldUIOverview: { AJAXRefreshRows: (rows: Record<string, Element | null>) => void } } }
      w.Drupal.fieldUIOverview.AJAXRefreshRows({ integer: document.querySelector('select[name="fields[integer][source_id]"]') })
    })
    await expect(sourceSelect.locator('option[value=""]')).toHaveCount(1, { timeout: 20_000 })
    await sourceSelect.selectOption('')
  })

  await test.step('2. Admin table: the cog edits widget settings, the summary follows', async () => {
    await page.locator('input[name="string_settings_edit"]').click()
    const title = page.locator('input[name="fields[string][settings_edit_form][settings][title]"]')
    await title.waitFor({ state: 'attached', timeout: 20_000 })
    await expect(title).toHaveValue('Custom title')
    await title.fill('From the cog')
    await page.locator('input[name="string_plugin_settings_update"]').click()
    await expect(page.locator('tr:has(input[name="string_settings_edit"]) .field-plugin-summary')).toContainText('Title: From the cog', { timeout: 20_000 })
    await page.locator('[data-drupal-selector="edit-actions-submit"], [data-drupal-selector="edit-submit"]').first().click()
    await expect(page.locator('select[name="fields[integer][region]"]')).toHaveValue('content')
    await expect(page.locator('select[name="fields[integer][source_id]"]')).toHaveValue('')
    await expect(page.locator('tr:has(input[name="string_settings_edit"]) .field-plugin-summary')).toContainText('Title: From the cog')
  })

  await test.step('3. Block form: switch displays, defaults and titles follow', async () => {
    await page.goto(`admin/structure/block/add/ui_patterns:${component}/${theme}`)

    const displaySelect = page.locator('select[name="settings[ui_patterns][display_id]"]')
    const options = await displaySelect.locator('option').evaluateAll(els => els.map(el => (el as HTMLOptionElement).value))
    expect(options).toEqual([alpha, editor, '_component_form'])
    await expect(displaySelect).toHaveValue(alpha)
    await page.locator(field(alpha, '[string][source][value]')).fill('alpha text')

    // Editor: the fixed variant is not a field, the widget title and required
    // flag come from the display, the untouched boolean keeps its default.
    await displaySelect.selectOption(editor)
    const editorString = page.locator(field(editor, '[string][source][value]'))
    await editorString.waitFor({ state: 'attached', timeout: 20_000 })
    await expect(page.locator(field(editor, '[variant]'))).toHaveCount(0)
    await expect(editorString).toHaveAttribute('required', 'required')
    await expect(page.locator(`label[for="${await editorString.getAttribute('id')}"]`)).toContainText('From the cog')
    await expect(page.locator(field(editor, '[boolean_with_default_true][source][value]'))).toBeChecked()

    // The component form, then back: the display form is rebuilt.
    await displaySelect.selectOption('_component_form')
    const plainString = page.locator(field('_component_form', '[props][string][source][value]'))
    await plainString.waitFor({ state: 'attached', timeout: 20_000 })
    await expect(editorString).toHaveCount(0)
    await displaySelect.selectOption(editor)
    await editorString.waitFor({ state: 'attached', timeout: 20_000 })
    await editorString.fill('editor text')
  })

  await test.step('4. Save, reopen, render', async () => {
    await page.locator('select[name="region"]').selectOption('content')
    await page.locator('[data-drupal-selector="edit-actions-submit"], [data-drupal-selector="edit-submit"]').first().click()
    await page.waitForURL(/admin\/structure\/block\/list/)

    // The only block of the component: step 0 removed the others.
    const id = (
      await drupal.drush(
        `ev "foreach (\\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['plugin' => 'ui_patterns:${component}']) as \\$b) { print \\$b->id(); }"`,
      )
    ).trim()
    await page.goto(`admin/structure/block/manage/${id}`)
    await expect(page.locator('select[name="settings[ui_patterns][display_id]"]')).toHaveValue(editor)
    await expect(page.locator(field(editor, '[string][source][value]'))).toHaveValue('editor text')
    await expect(page.locator(field(editor, '[boolean_with_default_true][source][value]'))).toBeChecked()

    await page.goto('')
    const rendered = page.locator('.ui-patterns-test-component-variant-other')
    await expect(rendered).toHaveCount(1)
    await expect(rendered.locator('> .ui-patterns-props-string')).toHaveText('editor text')
    await expect(rendered.locator('> .ui-patterns-props-boolean_with_default_true')).toHaveText('1')
  })

  await test.step('5. CKEditor 5 dialog: the display selector in the modal, then the preview', async () => {
    await enableModules(drupal, { content: true })
    await seedArticles(drupal)
    await page.goto('node/add/test_article')
    const format = page.locator('select[name="field_test_body[0][format]"]')
    if ((await format.count()) > 0) {
      await format.selectOption('test_components')
    }
    await page.locator('.ck-editor__editable').waitFor({ timeout: 20_000 })
    await page.locator('.ck-toolbar .ck-button[data-cke-tooltip-text="Insert component"]').click()
    const dialog = page.locator('.ui-dialog')
    await dialog.waitFor({ timeout: 20_000 })
    await drupal.ajaxReady()
    await dialog.locator('select[name="component[component_id]"]').selectOption(component)
    const dialogSelect = dialog.locator('select[name="component[display_id]"]')
    await dialogSelect.waitFor({ state: 'attached', timeout: 20_000 })
    // The AJAX of the selector posts to the dialog route.
    await dialogSelect.selectOption(editor)
    const dialogString = dialog.locator(field(editor, '[string][source][value]'))
    await dialogString.waitFor({ state: 'attached', timeout: 20_000 })
    // A rebuild that is not an AJAX form submission nests the whole form.
    await expect(dialogSelect).toHaveCount(1)
    await dialogString.fill('from the dialog')
    await page.locator('.ui-dialog-buttonset button').first().click()
    await dialog.waitFor({ state: 'detached', timeout: 20_000 })
    const preview = page.locator('.ck-editor__editable .ck-widget.drupal-component .ui-patterns-test-component-variant-other')
    await expect(preview.locator('.ui-patterns-props-string')).toHaveText('from the dialog', { timeout: 20_000 })
  })

  await test.step('6. Cleanup: the later specs expect the plain form of the component', async () => {
    await cleanup(drupal)
  })

  await test.step('7. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// Switching between a component form display and the plain component form:
// the values typed on either side survive the round trip, for a prop and
// for a slot. The display targets a component no other test configures, so
// the worker site stays clean for the rest of the suite even on failure.
test('Displays: switching to the component form and back keeps the values', { tag: ['@base', '@ui_patterns_ui'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const fieldName = 'field_test_tags'
  const base = `fields[${fieldName}][settings_edit_form][settings][ui_patterns]`
  const displayId = 'ui_patterns_test.test-form-component.panel'
  // Html::getId() of the display id: dots dropped.
  const curated = `${base}[display][value_ui-patterns-testtest-form-componentpanel]`
  const classic = `${base}[display][value_-component-form]`
  let nodeId = ''

  const deleteDisplay = () =>
    drupal.drush(
      `ev "\\$s = \\Drupal::entityTypeManager()->getStorage('component_form_display'); \\$s->delete(\\$s->loadByProperties(['id' => '${displayId}']));"`,
    )

  await test.step('0. Setup: a display with a fixed prop and a fixed slot source', async () => {
    await enableModules(drupal, { content: true })
    await deleteDisplay()
    await createDisplay(drupal, {
      label: 'Panel',
      component_id: 'ui_patterns_test:test-form-component',
      form_mode_name: 'panel',
      status: true,
      content: {
        string: { region: 'content', weight: '0', parent: '', widget_settings: {}, third_party_settings: {}, source_id: 'textfield' },
        slot: { region: 'content', weight: '1', parent: '', widget_settings: {}, third_party_settings: {}, source_id: 'wysiwyg' },
      },
      hidden: { base_id: true },
    })
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Fill the display form: a prop and a slot', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default')
    await page.locator(`select[name="fields[${fieldName}][type]"]`).selectOption('ui_patterns_component_per_item')
    await page.getByText('No component selected.').waitFor({ timeout: 20_000 })
    await page.locator(`input[name="${fieldName}_settings_edit"]`).click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await select(page, `${base}[component_id]`).selectOption('ui_patterns_test:test-form-component')
    await page.locator(`input[name="${curated}[string][source][value]"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.locator(`input[name="${curated}[string][source][value]"]`).fill('keep-prop')
    await page.locator(`textarea[name="${curated}[slot][source][value][value]"]`).fill('keep-slot')
  })

  await test.step('2. Switch to the component form: both values are there', async () => {
    await select(page, `${base}[display_id]`).selectOption('_component_form')
    const propValue = page.locator(`input[name="${classic}[props][string][source][value]"]`)
    await propValue.waitFor({ state: 'attached', timeout: 20_000 })
    await expect(propValue).toHaveValue('keep-prop')
    await page.evaluate(() => document.querySelectorAll('details').forEach(d => (d.open = true)))
    await expect(page.locator(`textarea[name="${classic}[slots][slot][sources][0][source][value][value]"]`)).toHaveValue('keep-slot')
  })

  await test.step('3. Edit on the component form, switch back: everything kept', async () => {
    await page.locator(`input[name="${classic}[props][string][source][value]"]`).fill('keep-prop-2')
    await select(page, `${base}[display_id]`).selectOption(displayId)
    const curatedValue = page.locator(`input[name="${curated}[string][source][value]"]`)
    await curatedValue.waitFor({ state: 'attached', timeout: 20_000 })
    await expect(curatedValue).toHaveValue('keep-prop-2')
    await expect(page.locator(`textarea[name="${curated}[slot][source][value][value]"]`)).toHaveValue('keep-slot')
  })

  await test.step('4. Save, render: prop and slot reach the node', async () => {
    await page.locator(`input[name="${fieldName}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${fieldName}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
    await page.goto(`node/${nodeId}`)
    await expect(page.locator('.ui-patterns-test-form input[id$="-input"]').first()).toHaveValue('keep-prop-2')
    await expect(page.locator('.ui-patterns-test-form textarea').first()).toContainText('keep-slot')
  })

  await test.step('5. Cleanup and audit', async () => {
    await deleteDisplay()
    await audit.expectClean()
  })
})
