import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import { assertSlotAddRemove, openAllDetails, pick, select, value } from '../objects/ComponentForm'
import { enableLayoutBuilder, resetArticleDisplay, seedArticles } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'
import type { Drupal } from '../objects/Drupal'
import type { Page } from '@playwright/test'

// Contexts through layout builder: a component layout as section, an entity
// component block inside its slot, both fed by the layout builder entity.
// Not covered by PHPUnit: the forms. Entity fields offered and mapped over
// AJAX, a field formatter inside a slot, checkbox and attributes props, then
// save, reopen, check all of it is kept, and the article page.
//
// The forms are opened by their URL, not through the off-canvas dialog, so
// the runs stay deterministic. The forms are the same.

const storage = 'defaults/node.test_article.default'
const layout = 'ui_patterns:ui_patterns_test:test_component'
const block = 'ui_patterns_entity:ui_patterns_test:test-component'
const section = 'layout_settings[ui_patterns]'
const settings = 'settings[ui_patterns]'
const title = 'field:node:test_article:title'
const summary = 'field:node:test_article:field_test_summary'
const tags = 'field:node:test_article:field_test_tags'
const tagsFormatter = 'field_formatter:node:test_article:field_test_tags'

/** Maps a string prop to a field property of the layout builder entity. */
async function mapStringToField(page: Page, base: string, field: string, property: string, drupal: Drupal): Promise<void> {
  await pick(page, `${base}[props][string][source_id]`, 'entity_field', `${base}[props][string][source][derivable_context]`)
  await pick(page, `${base}[props][string][source][derivable_context]`, field, `${base}[props][string][source][${field}][value][source_id]`)
  await select(page, `${base}[props][string][source][${field}][value][source_id]`).selectOption(property)
  await drupal.ajaxReady()
}

