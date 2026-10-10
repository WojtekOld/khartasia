import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { addSlotSource, openAllDetails, select, slotAddButton, sourceRow, value } from '../objects/ComponentForm'
import { enableLayoutBuilder, resetArticleDisplay, seedArticles } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'

// The source selector in place: a row opens inside the form it belongs to,
// in its own box. Every control is pressed with the keyboard here, because
// an AJAX refresh replaces the very button that was pressed: what these
// tests pin is where the focus lands afterwards, and which box is unfolded.

const field = 'field_test_tags'
const base = `fields[${field}][settings_edit_form][settings][ui_patterns]`
const prop = `${base}[props][boolean]`
const stringProp = `${base}[props][string]`
const slotName = `${base}[slots][slot]`
const statusContext = 'field:node:test_article:status'

/** The details box a row folds into. */
function box(row: Locator): Locator {
  return row.locator('details.uip-prop-box').first()
}

/** Manage display page: the per-item component formatter on the tags field. */
async function openFormatterForm(page: Page): Promise<void> {
  await page.goto('admin/structure/types/manage/test_article/display/default')
  await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_component_per_item')
  await page.getByText('No component selected.').waitFor({ timeout: 20_000 })
  await page.locator(`input[name="${field}_settings_edit"]`).click()
  await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
  await select(page, `${base}[component_id]`).selectOption(config.testComponentId)
  await value(page, `${prop}[node_id]`).first().waitFor({ state: 'attached', timeout: 20_000 })
  await openAllDetails(page)
}

