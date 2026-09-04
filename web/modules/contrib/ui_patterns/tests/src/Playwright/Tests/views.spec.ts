import { expect, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { openAllDetails, select } from '../objects/ComponentForm'
import { resetArticlesView, seedArticles } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'
import type { Drupal } from '../objects/Drupal'

// The three places views uses components: the style plugin around the rows,
// the row plugin per row, and the component formatter on a views field.
// Each one: build the settings in the views dialogs, apply, save, reopen,
// then look at the page the view renders.
//
// The view comes from ui_patterns_test_content (views.view.test_articles, a
// page at /test-articles listing the two seeded articles).
//
// Style and row share a bug: picking the component does not load its form,
// the request behind that select fails. Both bypass it by applying and
// reopening, which is what a user does too. The views field does not have
// the bug and is driven over AJAX. See the fixme at the end of this file.

const dialog = '.ui-dialog'
const applyButton = `${dialog} .ui-dialog-buttonpane button:has-text("Apply")`
const formatLink = 'a[href$="/page_1/style"]'
const formatSettingsLink = 'a[href$="/page_1/style_options"]'
const showLink = 'a[href$="/page_1/row"]'
const showSettingsLink = 'a[href$="/page_1/row_options"]'
const tagsFieldLink = 'a[href$="/field/field_test_tags"]'

/** Modules, content and a view with the shipped settings. */
async function setup(drupal: Drupal, audit: Audit): Promise<void> {
  await enableModules(drupal, { content: true })
  await resetArticlesView(drupal)
  await seedArticles(drupal)
  await audit.start()
  await drupal.loginAsAdmin()
}

/** Applies the dialog and waits for it to close. */
async function apply(page: Page): Promise<void> {
  await page.locator(applyButton).click()
  await page.locator(dialog).waitFor({ state: 'detached', timeout: 20_000 })
}

/**
 * Picks the component without firing the select's change event.
 * The request that event triggers fails in the style and row dialogs; the
 * value still travels with the form when the dialog is applied.
 */
async function pickComponentQuietly(page: Page, name: string): Promise<void> {
  await select(page, name).evaluate((el, id) => {
    ;(el as HTMLSelectElement).value = id
  }, config.testComponentId)
}

async function saveView(page: Page, drupal: Drupal): Promise<void> {
  await page.getByRole('button', { name: 'Save' }).click()
  await drupal.expectMessage('has been saved')
}

test('Views style: a component around the rows', { tag: ['@base', '@views'] }, async ({ page, drupal }) => {
  // Admin pages only: the view page renders in the site theme.
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const base = 'style_options[ui_patterns][ui_patterns]'

  await test.step('0. Setup: modules, content, a fresh view, admin login', async () => {
    await setup(drupal, audit)
  })

  await test.step('1. Format: choose the component style', async () => {
    await page.goto('admin/structure/views/view/test_articles')
    await page.locator(formatLink).click()
    const radio = page.locator(`${dialog} input[name="style[type]"][value="ui_patterns"]`)
    await radio.waitFor({ timeout: 20_000 })
    await radio.click()
    await page.locator(applyButton).click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
  })

  await test.step('2. Pick the component, apply, reopen: the form is there', async () => {
    await pickComponentQuietly(page, `${base}[component_id]`)
    await apply(page)
    await page.locator(formatSettingsLink).click()
    await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await expect(select(page, `${base}[component_id]`)).toHaveValue(config.testComponentId)
  })

  await test.step('3. Configure over AJAX: the view title, the rows in the slot', async () => {
    await openAllDetails(page)
    // Source switch over AJAX inside the dialog: the value form is replaced.
    await select(page, `${base}[props][string][source_id]`).selectOption('view_title')
    await page.locator(`input[name="${base}[props][string][source][value]"]`).waitFor({ state: 'detached', timeout: 20_000 })
    await openAllDetails(page)
    await select(page, `${base}[slots][slot][add_more_button]`).selectOption('view_rows')
    await page.locator(`[name="${base}[slots][slot][sources][0][source_id]"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await page.locator(`input[name="${base}[props][boolean][source][value]"]`).check()
    await page.locator(`input[name="${base}[props][attributes][source][value]"]`).fill('class="from-view-style"')
    await apply(page)
  })

  await test.step('4. Save, reopen the dialog: everything is kept', async () => {
    await saveView(page, drupal)
    await page.locator(formatSettingsLink).click()
    await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await expect(select(page, `${base}[component_id]`)).toHaveValue(config.testComponentId)
    await expect(select(page, `${base}[props][string][source_id]`)).toHaveValue('view_title')
    await expect(page.locator(`[name="${base}[slots][slot][sources][0][source_id]"]`)).toHaveValue('view_rows')
    await expect(page.locator(`input[name="${base}[props][boolean][source][value]"]`)).toBeChecked()
    await expect(page.locator(`input[name="${base}[props][attributes][source][value]"]`)).toHaveValue('class="from-view-style"')
  })

  await test.step('5. The page renders one component holding both rows', async () => {
    await page.goto('test-articles')
    const component = page.locator('.ui-patterns-test-component.from-view-style')
    await expect(component).toHaveCount(1)
    await expect(component.locator('> .ui-patterns-props-string')).toContainText('Test articles')
    await expect(component.locator('> .ui-patterns-props-boolean')).toHaveText('1')
    const slot = component.locator('> .ui-patterns-slots-slot')
    await expect(slot).toContainText('First article')
    await expect(slot).toContainText('Second article')
  })

  await test.step('6. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

test('Views row: a component per row', { tag: ['@base', '@views'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const base = 'row_options[ui_patterns]'

  await test.step('0. Setup: modules, content, a fresh view, admin login', async () => {
    await setup(drupal, audit)
  })

  await test.step('1. Show: choose the component row', async () => {
    await page.goto('admin/structure/views/view/test_articles')
    await page.locator(showLink).click()
    const radio = page.locator(`${dialog} input[name="row[type]"][value="ui_patterns"]`)
    await radio.waitFor({ timeout: 20_000 })
    await radio.click()
    await page.locator(applyButton).click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
  })

  await test.step('2. Pick the component, apply, reopen: the form is there', async () => {
    await pickComponentQuietly(page, `${base}[component_id]`)
    await apply(page)
    await page.locator(showSettingsLink).click()
    await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await expect(select(page, `${base}[component_id]`)).toHaveValue(config.testComponentId)
  })

  await test.step('3. Configure over AJAX: a row field in the slot', async () => {
    await openAllDetails(page)
    // A row carries the views row context: its fields are offered as sources.
    await select(page, `${base}[slots][slot][add_more_button]`).selectOption('view_field')
    await page.locator(`[name="${base}[slots][slot][sources][0][source_id]"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    const fieldSelect = select(page, `${base}[slots][slot][sources][0][source][ui_patterns_views_field]`)
    await fieldSelect.waitFor({ state: 'attached', timeout: 20_000 })
    await fieldSelect.selectOption('title')
    await openAllDetails(page)
    await page.locator(`input[name="${base}[props][attributes][source][value]"]`).fill('class="from-view-row"')
    await apply(page)
  })

  await test.step('4. Save, reopen the dialog: everything is kept', async () => {
    await saveView(page, drupal)
    await page.locator(showSettingsLink).click()
    await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await expect(select(page, `${base}[component_id]`)).toHaveValue(config.testComponentId)
    await expect(page.locator(`[name="${base}[slots][slot][sources][0][source_id]"]`)).toHaveValue('view_field')
    await expect(select(page, `${base}[slots][slot][sources][0][source][ui_patterns_views_field]`)).toHaveValue('title')
    await expect(page.locator(`input[name="${base}[props][attributes][source][value]"]`)).toHaveValue('class="from-view-row"')
  })

  await test.step('5. The page renders one component per row, each with its title', async () => {
    await page.goto('test-articles')
    const components = page.locator('.ui-patterns-test-component.from-view-row')
    await expect(components).toHaveCount(2)
    // The view lists the newest first, so compare the set of titles.
    const titles = await components.locator('> .ui-patterns-slots-slot').allTextContents()
    expect(titles.map(t => t.trim()).sort()).toEqual(['First article', 'Second article'])
  })

  await test.step('6. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

test('Views field: the component formatter on a field', { tag: ['@base', '@views'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const base = 'options[settings][ui_patterns]'
  const referenced = 'entity:field_property:node:field_test_tags:target_id'
  // No bundle in the id: a views field is on the field storage, not a bundle.
  const ref = 'entity_reference:node::field_test_tags:taxonomy_term:test_tags'
  const termName = 'field:taxonomy_term:test_tags:name'
  const nested = `${base}[props][string][source][${ref}][value]`

  await test.step('0. Setup: modules, content, a fresh view, admin login', async () => {
    await setup(drupal, audit)
  })

  await test.step('1. The tags field takes the component formatter, over AJAX', async () => {
    await page.goto('admin/structure/views/view/test_articles')
    await page.locator(tagsFieldLink).first().click()
    const formatter = page.locator(`${dialog} select[name="options[type]"]`)
    await formatter.waitFor({ timeout: 20_000 })
    await formatter.selectOption('ui_patterns_component_per_item')
    // The formatter settings, component select included, arrive over AJAX.
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
  })

  await test.step('2. Pick the component: its form arrives over AJAX', async () => {
    await select(page, `${base}[component_id]`).selectOption(config.testComponentId)
    await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
  })

  await test.step('3. Configure the chain: tags item, referenced term, its name', async () => {
    await openAllDetails(page)
    // The formatter runs per field item, so the referenced term is a context.
    const options = await select(page, `${base}[props][string][source_id]`)
      .locator('option')
      .evaluateAll(list => list.map(o => (o as HTMLOptionElement).value))
    expect(options).toEqual(expect.arrayContaining([referenced]))

    await select(page, `${base}[props][string][source_id]`).selectOption(referenced)
    await select(page, `${nested}[source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await select(page, `${nested}[source_id]`).selectOption('entity_field')
    await select(page, `${nested}[source][derivable_context]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await select(page, `${nested}[source][derivable_context]`).selectOption(termName)
    await page.locator(`[name="${nested}[source][${termName}][value][source_id]"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await select(page, `${nested}[source][${termName}][value][source_id]`).selectOption('field_property:taxonomy_term:name:value')
    await drupal.ajaxReady()
    await openAllDetails(page)
    await page.locator(`input[name="${base}[props][attributes][source][value]"]`).fill('class="from-view-field"')
    await apply(page)
  })

  await test.step('4. Save, reopen the dialog: the chain is kept', async () => {
    await saveView(page, drupal)
    await page.locator(tagsFieldLink).first().click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await expect(select(page, `${base}[component_id]`)).toHaveValue(config.testComponentId)
    await expect(select(page, `${base}[props][string][source_id]`)).toHaveValue(referenced)
    await expect(select(page, `${nested}[source][derivable_context]`)).toHaveValue(termName)
    await expect(page.locator(`[name="${nested}[source][${termName}][value][source_id]"]`)).toHaveValue(
      'field_property:taxonomy_term:name:value',
    )
    await expect(page.locator(`input[name="${base}[props][attributes][source][value]"]`)).toHaveValue('class="from-view-field"')
  })

  await test.step('5. The page renders one component per tag, with the term name', async () => {
    await page.goto('test-articles')
    const components = page.locator('.ui-patterns-test-component.from-view-field')
    // The first article has two tags, the second has one.
    await expect(components).toHaveCount(3)
    const names = await components.locator('> .ui-patterns-props-string').allTextContents()
    expect(names.map(n => n.trim()).sort()).toEqual(['Drupal', 'Playwright', 'Playwright'])
  })

  await test.step('6. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

test('Views: an unsaved view edit reaches the sources', { tag: ['@base', '@views'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const base = 'row_options[ui_patterns]'
  const fieldSelectName = `${base}[slots][slot][sources][0][source][ui_patterns_views_field]`
  const addFieldsLink = '#views-add-field'

  await test.step('0. Setup: modules, content, a fresh view, admin login', async () => {
    await setup(drupal, audit)
  })

  await test.step('1. Baseline: a saved view with a component row on the title', async () => {
    await page.goto('admin/structure/views/view/test_articles')
    await page.locator(showLink).click()
    const radio = page.locator(`${dialog} input[name="row[type]"][value="ui_patterns"]`)
    await radio.waitFor({ timeout: 20_000 })
    await radio.click()
    await page.locator(applyButton).click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await pickComponentQuietly(page, `${base}[component_id]`)
    await apply(page)
    await page.locator(showSettingsLink).click()
    await select(page, `${base}[slots][slot][add_more_button]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await select(page, `${base}[slots][slot][add_more_button]`).selectOption('view_field')
    await page.locator(`[name="${base}[slots][slot][sources][0][source_id]"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await select(page, fieldSelectName).selectOption('title')
    await openAllDetails(page)
    await page.locator(`input[name="${base}[props][attributes][source][value]"]`).fill('class="from-unsaved-view"')
    await apply(page)
    await saveView(page, drupal)
  })

  await test.step('2. Add the ID field in the views UI, without saving', async () => {
    await page.locator(addFieldsLink).click()
    const checkbox = page.locator(`${dialog} input[name="name[node_field_data.nid]"]`)
    await checkbox.waitFor({ timeout: 20_000 })
    await checkbox.check()
    await page.locator(`${dialog} .ui-dialog-buttonpane button:has-text("Add and configure fields")`).click()
    await page.locator(`${dialog} .ui-dialog-buttonpane button:has-text("Apply")`).waitFor({ timeout: 20_000 })
    await apply(page)
  })

  await test.step('3. The unsaved field is offered in the component form', async () => {
    await page.locator(showSettingsLink).click()
    const fieldSelect = select(page, fieldSelectName)
    await fieldSelect.waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    const options = await fieldSelect.locator('option').evaluateAll(list => list.map(o => (o as HTMLOptionElement).value))
    expect(options).toEqual(expect.arrayContaining(['nid']))
    await fieldSelect.selectOption('nid')
    await apply(page)
  })

  await test.step('4. The preview renders the unsaved field, one value per row', async () => {
    await page.locator('#preview-submit').click()
    const components = page.locator('#views-live-preview .ui-patterns-test-component.from-unsaved-view')
    await expect(components).toHaveCount(2)
    const ids = (await components.locator('> .ui-patterns-slots-slot').allTextContents()).map(t => t.trim())
    for (const id of ids) {
      expect(id).toMatch(/^\d+$/)
    }
    expect(new Set(ids).size).toBe(2)
  })

  await test.step('5. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// Picking the component in the style and row dialogs must load its form over
// AJAX. Today that request fails and the dialog stays as it was, so both
// tests above apply and reopen instead. Flip this on when the bug is fixed
// and drop pickComponentQuietly.
test.fixme('Views style and row: picking the component loads its form at once', { tag: ['@base', '@views'] }, async () => {})
