import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { assertSlotAddRemove, openAllDetails, pick, select } from '../objects/ComponentForm'
import { resetArticleDisplay, seedArticles } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'

// Contexts through the field formatter plugin, on Manage display of a
// content type. Not covered by PHPUnit: the forms. Which sources a plugin
// type offers given its contexts, the chain "field item, referenced term,
// term field, property" built over AJAX, the nested component getting the
// same contexts, and the saved display rendering on a real node.
//
// Content: ui_patterns_test_content (test_article with tags and a related
// article, test_tags terms with a color). Seeded here, over drush.

const field = 'field_test_tags'
const base = `fields[${field}][settings_edit_form][settings][ui_patterns]`
// Derivable context id of "the term referenced by this tags item".
const ref = 'entity_reference:node:test_article:field_test_tags:taxonomy_term:test_tags'
const termName = 'field:taxonomy_term:test_tags:name'
const termColor = 'field:taxonomy_term:test_tags:field_test_color'
const referenced = 'entity:field_property:node:field_test_tags:target_id'

test('Field formatter: contexts and referenced entities', { tag: ['@base', '@field_formatter'] }, async ({ page, drupal }) => {
  // Admin pages only: the article page runs the site theme's own scripts.
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let nodeId = ''

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    // Runs on a reused site must start from the same state.
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Manage display: component per item on the tags field', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default')
    await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_component_per_item')
    // The row is replaced over AJAX: wait for the new formatter's summary
    // before clicking its gear.
    await page.getByText('No component selected.').waitFor({ timeout: 20_000 })
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await pick(page, `${base}[component_id]`, config.testComponentId, `${base}[props][string][source_id]`)

    // The sources offered depend on the contexts the formatter injects:
    // the field item, its entity, the referenced entity.
    const options = await select(page, `${base}[props][string][source_id]`)
      .locator('option')
      .evaluateAll(list => list.map(o => (o as HTMLOptionElement).value))
    expect(options).toEqual(expect.arrayContaining([referenced, 'entity_field', `field_property:node:${field}:target_id`]))
    // The props arrived over AJAX: a boolean prop with a default gets it.
    await expect(page.locator(`input[name="${base}[props][boolean_with_default_true][source][value]"]`)).toBeChecked()
  })

  await test.step('2. String prop: tags item, referenced term, its name', async () => {
    const nested = `${base}[props][string][source][${ref}][value]`
    await pick(page, `${base}[props][string][source_id]`, referenced, `${nested}[source_id]`)
    await pick(page, `${nested}[source_id]`, 'entity_field', `${nested}[source][derivable_context]`)
    await pick(page, `${nested}[source][derivable_context]`, termName, `${nested}[source][${termName}][value][source_id]`)
    await select(page, `${nested}[source][${termName}][value][source_id]`).selectOption('field_property:taxonomy_term:name:value')

    // Switching the term field replaces the nested form; switching back
    // gives an empty one, the old choice does not come back from the input.
    await pick(page, `${nested}[source][derivable_context]`, termColor, `${nested}[source][${termColor}][value][source_id]`)
    await expect(select(page, `${nested}[source][${termName}][value][source_id]`)).toHaveCount(0)
    await pick(page, `${nested}[source][derivable_context]`, termName, `${nested}[source][${termName}][value][source_id]`)
    await expect(select(page, `${nested}[source][${termName}][value][source_id]`)).toHaveValue('')
    await select(page, `${nested}[source][${termName}][value][source_id]`).selectOption('field_property:taxonomy_term:name:value')
    await drupal.ajaxReady()
  })

  await test.step('3. Slot: nested component gets the same contexts, shows the term color', async () => {
    await openAllDetails(page)
    await select(page, `${base}[slots][slot][add_more_button]`).selectOption('component')
    const component = page.locator(`select[name^="${base}[slots][slot][sources]"][name$="[component_id]"]`).first()
    await component.waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(page)
    await component.selectOption(config.testComponentId)
    const nestedString = page.locator(`select[name^="${base}[slots][slot][sources]"][name$="[props][string][source_id]"]`).first()
    await nestedString.waitFor({ state: 'attached', timeout: 20_000 })
    const prefix = (await nestedString.getAttribute('name'))!.replace('[source_id]', '')
    const nested = `${prefix}[source][${ref}][value]`

    await pick(page, `${prefix}[source_id]`, referenced, `${nested}[source_id]`)
    await pick(page, `${nested}[source_id]`, 'entity_field', `${nested}[source][derivable_context]`)
    await pick(page, `${nested}[source][derivable_context]`, termColor, `${nested}[source][${termColor}][value][source_id]`)
    await select(page, `${nested}[source][${termColor}][value][source_id]`).selectOption('field_property:taxonomy_term:field_test_color:value')
    await drupal.ajaxReady()
  })

  await test.step('4. Delete: add a second slot source, then remove it', async () => {
    // Net zero, so the persistence checks below still see one slot source.
    await assertSlotAddRemove(page, `${base}[slots][slot]`, 'foo')
  })

  await test.step('5. Update, save, reopen: the whole chain is kept', async () => {
    // Checkbox and attributes props, checked again after reopen.
    await openAllDetails(page)
    await page.locator(`input[name="${base}[props][boolean][source][value]"]`).check()
    await page.locator(`input[name="${base}[props][attributes][source][value]"]`).fill('class="from-formatter"')
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')

    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    const nested = `${base}[props][string][source][${ref}][value]`
    await expect(select(page, `${base}[props][string][source_id]`)).toHaveValue(referenced)
    await expect(page.locator(`input[name="${base}[props][string][source][derivable_context]"]`)).toHaveValue(ref)
    await expect(select(page, `${nested}[source_id]`)).toHaveValue('entity_field')
    await expect(select(page, `${nested}[source][derivable_context]`)).toHaveValue(termName)
    await expect(select(page, `${nested}[source][${termName}][value][source_id]`)).toHaveValue('field_property:taxonomy_term:name:value')
    const nestedString = page.locator(`select[name^="${base}[slots][slot][sources]"][name$="[props][string][source_id]"]`).first()
    await expect(nestedString).toHaveValue(referenced)
    const prefix = (await nestedString.getAttribute('name'))!.replace('[source_id]', '')
    await expect(select(page, `${prefix}[source][${ref}][value][source][derivable_context]`)).toHaveValue(termColor)
    await expect(select(page, `${prefix}[source][${ref}][value][source][${termColor}][value][source_id]`)).toHaveValue(
      'field_property:taxonomy_term:field_test_color:value',
    )
    await expect(page.locator(`input[name="${base}[props][boolean][source][value]"]`)).toBeChecked()
    await expect(page.locator(`input[name="${base}[props][boolean_with_default_true][source][value]"]`)).toBeChecked()
    await expect(page.locator(`input[name="${base}[props][attributes][source][value]"]`)).toHaveValue('class="from-formatter"')
  })

  await test.step('6. The article renders one component per tag, with name and color', async () => {
    await page.goto(`node/${nodeId}`)
    // Outer string then nested string, for each of the two tags.
    const strings = page.locator('.ui-patterns-test-component .ui-patterns-props-string')
    await expect(strings).toHaveCount(4)
    expect((await strings.allTextContents()).map(s => s.trim())).toEqual(['Drupal', 'blue', 'Playwright', 'green'])
    const outer = page.locator('.ui-patterns-test-component.from-formatter')
    await expect(outer).toHaveCount(2)
    await expect(outer.locator('> .ui-patterns-props-boolean')).toHaveText(['1', '1'])
    await expect(outer.locator('> .ui-patterns-props-boolean_with_default_true')).toHaveText(['1', '1'])
  })

  // A slot form takes its value from the processed form values, not from the
  // raw POST: the browser submits nothing for an unchecked checkbox, and a
  // wrapped plugin form whose setting defaults to TRUE gets it back checked.
  await test.step('7. Slot: a setting left unchecked stays unchecked', async () => {
    const openSettings = async () => {
      await page.locator(`input[name="${field}_settings_edit"]`).click()
      await select(page, `${base}[props][string][source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
      await openAllDetails(page)
    }
    await page.goto('admin/structure/types/manage/test_article/display/default')
    await openSettings()

    // The Label formatter links to the referenced entity unless told not to,
    // which is what makes a lost value visible: it comes back checked.
    await select(page, `${base}[slots][slot][add_more_button]`).selectOption(`field_formatter:node:test_article:${field}`)
    const type = page.locator(`select[name^="${base}[slots][slot][sources]"][name$="[source][type]"]`).first()
    await type.waitFor({ state: 'attached', timeout: 20_000 })
    const link = (await type.getAttribute('name'))!.replace(/\[type\]$/, '[settings][link]')
    // The settings subform renders before a formatter is picked, so there is
    // no new element to wait on: wait on the rebuild itself. Label is the
    // field's default formatter: leave it and come back, so that its settings
    // form is new and arrives over AJAX with its defaults.
    await type.selectOption('entity_reference_entity_id')
    await drupal.ajaxReady()
    await type.selectOption('entity_reference_label')
    await drupal.ajaxReady()
    await openAllDetails(page)
    await expect(page.locator(`input[name="${link}"]`)).toBeChecked()
    await page.locator(`input[name="${link}"]`).uncheck()

    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')

    await openSettings()
    await expect(page.locator(`input[name="${link}"]`)).not.toBeChecked()
    // The submit carrying that empty value kept the rest of the slot.
    const nestedString = page.locator(`select[name^="${base}[slots][slot][sources]"][name$="[props][string][source_id]"]`).first()
    await expect(nestedString).toHaveValue(referenced)
  })

  await test.step('8. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})