test('Source selector: a boolean prop from an entity field, end to end', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  // Admin pages only: the article page runs the site theme's own scripts.
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let nodeId = ''
  const row = () => sourceRow(page, `${prop}[source_id]`)

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Enter on the button opens the row, on the selector', async () => {
    await openFormatterForm(page)
    await row().locator('.uip-source-selector-toggle--open').press('Enter')
    const selector = select(page, `${prop}[source_id]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await expect(selector).toBeFocused()
    await expect(box(row())).toHaveJSProperty('open', true)
    // In the form: what is typed in the row is submitted with it.
    await expect(page.locator(`form select[name="${prop}[source_id]"]`)).toHaveCount(1)
  })

  await test.step('2. In the row: entity field, the status field, its value', async () => {
    await select(page, `${prop}[source_id]`).selectOption('entity_field')
    const context = select(page, `${prop}[source][derivable_context]`)
    await context.waitFor({ state: 'attached', timeout: 20_000 })
    await context.selectOption(statusContext)
    // The embedded prop form opens straight on its selector.
    const property = select(page, `${prop}[source][${statusContext}][value][source_id]`)
    await property.waitFor({ state: 'attached', timeout: 20_000 })
    await property.selectOption('field_property:node:status:value')
    await drupal.ajaxReady()
    await openAllDetails(page)
  })

  await test.step('3. Enter on Save folds the row, focus back on its button', async () => {
    await row().locator('.uip-source-selector-toggle--close').press('Enter')
    const configure = row().locator('.uip-source-selector-toggle--open')
    await expect(configure).toBeVisible({ timeout: 20_000 })
    await expect(configure).toBeFocused()
    // The box holding the result stays unfolded.
    await expect(box(row())).toHaveJSProperty('open', true)
    // The summary hints the picked chain, not just the source name.
    await expect(row().locator('.uip-source-summary')).toContainText('Boolean value')
    await expect(value(page, `${prop}[source_id]`).first()).toHaveValue('entity_field')
  })

  await test.step('4. Save, render: the published node shows the boolean', async () => {
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
    await page.goto(`node/${nodeId}`)
    await expect(page.locator('.ui-patterns-test-component .ui-patterns-props-boolean').first()).toHaveText('1')
  })

  await test.step('5. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// A slot row: the add button appends a row arriving open, the source is
// picked in it, and Save folds it into a box titled by that source. The
// remove button stays out of the box, reachable with the box folded.
test('Source selector: a slot source, added open, closed to a summary', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let nodeId = ''
  const row = () => page.locator(`.uip-slot-row:has([name="${slotName}[sources][0][node_id]"])`)

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Enter on the add button: the new row arrives open', async () => {
    await openFormatterForm(page)
    await slotAddButton(page, slotName).press('Enter')
    const selector = select(page, `${slotName}[sources][0][source_id]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await expect(selector).toBeFocused()
    await expect(box(row())).toHaveJSProperty('open', true)
  })

  await test.step('2. In the row: a nested component', async () => {
    const selector = select(page, `${slotName}[sources][0][source_id]`)
    await selector.selectOption('component')
    await expect(selector).toBeEnabled({ timeout: 20_000 })
    const nested = select(page, `${slotName}[sources][0][source][component][component_id]`)
    await nested.waitFor({ state: 'attached', timeout: 20_000 })
    await nested.selectOption(config.testComponentId)
    await drupal.ajaxReady()
  })

  await test.step('3. Save: a box titled by the source, the bin beside it', async () => {
    await row().locator('.uip-source-selector-toggle--close').first().press('Enter')
    // The folded source form holds buttons of its own, hidden: ours shows.
    const configure = row().locator('.uip-source-selector-toggle--open:visible').first()
    await expect(configure).toBeVisible({ timeout: 20_000 })
    await expect(configure).toBeFocused()
    await expect(box(row()).locator('summary').first()).toContainText('Component')
    // The bin is not in the box: a folded row can still be removed.
    await expect(row().locator('.uip-slot-remove')).toBeVisible()
    await expect(box(row()).locator('.uip-slot-remove')).toHaveCount(0)
    // The table owns the weight: the refreshed row renders no second one.
    await expect(page.locator(`[name="${slotName}[sources][0][_weight]"]`)).toHaveCount(1)
    await openAllDetails(page)
    // The hidden nested form holds summaries of its own: the visible one.
    await expect(row().locator('.uip-source-summary:visible').first()).toBeVisible()
    await expect(value(page, `${slotName}[sources][0][source_id]`).first()).toHaveValue('component')
  })

  await test.step('4. The button reopens the same row on its selector', async () => {
    await row().locator('.uip-source-selector-toggle--open:visible').first().press('Enter')
    const selector = select(page, `${slotName}[sources][0][source_id]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await expect(selector).toBeFocused()
    await row().locator('.uip-source-selector-toggle--close').first().press('Enter')
    await expect(select(page, `${slotName}[sources][0][source_id]`)).toHaveCount(0, { timeout: 20_000 })
  })

  await test.step('5. Save, render: the nested component fills the slot', async () => {
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
    await page.goto(`node/${nodeId}`)
    await expect(page.locator('.ui-patterns-test-component .ui-patterns-slots-slot .ui-patterns-test-component').first()).toBeAttached()
  })

  await test.step('6. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// Every control of an open row: Save keeps the typed values, Cancel puts
// back the ones the row opened with, a cancelled fresh slot row disappears,
// the bin removes a configured one, and two rows stay open at once.
test('Source selector: cancel, the bin, two rows open at once', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const stringValue = page.locator(`input[name="${stringProp}[source][value]"]`)
  const propRow = (name: string) => sourceRow(page, `${name}[source_id]`)

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Save keeps the typed value', async () => {
    await openFormatterForm(page)
    await propRow(stringProp).locator('.uip-source-selector-toggle--open').press('Enter')
    await expect(select(page, `${stringProp}[source_id]`)).toBeVisible({ timeout: 20_000 })
    await stringValue.fill('kept-value')
    await propRow(stringProp).locator('.uip-source-selector-toggle--close').press('Enter')
    await expect(select(page, `${stringProp}[source_id]`)).toHaveCount(0, { timeout: 20_000 })
    await expect(stringValue).toHaveValue('kept-value')
    // The live region a screen reader follows; hidden from sight.
    await expect(page.locator('#drupal-live-announce')).toContainText('String: values kept.')
  })

  await test.step('2. Cancel restores the value the row opened with', async () => {
    await propRow(stringProp).locator('.uip-source-selector-toggle--open').press('Enter')
    await expect(select(page, `${stringProp}[source_id]`)).toBeVisible({ timeout: 20_000 })
    await stringValue.fill('abandoned-value')
    await propRow(stringProp).locator('.uip-source-selector-toggle--cancel').press('Enter')
    const configure = propRow(stringProp).locator('.uip-source-selector-toggle--open')
    await expect(configure).toBeVisible({ timeout: 20_000 })
    await expect(configure).toBeFocused()
    await expect(stringValue).toHaveValue('kept-value')
    await expect(page.locator('#drupal-live-announce')).toContainText('String: values put back.')
  })

  await test.step('3. Two rows open at once, each editing its own prop', async () => {
    await openAllDetails(page)
    await propRow(stringProp).locator('.uip-source-selector-toggle--open').press('Enter')
    await expect(select(page, `${stringProp}[source_id]`)).toBeVisible({ timeout: 20_000 })
    await propRow(prop).locator('.uip-source-selector-toggle--open').press('Enter')
    await expect(select(page, `${prop}[source_id]`)).toBeVisible({ timeout: 20_000 })
    await expect(select(page, `${stringProp}[source_id]`)).toBeVisible()

    await stringValue.fill('kept-value-2')
    await propRow(prop).locator('.uip-source-selector-toggle--close').press('Enter')
    await expect(select(page, `${prop}[source_id]`)).toHaveCount(0, { timeout: 20_000 })
    await propRow(stringProp).locator('.uip-source-selector-toggle--close').press('Enter')
    await expect(select(page, `${stringProp}[source_id]`)).toHaveCount(0, { timeout: 20_000 })
    await expect(stringValue).toHaveValue('kept-value-2')
  })

  await test.step('4. A cancelled fresh slot row disappears, focus on add', async () => {
    await expect(page.locator(`[name^="${slotName}[sources]["][name$="[node_id]"]`)).toHaveCount(0)
    await slotAddButton(page, slotName).press('Enter')
    await expect(select(page, `${slotName}[sources][0][source_id]`)).toBeVisible({ timeout: 20_000 })
    await page.locator('.uip-slot-row .uip-source-selector-toggle--cancel').first().press('Enter')
    await expect(page.locator(`[name^="${slotName}[sources]["][name$="[node_id]"]`)).toHaveCount(0, { timeout: 20_000 })
    await expect(slotAddButton(page, slotName)).toBeFocused()
  })

  /** Adds a row, saves it, removes it with the bin: focus lands on add. */
  const addSaveRemove = async (source: string) => {
    await addSlotSource(page, slotName, source)
    await page.locator('.uip-slot-row .uip-source-selector-toggle--close').first().press('Enter')
    const slotRow = page.locator(`.uip-slot-row:has([name^="${slotName}[sources]["][name$="[node_id]"])`).last()
    await expect(slotRow.locator('.uip-slot-remove')).toBeVisible({ timeout: 20_000 })
    await slotRow.locator('.uip-slot-remove').press('Enter')
    await expect(page.locator(`[name^="${slotName}[sources]["][name$="[node_id]"]`)).toHaveCount(0, { timeout: 20_000 })
    await expect(slotAddButton(page, slotName)).toBeFocused()
  }

  await test.step('5. The bin removes a configured row, focus on add', async () => {
    await addSaveRemove('component')
    await expect(page.locator('#drupal-live-announce')).toContainText('Slot: content removed.')
  })

  await test.step('6. The bin with an error elsewhere still lands the focus', async () => {
    // A prop saved on an entity field with no property: the bin validates
    // the whole form, so no rebuild, and a folded box would hide the add.
    await openAllDetails(page)
    await propRow(prop).locator('.uip-source-selector-toggle--open').press('Enter')
    await expect(select(page, `${prop}[source_id]`)).toBeVisible({ timeout: 20_000 })
    await select(page, `${prop}[source_id]`).selectOption('entity_field')
    await select(page, `${prop}[source][derivable_context]`).waitFor({ state: 'attached', timeout: 20_000 })
    await propRow(prop).locator('.uip-source-selector-toggle--close').press('Enter')
    await expect(select(page, `${prop}[source_id]`)).toHaveCount(0, { timeout: 20_000 })
    await addSaveRemove('foo')
  })

  await test.step('7. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// A host following the form with the browser's own change events hears
// nothing from the server: a cancelled row puts values back, a source
// switch lands a form, and a jQuery-triggered event never reaches it. What
// is pinned here is the contract such a host depends on: the last value
// announced for a field is the value it holds, and a save, which keeps what
// the browser reported already, announces nothing.
test('Source selector: the page hears what the server changed, not what it saved', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const valueName = `${stringProp}[source][value]`
  const field = () => page.locator(`input[name="${valueName}"]`)
  const propRow = () => sourceRow(page, `${stringProp}[source_id]`)
  const selector = () => select(page, `${stringProp}[source_id]`)

  /**
   * A native listener, like a host's; it records the value the field holds.
   *
   * Trusted events, the browser's own on blur or pick, are left out: this
   * pins what the server announces.
   */
  const watchChanges = () =>
    page.evaluate(name => {
      const win = window as unknown as { uipChanges: string[] }
      win.uipChanges = []
      document.addEventListener(
        'change',
        event => {
          if (!event.isTrusted) {
            win.uipChanges.push((document.querySelector(`[name="${name}"]`) as HTMLInputElement | null)?.value ?? 'gone')
          }
        },
        true,
      )
    }, valueName)

  /** The value the form held when the server last announced a change. */
  const lastAnnounced = async (): Promise<string | null> => {
    const seen = await page.evaluate(() => (window as unknown as { uipChanges: string[] }).uipChanges)
    return seen.length ? seen[seen.length - 1] : null
  }

  const open = async () => {
    await propRow().locator('.uip-source-selector-toggle--open').click()
    await expect(selector()).toBeVisible({ timeout: 20_000 })
  }
  const fold = async (toggle: 'close' | 'cancel') => {
    await propRow().locator(`.uip-source-selector-toggle--${toggle}`).click()
    await expect(selector()).toHaveCount(0, { timeout: 20_000 })
  }

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Save announces nothing: the browser reported the typed value', async () => {
    await openFormatterForm(page)
    await open()
    await field().fill('kept-value')
    await watchChanges()
    await fold('close')
    await expect(field()).toHaveValue('kept-value')
    expect(await lastAnnounced()).toBeNull()
  })

  await test.step('2. Cancel announces the value it puts back', async () => {
    await open()
    await field().fill('abandoned-value')
    await watchChanges()
    await fold('cancel')
    await expect(field()).toHaveValue('kept-value')
    expect(await lastAnnounced()).toBe('kept-value')
  })

  await test.step('3. Cancel with nothing typed announces nothing', async () => {
    await open()
    await watchChanges()
    await fold('cancel')
    await expect(field()).toHaveValue('kept-value')
    expect(await lastAnnounced()).toBeNull()
  })

  await test.step('4. A source switch announces the form it lands', async () => {
    await open()
    await watchChanges()
    await selector().selectOption('token')
    // The browser reported the pick; the form that landed reports itself.
    await expect.poll(lastAnnounced, { timeout: 20_000 }).not.toBeNull()
    expect(await lastAnnounced()).toBe(await field().inputValue())
  })

  await test.step('5. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// Nested values: a component in the slot, holding a component in its own
// slot. Saving a nested row folds that row only, and the whole chain saves
// and renders.
test('Source selector: nested slots and props', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  const inner = `${slotName}[sources][0][source][component]`
  const innerSlot = `${inner}[slots][slot]`
  const innermost = `${innerSlot}[sources][0][source][component]`
  let nodeId = ''

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. A component in the slot, a component in its own slot', async () => {
    await openFormatterForm(page)
    await slotAddButton(page, slotName).press('Enter')
    const selector = select(page, `${slotName}[sources][0][source_id]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await selector.selectOption('component')
    await expect(selector).toBeEnabled({ timeout: 20_000 })
    const component = select(page, `${inner}[component_id]`)
    await component.waitFor({ state: 'attached', timeout: 20_000 })
    await component.selectOption(config.testComponentId)
    await expect(component).toBeEnabled({ timeout: 20_000 })

    // The nested slot adds its row in place, inside the open outer row.
    await addSlotSource(page, innerSlot, 'component', `${innermost}[component_id]`)
    await select(page, `${innermost}[component_id]`).selectOption(config.testComponentId)
    await expect(select(page, `${innermost}[component_id]`)).toBeEnabled({ timeout: 20_000 })

    // A prop at each level, the innermost through its own button.
    await openAllDetails(page)
    await page.locator(`input[name="${inner}[props][string][source][value]"]`).fill('nested-level-1')
    await page.locator(`input[name="${innermost}[props][string][source][value]"]`).fill('nested-level-2')
  })

  await test.step('2. Saving the nested row folds that row only', async () => {
    const nestedRow = page.locator(`.uip-slot-row:has([name="${innerSlot}[sources][0][node_id]"])`)
    await nestedRow.locator('.uip-source-selector-toggle--close').first().press('Enter')
    await expect(select(page, `${innerSlot}[sources][0][source_id]`)).toHaveCount(0, { timeout: 20_000 })
    // The outer row is still open around it.
    await expect(select(page, `${slotName}[sources][0][source_id]`)).toBeVisible()
    await page
      .locator(`.uip-slot-row:has([name="${slotName}[sources][0][node_id]"]) .uip-source-selector-toggle--close`)
      .first()
      .press('Enter')
    await expect(select(page, `${slotName}[sources][0][source_id]`)).toHaveCount(0, { timeout: 20_000 })
  })

  await test.step('3. A referenced entity value in the same slot, embedded flat', async () => {
    const refCtx = 'entity_reference:node:test_article:field_test_tags:taxonomy_term:test_tags'
    const outer2 = `${slotName}[sources][1]`
    const embedded = `${outer2}[source][${refCtx}][value]`
    const refComponent = `${embedded}[sources][0][source][component]`

    await slotAddButton(page, slotName).press('Enter')
    const selector = select(page, `${outer2}[source_id]`)
    await selector.waitFor({ state: 'attached', timeout: 20_000 })
    await selector.selectOption('entity_reference')
    await expect(selector).toBeEnabled({ timeout: 20_000 })
    const ctx = select(page, `${outer2}[source][derivable_context]`)
    await ctx.waitFor({ state: 'attached', timeout: 20_000 })
    await ctx.selectOption(refCtx)
    await expect(ctx).toBeEnabled({ timeout: 20_000 })

    await slotAddButton(page, embedded).waitFor({ state: 'attached', timeout: 20_000 })
    await addSlotSource(page, embedded, 'component', `${refComponent}[component_id]`)
    // Embedded like a prop: the derived row renders flat, with no box and
    // none of the state a box needs.
    await expect(page.locator(`[name="${embedded}[sources][0][_open]"]`)).toHaveCount(0)
    await expect(select(page, `${embedded}[sources][0][source_id]`)).toBeVisible()

    await select(page, `${refComponent}[component_id]`).selectOption(config.testComponentId)
    await expect(select(page, `${refComponent}[component_id]`)).toBeEnabled({ timeout: 20_000 })
    await openAllDetails(page)
    await page.locator(`input[name="${refComponent}[props][string][source][value]"]`).fill('nested-ref')
    await page.locator(`.uip-slot-row:has([name="${outer2}[node_id]"]) .uip-source-selector-toggle--close`).first().press('Enter')
    await expect(select(page, `${outer2}[source_id]`)).toHaveCount(0, { timeout: 20_000 })
  })

  await test.step('4. Save the formatter, render the whole chain', async () => {
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
    await page.goto(`node/${nodeId}`)
    // By its own text: blocks placed by other specs render the same chain.
    const outer = page
      .locator('.ui-patterns-test-component .ui-patterns-slots-slot .ui-patterns-test-component')
      .filter({ hasText: 'nested-level-1' })
      .first()
    await expect(outer.locator('> .ui-patterns-props-string')).toHaveText('nested-level-1')
    await expect(outer.locator('.ui-patterns-slots-slot .ui-patterns-test-component > .ui-patterns-props-string').first()).toHaveText('nested-level-2')
    // The component reached through the referenced entity renders too.
    await expect(page.locator('.ui-patterns-props-string', { hasText: 'nested-ref' }).first()).toBeAttached()
  })

  await test.step('5. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// A "Source selector" row of a component form display embeds the real prop
// form, so the button and the row behave exactly like the plain form. The
// display targets a component no other test configures, so the worker site
// stays clean for the rest of the suite even on failure.
test('Source selector: inside a ui_patterns_ui display', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  // Html::getId drops the dots of the display id.
  const displayBase = `${base}[display][value_ui-patterns-testtest-form-componentpanel]`
  const displayProp = `${displayBase}[string]`
  let nodeId = ''
  const storage = "\\Drupal::entityTypeManager()->getStorage('component_form_display')"

  await test.step('0. Setup: ui_patterns_ui on, a display with a selector row', async () => {
    await enableModules(drupal, { content: true })
    await drupal.drush('en -y ui_patterns_ui')
    await drupal.drush(`ev "${storage}->delete(${storage}->loadByProperties(['id' => 'ui_patterns_test.test-form-component.panel']));"`)
    await drupal.drush(
      `ev "${storage}->create(['label' => 'Panel', 'component_id' => 'ui_patterns_test:test-form-component', 'form_mode_name' => 'panel', 'status' => TRUE, 'content' => ['string' => ['region' => 'content', 'weight' => '0', 'parent' => '', 'widget_settings' => [], 'third_party_settings' => [], 'source_id' => '']], 'hidden' => ['base_id' => TRUE, 'slot' => TRUE]])->save();"`,
    )
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. The display shows the row closed; the button opens it', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default')
    await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_component_per_item')
    await page.getByText('No component selected.').waitFor({ timeout: 20_000 })
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await select(page, `${base}[component_id]`).selectOption('ui_patterns_test:test-form-component')
    await value(page, `${displayProp}[node_id]`).first().waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)

    await sourceRow(page, `${displayProp}[source_id]`).locator('.uip-source-selector-toggle--open').press('Enter')
    const selector = select(page, `${displayProp}[source_id]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await expect(selector).toBeFocused()
  })

  await test.step('2. Pick a source, save: the row keeps the value', async () => {
    const selector = select(page, `${displayProp}[source_id]`)
    await selector.selectOption('textfield')
    await expect(selector).toBeEnabled({ timeout: 20_000 })
    await page.locator(`input[name="${displayProp}[source][value]"]`).fill('from-display-row')
    await sourceRow(page, `${displayProp}[source_id]`).locator('.uip-source-selector-toggle--close').press('Enter')
    await expect(select(page, `${displayProp}[source_id]`)).toHaveCount(0, { timeout: 20_000 })
    await expect(page.locator(`input[name="${displayProp}[source][value]"]`)).toHaveValue('from-display-row')
  })

  await test.step('3. Save, render: the display-built value reaches the node', async () => {
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
    await page.goto(`node/${nodeId}`)
    await expect(page.locator('.ui-patterns-test-form input[id$="-input"]').first()).toHaveValue('from-display-row')
  })

  await test.step('4. Cleanup and audit', async () => {
    await drupal.drush(`ev "${storage}->delete(${storage}->loadByProperties(['id' => 'ui_patterns_test.test-form-component.panel']));"`)
    await audit.expectClean()
  })
})

// The button inside a layout builder FIELD BLOCK: the entity field formatter
// form nested in the block form. That host rebuilds its structure from the
// form-state values, so a toggle leaving partial values behind loses the
// whole formatter form and empties the row.
test('Source selector: inside a layout builder field block', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/layout_builder/'))

  await test.step('0. Setup: modules, content, layout builder on, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    await enableLayoutBuilder(drupal)
    await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. A field block with the component formatter', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default/layout')
    await page.getByRole('link', { name: 'Add block' }).first().click()
    const offCanvas = page.locator('#drupal-off-canvas')
    await offCanvas.waitFor({ timeout: 20_000 })
    await offCanvas.getByRole('link', { name: /Tags/ }).first().click()
    const formatter = offCanvas.locator('select[name*="[formatter][type]"]').first()
    await formatter.waitFor({ state: 'attached', timeout: 20_000 })
    await formatter.selectOption('ui_patterns_component_per_item')
    const component = offCanvas.locator('select[name*="[component_id]"]').first()
    await component.waitFor({ state: 'attached', timeout: 20_000 })
    await component.selectOption(config.testComponentId)
    await offCanvas.locator('[name*="[ui_patterns]"][name$="[node_id]"]').first().waitFor({ state: 'attached', timeout: 20_000 })
  })

  await test.step('2. The button opens the row; the row survives Save', async () => {
    const offCanvas = page.locator('#drupal-off-canvas')
    await openAllDetails(offCanvas)
    const row = offCanvas.locator('[id*="ui-patterns-prop-row"]:has([name*="[boolean]"])').first()
    await row.locator('.uip-source-selector-toggle--open').first().press('Enter')
    const selector = offCanvas.locator('select[name*="[boolean]"][name$="[source_id]"]')
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await expect(selector).toBeFocused()

    await row.locator('.uip-source-selector-toggle--close').first().press('Enter')
    await expect(selector).toHaveCount(0, { timeout: 20_000 })
    // The regression emptied the row here: the formatter form was lost.
    await expect(row.locator('.uip-source-selector-toggle--open')).toBeVisible()
  })

  await test.step('3. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

// The row inside the layout builder off-canvas, a dialog of its own with a
// style reset: the button must still open the row in place, usable, and the
// typed value must reach the saved layout.
test('Source selector: inside the layout builder off-canvas', { tag: ['@base', '@selector'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/layout_builder/'))
  const section = 'layout_settings[ui_patterns]'
  const sectionProp = `${section}[props][string]`
  let nodeId = ''

  await test.step('0. Setup: modules, content, layout builder on, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    await enableLayoutBuilder(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. The button opens the row inside the off-canvas', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default/layout')
    await page.getByRole('link', { name: 'Add section' }).first().click()
    const offCanvas = page.locator('#drupal-off-canvas')
    await offCanvas.waitFor({ timeout: 20_000 })
    await offCanvas.getByRole('link', { name: 'Test component' }).click()
    await offCanvas.locator(`[name="${sectionProp}[node_id]"]`).waitFor({ state: 'attached', timeout: 20_000 })

    // The off-canvas opens every box folded: the button lives in one.
    await openAllDetails(offCanvas)
    const row = offCanvas.locator(`[id*="ui-patterns-prop-row"]:has([name="${sectionProp}[node_id]"])`)
    await row.locator('.uip-source-selector-toggle--open').press('Enter')
    const selector = offCanvas.locator(`select[name="${sectionProp}[source_id]"]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    await expect(selector).toBeFocused()
  })

  await test.step('2. Type a value, save the row: the value stays', async () => {
    const offCanvas = page.locator('#drupal-off-canvas')
    await offCanvas.locator(`input[name="${sectionProp}[source][value]"]`).fill('from-inline-row')
    await offCanvas.locator('.uip-source-selector-toggle--close').first().press('Enter')
    await expect(offCanvas.locator(`select[name="${sectionProp}[source_id]"]`)).toHaveCount(0, { timeout: 20_000 })
    await expect(offCanvas.locator(`input[name="${sectionProp}[source][value]"]`)).toHaveValue('from-inline-row')
    await openAllDetails(offCanvas)
    await offCanvas.locator(`input[name="${section}[props][attributes][source][value]"]`).fill('class="from-inline-test"')
  })

  await test.step('3. Escape cancels the row, the off-canvas stays', async () => {
    const offCanvas = page.locator('#drupal-off-canvas')
    const row = offCanvas.locator(`[id*="ui-patterns-prop-row"]:has([name="${sectionProp}[node_id]"])`)
    await row.locator('.uip-source-selector-toggle--open').press('Enter')
    const selector = offCanvas.locator(`select[name="${sectionProp}[source_id]"]`)
    await expect(selector).toBeVisible({ timeout: 20_000 })
    const field = offCanvas.locator(`input[name="${sectionProp}[source][value]"]`)
    await field.fill('abandoned')
    // The off-canvas is a dialog of its own, closing on Escape: ours first.
    await field.press('Escape')
    await expect(selector).toHaveCount(0, { timeout: 20_000 })
    await expect(offCanvas).toBeVisible()
    await expect(field).toHaveValue('from-inline-row')
    await expect(row.locator('.uip-source-selector-toggle--open')).toBeFocused()
  })

  await test.step('4. Add the section, save, the article shows the value', async () => {
    await page.getByRole('button', { name: 'Add section' }).click()
    await page.getByRole('link', { name: 'Configure Section 2' }).waitFor({ timeout: 20_000 })
    await page.getByRole('button', { name: 'Save layout' }).click()
    await drupal.expectMessage('The layout has been saved.')
    await page.goto(`node/${nodeId}`)
    // Not the first component of the page: a block placed by another test
    // can render one before the section.
    const strings = page.locator('.ui-patterns-test-component .ui-patterns-props-string')
    await expect(strings.filter({ hasText: 'from-inline-row' })).toHaveCount(1)
  })

  await test.step('5. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})
