import { expect, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { addSlotSource, assertSlotAddRemove, openAllDetails, openSourceSelector, pick, pickSource, select, sourceRow, value } from '../objects/ComponentForm'
import { resetArticleDisplay, resetSourceField, seedArticles, showSourceField } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import type { Drupal } from '../objects/Drupal'
import { createDisplay } from '../objects/FormDisplay'
import { enableModules } from '../objects/Setup'

// The ui_patterns_source field, on the article form. Not covered by PHPUnit:
// the widget forms over AJAX, core's multiple items around them, the default
// value form, the widget settings forms, and the saved values rendering on a
// real node.
//
// Content: ui_patterns_test_content, field_test_source (unlimited) on
// test_article, hidden on the page unless a test shows it.

const field = 'field_test_source'
const item = (delta: number): string => `${field}[${delta}]`
const component = (delta: number): string => `${item(delta)}[source][component]`

/** Admin forms and the article form: the article page runs the site theme's scripts. */
const formPages = (url: string): boolean => url.includes('/admin/') || url.includes('/node/add/') || url.endsWith('/edit')

/** Picks the test component for a field item, from the source select down. */
async function pickTestComponent(page: Page, base: string): Promise<void> {
  await pick(page, `${base}[source_id]`, 'component', `${base}[source][component][component_id]`)
  const form = `${base}[source][component]`
  await pick(page, `${form}[component_id]`, config.testComponentId, `${form}[props][string][source][value]`)
}

/** The saved items of the source field. */
async function storedItems(drupal: Drupal, nodeId: string): Promise<Record<string, unknown>[]> {
  const php = `print json_encode(\\Drupal\\node\\Entity\\Node::load(${nodeId})->get('${field}')->getValue());`
  return JSON.parse((await drupal.drush(`ev "${php}"`)).trim())
}

/**
 * Saving the article form keeps what the widget does not edit: the item's
 * id, which translations are matched on, and the third-party settings that
 * other modules store on it.
 */
async function assertSaveKeepsItemMetadata(page: Page, drupal: Drupal, nodeId: string): Promise<void> {
  const settings = { ui_patterns_test: { kept: 'yes' } }
  const json = Buffer.from(JSON.stringify(settings)).toString('base64')
  const php = [
    `\\$node = \\Drupal\\node\\Entity\\Node::load(${nodeId});`,
    `\\$items = \\$node->get('${field}')->getValue();`,
    `\\$items[0]['third_party_settings'] = json_decode(base64_decode('${json}'), TRUE);`,
    `\\$node->set('${field}', \\$items)->save();`,
  ].join(' ')
  await drupal.drush(`ev "${php}"`)
  const before = (await storedItems(drupal, nodeId))[0]
  expect(before.node_id).toBeTruthy()

  await page.goto(`node/${nodeId}/edit`)
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await drupal.expectMessage('has been updated.')
  const after = (await storedItems(drupal, nodeId))[0]
  expect(after.node_id).toBe(before.node_id)
  expect(after.third_party_settings).toEqual(settings)
}

test('Field: default value, items, slots, edit, render', { tag: ['@base', '@field'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, formPages)
  let nodeId = ''

  await test.step('0. Setup: modules, field as shipped and shown, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetSourceField(drupal)
    await showSourceField(drupal)
    // Clears the articles of earlier runs.
    await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Default value: a component, set on the field page', async () => {
    const base = `default_value_input[${field}][0]`
    await page.goto(`admin/structure/types/manage/test_article/fields/node.test_article.${field}`)
    await page.locator('input[name="set_default_value"]').check()
    await pickTestComponent(page, base)
    await page.locator(`input[name="${base}[source][component][props][string][source][value]"]`).fill('Default string')
    await page.locator(`input[name="${base}[source][component][props][attributes][source][value]"]`).fill('class="from-default"')
    await page.getByRole('button', { name: 'Save settings' }).click()
    await drupal.expectMessage('Saved Source configuration.')
  })

  await test.step('2. New article: the default is there, a second item with a slot', async () => {
    await page.goto('node/add/test_article')
    await page.locator('input[name="title[0][value]"]').fill('Article with sources')
    await openAllDetails(page)
    await expect(value(page, `${item(0)}[source_id]`)).toHaveValue('component')
    await expect(page.locator(`input[name="${component(0)}[props][string][source][value]"]`)).toHaveValue('Default string')

    // An unlimited field shows one empty item after the default one.
    await pickTestComponent(page, item(1))
    await page.locator(`input[name="${component(1)}[props][string][source][value]"]`).fill('Second item')
    await page.locator(`input[name="${component(1)}[props][attributes][source][value]"]`).fill('class="from-second"')
    const slot = `${component(1)}[slots][slot]`
    await assertSlotAddRemove(page, slot, 'foo')
    await addSlotSource(page, slot, 'textfield', `${slot}[sources][0][source][value]`)
    await page.locator(`input[name="${slot}[sources][0][source][value]"]`).fill('Slot text')

    // Core rebuilds the whole widget: the items filled so far are kept.
    await page.locator(`input[name="${field}_add_more"]`).click()
    await value(page, `${item(2)}[source_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await drupal.ajaxReady()
    await openAllDetails(page)
    await expect(page.locator(`input[name="${component(1)}[props][string][source][value]"]`)).toHaveValue('Second item')
    await expect(page.locator(`input[name="${slot}[sources][0][source][value]"]`)).toHaveValue('Slot text')

    // The third item stays empty: core drops it on save.
    await page.getByRole('button', { name: 'Save', exact: true }).click()
    await drupal.expectMessage('has been created.')
    nodeId = page.url().match(/node\/(\d+)/)![1]
  })

  await test.step('3. The article renders both items', async () => {
    await page.goto(`node/${nodeId}`)
    const first = page.locator('.ui-patterns-test-component.from-default')
    const second = page.locator('.ui-patterns-test-component.from-second')
    await expect(first.locator('> .ui-patterns-props-string')).toHaveText('Default string')
    await expect(second.locator('> .ui-patterns-props-string')).toHaveText('Second item')
    await expect(second.locator('> .ui-patterns-slots-slot')).toContainText('Slot text')
  })

  await test.step('4. Edit: every level is kept, a source switched over AJAX, an item removed', async () => {
    await page.goto(`node/${nodeId}/edit`)
    await openAllDetails(page)
    await expect(value(page, `${item(0)}[source_id]`)).toHaveValue('component')
    await expect(value(page, `${component(0)}[component_id]`)).toHaveValue(config.testComponentId)
    await expect(page.locator(`input[name="${component(0)}[props][string][source][value]"]`)).toHaveValue('Default string')
    await expect(page.locator(`input[name="${component(1)}[props][string][source][value]"]`)).toHaveValue('Second item')
    await expect(page.locator(`input[name="${component(1)}[slots][slot][sources][0][source][value]"]`)).toHaveValue('Slot text')

    // The token source reads the node the widget is on.
    await pickSource(page, `${component(1)}[props][string][source_id]`, 'token')
    await drupal.ajaxReady()
    await openAllDetails(page)
    await expect(select(page, `${component(1)}[props][string][source_id]`)).toHaveValue('token')
    await page.locator(`[name="${component(1)}[props][string][source][value]"]`).fill('[node:title]')

    await page.locator(`input[name="${field}_0_remove_button"]`).click()
    await expect(page.locator(`input[name="${component(0)}[props][attributes][source][value]"]`)).toHaveValue('class="from-second"', {
      timeout: 20_000,
    })
    await page.getByRole('button', { name: 'Save', exact: true }).click()
    await drupal.expectMessage('has been updated.')
  })

  await test.step('5. The article renders the remaining item, with the node title', async () => {
    await page.goto(`node/${nodeId}`)
    await expect(page.locator('.ui-patterns-test-component.from-default')).toHaveCount(0)
    const second = page.locator('.ui-patterns-test-component.from-second')
    await expect(second.locator('> .ui-patterns-props-string')).toHaveText('Article with sources')
    await expect(second.locator('> .ui-patterns-slots-slot')).toContainText('Slot text')
  })

  await test.step('6. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

test('Field: Components only widget, its settings and the form they shape', { tag: ['@base', '@field'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, formPages)
  const settings = `fields[${field}][settings_edit_form][settings]`
  const summary = page.locator(`tr:has(input[name="${field}_settings_edit"]) .field-plugin-summary`)
  let nodeId = ''

  await test.step('0. Setup: modules, field as shipped and shown, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetSourceField(drupal)
    await showSourceField(drupal)
    await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Manage form display: the widget, its component, slots and two props', async () => {
    await page.goto('admin/structure/types/manage/test_article/form-display')
    await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_source_component')
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await drupal.ajaxReady()
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    // Picking the component loads its props over AJAX.
    await select(page, `${settings}[component_id]`).selectOption(config.testComponentId)
    await page.locator(`select[name="${settings}[selection][prop_filter][]"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.locator(`input[name="${settings}[hide_slots]"]`).uncheck()
    await page.locator(`input[name="${settings}[prop_filter_enable]"]`).check()
    await page.locator(`select[name="${settings}[selection][prop_filter][]"]`).selectOption(['attributes', 'string'])
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })

    await expect(summary).toContainText(`Component Id: ${config.testComponentId}`)
    await expect(summary).not.toContainText('Hide slots')
    await expect(summary).toContainText('Only selected props: attributes,string')
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
  })

  await test.step('2. Reopen: the settings are kept', async () => {
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await select(page, `${settings}[component_id]`).waitFor({ state: 'attached', timeout: 20_000 })
    await expect(select(page, `${settings}[component_id]`)).toHaveValue(config.testComponentId)
    await expect(page.locator(`input[name="${settings}[hide_slots]"]`)).not.toBeChecked()
    await expect(page.locator(`input[name="${settings}[prop_filter_enable]"]`)).toBeChecked()
    await expect(page.locator(`select[name="${settings}[selection][prop_filter][]"]`)).toHaveValues(['attributes', 'string'])
  })

  await test.step('3. New article: the component is fixed, two props and the slot', async () => {
    await page.goto('node/add/test_article')
    await page.locator('input[name="title[0][value]"]').fill('Article with a fixed component')
    await openAllDetails(page)
    await expect(value(page, `${component(0)}[component_id]`)).toHaveValue(config.testComponentId)
    const props = await page
      .locator(`[name^="${component(0)}[props]"][name$="[node_id]"]`)
      .evaluateAll(els => els.map(el => (el as HTMLInputElement).name))
    expect(props.sort()).toEqual([`${component(0)}[props][attributes][node_id]`, `${component(0)}[props][string][node_id]`])
    // "Display all" prop sources, not only the widgets: a prop with one
    // source fitting has no selector to open.
    const stringSource = `${component(0)}[props][string][source_id]`
    await expect(sourceRow(page, stringSource).locator('.uip-source-selector-toggle--open').first()).toBeAttached()
    await openSourceSelector(page, stringSource)
    const sources = await select(page, stringSource)
      .locator('option')
      .evaluateAll(list => list.map(o => (o as HTMLOptionElement).value))
    expect(sources, sources.join(', ')).toEqual(expect.arrayContaining(['textfield', 'token']))
    await page.locator(`input[name="${component(0)}[props][string][source][value]"]`).fill('Fixed component')
    await page.locator(`input[name="${component(0)}[props][attributes][source][value]"]`).fill('class="from-component-widget"')
    const slot = `${component(0)}[slots][slot]`
    await addSlotSource(page, slot, 'textfield', `${slot}[sources][0][source][value]`)
    await page.locator(`input[name="${slot}[sources][0][source][value]"]`).fill('Widget slot text')
    await page.getByRole('button', { name: 'Save', exact: true }).click()
    await drupal.expectMessage('has been created.')
    nodeId = page.url().match(/node\/(\d+)/)![1]
  })

  await test.step('4. The article renders the component; editing keeps the values', async () => {
    await page.goto(`node/${nodeId}`)
    const rendered = page.locator('.ui-patterns-test-component.from-component-widget')
    await expect(rendered.locator('> .ui-patterns-props-string')).toHaveText('Fixed component')
    await expect(rendered.locator('> .ui-patterns-slots-slot')).toContainText('Widget slot text')

    await page.goto(`node/${nodeId}/edit`)
    await openAllDetails(page)
    await expect(page.locator(`input[name="${component(0)}[props][string][source][value]"]`)).toHaveValue('Fixed component')
    await expect(page.locator(`input[name="${component(0)}[slots][slot][sources][0][source][value]"]`)).toHaveValue('Widget slot text')
  })

  await test.step('5. Saving again keeps the item id and its third-party settings', async () => {
    await assertSaveKeepsItemMetadata(page, drupal, nodeId)
  })

  await test.step('6. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})

test('Field: form display widget of ui_patterns_ui', { tag: ['@base', '@field'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, formPages)
  const settings = `fields[${field}][settings_edit_form][settings]`
  const display = 'ui_patterns_test.test-component.field'
  // Html::getId() of the display id names its form: dots dropped, underscores dashed.
  const formId = display.replace(/[._]/g, m => (m === '.' ? '' : '-'))
  const displayed = (path: string) => page.locator(`[name^="${item(0)}"][name*="[value_${formId}]${path}"]`)
  // Later tests expect the plain component form.
  const deleteDisplays = () =>
    drupal.drush(
      `ev "\\$s = \\Drupal::entityTypeManager()->getStorage('component_form_display'); \\$s->delete(\\$s->loadByProperties(['component_id' => '${config.testComponentId}']));"`,
    )
  let nodeId = ''

  await test.step('0. Setup: modules, field as shipped and shown, a display, admin login', async () => {
    await enableModules(drupal, { content: true })
    await resetSourceField(drupal)
    await showSourceField(drupal)
    await seedArticles(drupal)
    await deleteDisplays()
    const row = (source_id: string, weight: number) => ({
      region: 'content',
      weight: String(weight),
      parent: '',
      source_id,
      widget_settings: {},
      third_party_settings: {},
    })
    await createDisplay(drupal, {
      label: 'Field',
      component_id: config.testComponentId,
      form_mode_name: 'field',
      status: true,
      content: { string: row('textfield', 0), attributes: row('attributes', 1) },
      hidden: {},
    })
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Manage form display: the widget and its display', async () => {
    await page.goto('admin/structure/types/manage/test_article/form-display')
    await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_ui_component_form_display')
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await drupal.ajaxReady()
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    // Dots are stored as double pipes.
    await select(page, `${settings}[display_id]`).selectOption(display.replaceAll('.', '||'))
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await expect(page.locator(`tr:has(input[name="${field}_settings_edit"]) .field-plugin-summary`)).toContainText(`Display Id: ${display}`)
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
  })

  await test.step('2. New article: the display form, filled and saved', async () => {
    await page.goto('node/add/test_article')
    await page.locator('input[name="title[0][value]"]').fill('Article through a display')
    await openAllDetails(page)
    await displayed('[string][source][value]').fill('From the display')
    await displayed('[attributes][source][value]').fill('class="from-display"')
    await page.getByRole('button', { name: 'Save', exact: true }).click()
    await drupal.expectMessage('has been created.')
    nodeId = page.url().match(/node\/(\d+)/)![1]
  })

  await test.step('3. The article renders the component; editing keeps the values', async () => {
    await page.goto(`node/${nodeId}`)
    await expect(page.locator('.ui-patterns-test-component.from-display > .ui-patterns-props-string')).toHaveText('From the display')
    await page.goto(`node/${nodeId}/edit`)
    await openAllDetails(page)
    await expect(displayed('[string][source][value]')).toHaveValue('From the display')
  })

  await test.step('4. Saving again keeps the item id and its third-party settings', async () => {
    await assertSaveKeepsItemMetadata(page, drupal, nodeId)
  })

  await test.step('5. Cleanup, then no PHP error in watchdog, no JS error in console', async () => {
    await deleteDisplays()
    await audit.expectClean()
  })
})

// The page cache serves anonymous visitors: the component reading the field
// must carry the cache tags of what the stored component reads, here the
// related article, which nothing else on the page shows.
test('Field: a component reading the field, refreshed for anonymous visitors', { tag: ['@base', '@field'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, formPages)
  const base = `fields[${field}][settings_edit_form][settings][ui_patterns]`
  const reader = `ui_patterns_source:node:${field}`
  const related = 'entity_reference:node:test_article:field_test_related:node:test_article'
  let nodeId = ''

  await test.step('0. Setup: the first article stores a component reading its related article', async () => {
    await enableModules(drupal, { content: true })
    await resetSourceField(drupal)
    await resetArticleDisplay(drupal)
    nodeId = await seedArticles(drupal)
    const stored = {
      source_id: 'component',
      source: {
        component: {
          component_id: config.testComponentId,
          props: {
            string: {
              source_id: 'entity_reference',
              source: { derivable_context: related, [related]: { value: { source_id: 'field_property:node:title:value', source: {} } } },
            },
          },
        },
      },
    }
    const json = Buffer.from(JSON.stringify([stored])).toString('base64')
    const php = [
      `\\$node = \\Drupal\\node\\Entity\\Node::load(${nodeId});`,
      `\\$node->set('${field}', json_decode(base64_decode('${json}'), TRUE))->save();`,
      // Only the component may show the related article.
      "\\Drupal::service('entity_display.repository')->getViewDisplay('node', 'test_article')->removeComponent('field_test_related')->removeComponent('field_test_tags')->save();",
    ].join(' ')
    await drupal.drush(`ev "${php}"`)
    await showSourceField(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Manage display: a component whose string comes from the field', async () => {
    await page.goto('admin/structure/types/manage/test_article/display/default')
    await page.locator(`select[name="fields[${field}][type]"]`).selectOption('ui_patterns_component')
    await page.getByText('No component selected.').waitFor({ timeout: 20_000 })
    await page.locator(`input[name="${field}_settings_edit"]`).click()
    await pick(page, `${base}[component_id]`, config.testComponentId, `${base}[props][string][source_id]`)
    await openSourceSelector(page, `${base}[props][string][source_id]`)
    const options = await select(page, `${base}[props][string][source_id]`)
      .locator('option')
      .evaluateAll(list => list.map(o => (o as HTMLOptionElement).value))
    expect(options, options.join(', ')).toContain(reader)
    await select(page, `${base}[props][string][source_id]`).selectOption(reader)
    await drupal.ajaxReady()
    await openAllDetails(page)
    await page.locator(`input[name="${base}[props][attributes][source][value]"]`).fill('class="from-reader"')
    await page.locator(`input[name="${field}_plugin_settings_update"]`).click()
    await page.locator(`input[name="${field}_settings_edit"]`).waitFor({ state: 'attached', timeout: 20_000 })
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')
  })

  await test.step('2. An anonymous visitor sees the related title, then its new title', async () => {
    const visitor = await drupal.anonymousPage()
    const string = visitor.locator('.ui-patterns-test-component.from-reader > .ui-patterns-props-string')
    // Twice: the second visit comes from the page cache.
    for (let visit = 0; visit < 2; visit++) {
      await visitor.goto(`node/${nodeId}`)
      await expect(string).toHaveText('Second article')
    }
    await drupal.drush(
      `ev "\\$nodes = \\Drupal::entityTypeManager()->getStorage('node'); \\$second = current(\\$nodes->loadByProperties(['title' => 'Second article'])); \\$second->setTitle('Second article renamed')->save();"`,
    )
    await visitor.goto(`node/${nodeId}`)
    await expect(string).toHaveText('Second article renamed')
    await visitor.context().close()
  })

  await test.step('3. Display as shipped, no PHP error in watchdog, no JS error in console', async () => {
    await resetSourceField(drupal)
    await resetArticleDisplay(drupal)
    await audit.expectClean()
  })
})
