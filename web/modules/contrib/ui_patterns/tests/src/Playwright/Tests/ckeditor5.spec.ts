import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { assertSlotAddRemove, openAllDetails, pick, select } from '../objects/ComponentForm'
import { seedArticles } from '../objects/TestContent'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'

// The CKEditor 5 integration, end to end. Not covered by PHPUnit: the
// editor. The dialog opened from the toolbar, its form over AJAX with the
// entity context of the node being edited, the widget preview, editing
// through the balloon, the saved markup, the node page, and deleting.
//
// Content: ui_patterns_test_content (test_article with a text body and the
// "Test components" format, whose editor has the component toolbar item).

const dialog = '.ui-dialog'
const embed = '.ui-dialog-buttonset button:has-text("Embed")'
const widget = '.ck-editor__editable .ck-widget.drupal-component'
const rendered = '.ui-patterns-test-component.from-ckeditor .ui-patterns-props-string'

test('CKEditor 5: insert, edit, render and delete a component', { tag: ['@base', '@ckeditor5'] }, async ({ page, drupal }) => {
  // The node forms only: the article page runs the site theme's own scripts.
  const audit = new Audit(page, drupal, url => /\/node\/(add|\d+\/edit)/.test(url))
  let nodeId = ''

  const openEditor = async (path: string) => {
    await page.goto(path)
    // The selector only renders when the user has more than one format.
    const format = page.locator('select[name="field_test_body[0][format]"]')
    if ((await format.count()) > 0) {
      await format.selectOption('test_components')
    }
    await page.locator('.ck-editor__editable').waitFor({ timeout: 20_000 })
  }
  const openDialogFromToolbar = async () => {
    await page.locator('.ck-toolbar .ck-button[data-cke-tooltip-text="Insert component"]').click()
    await page.locator(dialog).waitFor({ timeout: 20_000 })
    await drupal.ajaxReady()
  }
  const openDialogFromBalloon = async () => {
    await page.locator(widget).first().click()
    await page.locator('.ck-balloon-panel .ck-button[data-cke-tooltip-text="Edit component"]').click()
    await page.locator(dialog).waitFor({ timeout: 20_000 })
    await drupal.ajaxReady()
  }
  const embedAndWaitPreview = async (text: string) => {
    await page.locator(embed).click()
    await page.locator(dialog).waitFor({ state: 'detached', timeout: 20_000 })
    await expect(page.locator(`${widget} ${rendered}`)).toHaveText(text, { timeout: 20_000 })
  }
  const editorData = () => page.evaluate(() => (window as any).Drupal.CKEditor5Instances.values().next().value.getData())

  await test.step('0. Setup: modules, content, watchdog marker, admin login', async () => {
    await enableModules(drupal, { content: true })
    nodeId = await seedArticles(drupal)
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. New node: the dialog offers the entity sources of the bundle', async () => {
    await openEditor('node/add/test_article')
    await openDialogFromToolbar()
    await expect(page.locator(`${dialog} .ui-dialog-title`)).toHaveText('Insert component')
    const scope = page.locator(dialog)
    await pick(scope, 'component[component_id]', config.testComponentId, 'component[props][string][source_id]')
    // The unsaved node gives the sources a sample entity of its bundle.
    const options = await select(scope, 'component[props][string][source_id]')
      .locator('option')
      .evaluateAll(list => list.map(o => (o as HTMLOptionElement).value))
    expect(options).toEqual(expect.arrayContaining(['textfield', 'token', 'entity_field']))
  })

  await test.step('2. Fill a prop, switch a source over AJAX, add and remove a slot source, embed', async () => {
    const scope = page.locator(dialog)
    const nested = 'component[props][string][source][field:node:test_article:title][value]'
    await pick(scope, 'component[props][string][source_id]', 'entity_field', 'component[props][string][source][derivable_context]')
    await pick(scope, 'component[props][string][source][derivable_context]', 'field:node:test_article:title', `${nested}[source_id]`)
    await pick(scope, 'component[props][string][source_id]', 'textfield', 'component[props][string][source][value]')
    await scope.locator('input[name="component[props][string][source][value]"]').fill('Hello')
    await assertSlotAddRemove(scope, 'component[slots][slot]', 'foo')
    await openAllDetails(scope)
    await scope.locator('input[name="component[props][attributes][source][value]"]').fill('class="from-ckeditor"')
    await embedAndWaitPreview('Hello')
    expect(await editorData()).toContain('<drupal-component data-component-id="ui_patterns_test:test-component"')
  })

  await test.step('3. Edit through the balloon: the form is prefilled, the widget is replaced', async () => {
    await openDialogFromBalloon()
    await expect(page.locator(`${dialog} .ui-dialog-title`)).toHaveText(`Edit component: ${config.testComponentLabel}`)
    const scope = page.locator(dialog)
    await openAllDetails(scope)
    await expect(scope.locator('input[name="component[props][string][source][value]"]')).toHaveValue('Hello')
    await scope.locator('input[name="component[props][string][source][value]"]').fill('World')
    await embedAndWaitPreview('World')
    await expect(page.locator(widget)).toHaveCount(1)
    expect(await editorData()).toContain('World')
  })

  await test.step('4. Save: the node page renders the component', async () => {
    await page.locator('input[name="title[0][value]"]').fill('CKEditor article')
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('has been created')
    nodeId = page.url().match(/node\/(\d+)/)![1]
    await expect(page.locator(rendered)).toHaveText('World')
  })

  await test.step('5. Saved node: the token source sees the node, in the editor and on the page', async () => {
    await openEditor(`node/${nodeId}/edit`)
    await expect(page.locator(`${widget} ${rendered}`)).toHaveText('World', { timeout: 20_000 })
    await openDialogFromBalloon()
    const scope = page.locator(dialog)
    // Both sources have a [source][value] input: wait on the rebuild itself.
    await openAllDetails(scope)
    await select(scope, 'component[props][string][source_id]').selectOption('token')
    await drupal.ajaxReady()
    await openAllDetails(scope)
    await expect(select(scope, 'component[props][string][source_id]')).toHaveValue('token')
    await scope.locator('[name="component[props][string][source][value]"]').fill('[node:title]')
    await embedAndWaitPreview('CKEditor article')
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('has been updated')
    await expect(page.locator(rendered)).toHaveText('CKEditor article')
  })

  await test.step('6. Delete through the balloon, save: the page has no component', async () => {
    await openEditor(`node/${nodeId}/edit`)
    await page.locator(widget).first().waitFor({ timeout: 20_000 })
    await page.locator(widget).first().click()
    await page.locator('.ck-balloon-panel .ck-button[data-cke-tooltip-text="Delete component"]').click()
    await expect(page.locator(widget)).toHaveCount(0)
    expect(await editorData()).not.toContain('<drupal-component')
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('has been updated')
    await expect(page.locator('.ui-patterns-test-component')).toHaveCount(0)
  })

  await test.step('7. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})
