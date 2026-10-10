import { expect, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { select, slotAddButton, value } from '../objects/ComponentForm'
import { resetArticleDisplay, seedArticles } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'

// The whole selector without JavaScript: every button is a real submit and
// each click is a full POST rebuild. The pencil opens the row inline, Save
// folds it back, the plus adds an open row, Cancel drops it, and the typed
// values survive every round trip.

test.use({ javaScriptEnabled: false })

const field = 'field_test_tags'
const base = `fields[${field}][settings_edit_form][settings][ui_patterns]`
const stringProp = `${base}[props][string]`
const slotName = `${base}[slots][slot]`

/** Clicks closed summaries until every details is open; no JS to help. */
async function unfoldAll(page: Page): Promise<void> {
  // One click per pass: each unfolds a box, which may reveal more.
  for (let pass = 0; pass < 40; pass++) {
    const closed = page.locator('details:not([open]) > summary')
    if ((await closed.count()) === 0) {
      return
    }
    await closed.first().click()
  }
}

test('No JavaScript: the whole selector works inline', { tag: ['@base', '@nojs'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let nodeId = ''

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Pick the formatter and the component, full POSTs only', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default')
    await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_component_per_item')
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Your settings have been saved.')).toBeVisible()

    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await select(page, `${base}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await select(page, `${base}[component_id]`).selectOption(config.testComponentId)
    // No AJAX: the update round trip loads the component form.
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await value(page, `${stringProp}[node_id]`).first().waitFor({ state: 'attached', timeout: 20_000 })
  })

  await test.step('2. The pencil opens the row inline; the typed value survives', async () => {
    await unfoldAll(page)
    await page.locator(`input[name="${stringProp}[source][value]"]`).fill('no-js-value')
    await page
      .locator(`[id*="ui-patterns-prop-row"]:has([name="${stringProp}[node_id]"])`)
      .last()
      .locator('.uip-source-selector-toggle--open')
      .click()
    // The rebuilt page unfolds the box of the row it opened, on its own.
    await expect(
      page.locator(`[id*="ui-patterns-prop-row"]:has([name="${stringProp}[node_id]"]) details.uip-prop-box`).last(),
    ).toHaveAttribute('open', 'open')
    await unfoldAll(page)
    // Open inline: the selector select, Save and Cancel.
    await expect(select(page, `${stringProp}[source_id]`)).toBeVisible()
    await expect(page.locator('.uip-source-selector-toggle--close').first()).toBeVisible()
    await expect(page.locator('.uip-source-selector-toggle--cancel').first()).toBeVisible()
    await expect(page.locator(`input[name="${stringProp}[source][value]"]`)).toHaveValue('no-js-value')

    await page.locator('.uip-source-selector-toggle--close').first().click()
    await unfoldAll(page)
    await expect(select(page, `${stringProp}[source_id]`)).toHaveCount(0)
    await expect(page.locator(`input[name="${stringProp}[source][value]"]`)).toHaveValue('no-js-value')
  })

  await test.step('3. The plus adds an open row; Cancel drops it', async () => {
    // Every POST folds the boxes back: the add button sits in the slot one.
    await unfoldAll(page)
    await slotAddButton(page, slotName).click()
    await unfoldAll(page)
    await expect(select(page, `${slotName}[sources][0][source_id]`)).toBeVisible()
    await page.locator('.uip-source-selector-toggle--cancel').first().click()
    await unfoldAll(page)
    await expect(page.locator(`[name^="${slotName}[sources]["][name$="[node_id]"]`)).toHaveCount(0)
  })

  await test.step('4. Save, render: the value reaches the node', async () => {
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('Your settings have been saved.')).toBeVisible()
    await page.goto(`node/${nodeId}`)
    // By its text: a block placed by another spec renders the same component.
    await expect(page.locator('.ui-patterns-test-component .ui-patterns-props-string', { hasText: 'no-js-value' }).first()).toBeVisible()
  })

  await test.step('5. No PHP error in watchdog', async () => {
    await audit.expectClean()
  })
})