test('Layout builder: entity context in a section and a block', { tag: ['@base', '@layout_builder'] }, async ({ page, drupal }) => {
  // The forms only: the layout page and the article render in the site theme,
  // whose own scripts are not under test.
  const audit = new Audit(page, drupal, url => url.includes('/layout_builder/'))
  let nodeId = ''

  await test.step('0. Setup: modules, content, layout builder on, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetArticleDisplay(drupal)
    await enableLayoutBuilder(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Section: the component layout maps its string prop to the title', async () => {
    await page.goto(`layout_builder/configure/section/${storage}/0/${layout}`)
    await mapStringToField(page, section, title, 'field_property:node:title:value', drupal)
    await page.locator(`input[name="${section}[props][boolean][source][value]"]`).check()
    await page.locator(`input[name="${section}[props][attributes][source][value]"]`).fill('class="from-layout"')
    await page.getByRole('button', { name: 'Add section' }).click()
    await page.getByRole('link', { name: 'Configure Section 1' }).waitFor({ timeout: 20_000 })
  })

  await test.step('2. Block in the slot: summary in the string prop, tags formatter in the slot', async () => {
    await page.goto(`layout_builder/add/block/${storage}/0/slot/${block}`)
    await mapStringToField(page, settings, summary, 'field_property:node:field_test_summary:value', drupal)

    const slot = `${settings}[slots][slot]`
    await pick(page, `${slot}[add_more_button]`, 'entity_field', `${slot}[sources][0][source][derivable_context]`)
    const inField = `${slot}[sources][0][source][${tags}][value]`
    await pick(page, `${slot}[sources][0][source][derivable_context]`, tags, `${inField}[add_more_button]`)
    await pick(page, `${inField}[add_more_button]`, tagsFormatter, `${inField}[sources][0][source][type]`)
    // The formatter select rebuilds its settings over AJAX; the choice must
    // survive that rebuild before the settings are touched.
    await select(page, `${inField}[sources][0][source][type]`).selectOption('entity_reference_label')
    await drupal.ajaxReady()
    await openAllDetails(page)
    await expect(select(page, `${inField}[sources][0][source][type]`)).toHaveValue('entity_reference_label')
    await expect(page.locator(`input[name="${inField}[sources][0][source][settings][link]"]`)).toBeChecked()
    await page.getByRole('button', { name: 'Add block' }).click()
    await page.getByRole('link', { name: 'Configure Section 1' }).waitFor({ timeout: 20_000 })
  })

  await test.step('3. Delete: add a second slot source in the block, then remove it', async () => {
    // Net zero, so the persistence checks below still see the one slot source.
    await page.goto('admin/structure/types/manage/test_article/display/default/layout')
    const href = await page.locator(`a[href*="/layout_builder/update/block/${storage}/0/slot/"]`).first().getAttribute('href')
    await page.goto(href!.split('?')[0])
    await assertSlotAddRemove(page, `${settings}[slots][slot]`, 'foo')
    await page.getByRole('button', { name: 'Update' }).click()
    await page.getByRole('link', { name: 'Configure Section 1' }).waitFor({ timeout: 20_000 })
  })

  await test.step('4. Save the layout', async () => {
    await page.getByRole('button', { name: 'Save layout' }).click()
    await drupal.expectMessage('The layout has been saved.')
  })

  await test.step('5. Reopen the section and the block: everything is kept', async () => {
    await page.goto(`layout_builder/configure/section/${storage}/0`)
    await openAllDetails(page)
    await expect(select(page, `${section}[props][string][source_id]`)).toHaveValue('entity_field')
    await expect(select(page, `${section}[props][string][source][derivable_context]`)).toHaveValue(title)
    await expect(value(page, `${section}[props][string][source][${title}][value][source_id]`)).toHaveValue('field_property:node:title:value')
    await expect(page.locator(`input[name="${section}[props][boolean][source][value]"]`)).toBeChecked()
    await expect(page.locator(`input[name="${section}[props][attributes][source][value]"]`)).toHaveValue('class="from-layout"')

    await page.goto('admin/structure/types/manage/test_article/display/default/layout')
    const href = await page.locator(`a[href*="/layout_builder/update/block/${storage}/0/slot/"]`).first().getAttribute('href')
    await page.goto(href!.split('?')[0])
    await openAllDetails(page)
    await expect(select(page, `${settings}[props][string][source_id]`)).toHaveValue('entity_field')
    await expect(select(page, `${settings}[props][string][source][derivable_context]`)).toHaveValue(summary)
    await expect(value(page, `${settings}[props][string][source][${summary}][value][source_id]`)).toHaveValue(
      'field_property:node:field_test_summary:value',
    )
    const inField = `${settings}[slots][slot][sources][0][source][${tags}][value]`
    await expect(select(page, `${settings}[slots][slot][sources][0][source][derivable_context]`)).toHaveValue(tags)
    await expect(select(page, `${inField}[sources][0][source][type]`)).toHaveValue('entity_reference_label')
    await expect(page.locator(`input[name="${inField}[sources][0][source][settings][link]"]`)).toBeChecked()
  })

  await test.step('6. The article renders the layout, the block and the linked tags', async () => {
    await page.goto(`node/${nodeId}`)
    const layoutComponent = page.locator('.ui-patterns-test-component.from-layout')
    await expect(layoutComponent).toHaveCount(1)
    await expect(layoutComponent.locator('> .ui-patterns-props-string')).toHaveText('First article')
    await expect(layoutComponent.locator('> .ui-patterns-props-boolean')).toHaveText('1')
    const blockComponent = layoutComponent.locator('> .ui-patterns-slots-slot .ui-patterns-test-component')
    await expect(blockComponent).toHaveCount(1)
    await expect(blockComponent.locator('> .ui-patterns-props-string')).toHaveText('Summary of the first article')
    const blockSlot = blockComponent.locator('> .ui-patterns-slots-slot')
    await expect(blockSlot.locator('a')).toHaveText(['Drupal', 'Playwright'])
  })

  await test.step('7. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})
