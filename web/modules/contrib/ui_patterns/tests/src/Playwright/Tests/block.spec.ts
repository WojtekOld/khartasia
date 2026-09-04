import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import { openAllDetails, openDetails } from '../objects/ComponentForm'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'

// Contexts and AJAX through the block plugin (ui_patterns_blocks). One long
// test: login and module setup are done once.
//
// Covered: switch a prop source and switch back, add and remove a slot
// source, the block plugin form inside BlockSource, a component inside a
// slot with unique wrapper ids, then save, reopen, and render the result.

test('Block: source settings forms over AJAX', { tag: ['@base', '@block'] }, async ({ page, drupal }) => {
  // Admin forms only: the page the block renders on runs the theme's scripts.
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let theme = 'claro'
  const nestedDefaultTrue = page.locator('input[name*="[slots]"][name*="[props][boolean_with_default_true][source][value]"]').first()
  const onByDefault = page.locator('input[name="settings[ui_patterns][props][string][source][on_by_default]"]')

  await test.step('0. Setup: modules, cleanup, watchdog marker, admin login', async () => {
    await enableModules(drupal)
    theme = (await drupal.drush(`ev "print \\Drupal::config('system.theme')->get('default');"`)).trim()
    // Blocks left by earlier runs would match the same rows.
    await drupal.drush(
      `ev "\\Drupal::entityTypeManager()->getStorage('block')->delete(\\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['plugin' => 'ui_patterns:${config.testComponentId}']));"`,
    )
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Place block, switch the prop source and switch back', async () => {
    await page.goto(`admin/structure/block/library/${theme}?region=content`)
    const row = page.getByRole('row', { name: config.testComponentLabel }).first()
    const href = await row.getByRole('link', { name: 'Place block' }).getAttribute('href')
    await page.goto(href!.split('?')[0])

    await openDetails(page, 'Links')
    const sourceSelect = page.locator('select[name="settings[ui_patterns][props][links][source_id]"]')
    const menuSelect = page.locator('select[name="settings[ui_patterns][props][links][source][menu]"]')

    // Menu -> its settings form arrives over AJAX.
    await sourceSelect.selectOption({ label: 'Menu' })
    await menuSelect.waitFor({ state: 'attached', timeout: 15_000 })

    // Breadcrumb -> the wrapper is replaced, the menu select is gone.
    await sourceSelect.selectOption({ label: 'Breadcrumb' })
    await expect(menuSelect).toHaveCount(0)

    // Back to Menu -> the form comes back on the same wrapper.
    await sourceSelect.selectOption({ label: 'Menu' })
    await menuSelect.waitFor({ state: 'attached', timeout: 15_000 })
    await menuSelect.selectOption('main')

    // A source whose setting is on by default: its form arrives over AJAX.
    await openAllDetails(page)
    await page.locator('select[name="settings[ui_patterns][props][string][source_id]"]').selectOption('boolean_settings')
    await onByDefault.waitFor({ state: 'attached', timeout: 15_000 })
    await expect(onByDefault).toBeChecked()
    await expect(page.locator('input[name="settings[ui_patterns][props][string][source][off_by_default]"]')).not.toBeChecked()

    // Checkbox and attributes props, checked again after save and reopen.
    await openAllDetails(page)
    await page.locator('input[name="settings[ui_patterns][props][boolean][source][value]"]').check()
    await page.locator('input[name="settings[ui_patterns][props][boolean_with_default_true][source][value]"]').uncheck()
    await page.locator('input[name="settings[ui_patterns][props][attributes][source][value]"]').fill('class="from-block"')
  })

  await test.step('2. Nesting: Component source in slot, unique wrapper ids', async () => {
    await openDetails(page, 'Slot')
    await page
      .locator('select[name*="[slots]"][name*="[add_more_button]"]')
      .first()
      .selectOption({ label: 'Component' })
    const nestedComponent = page.locator('select[name*="[slots]"][name*="[component_id]"]').first()
    await nestedComponent.waitFor({ state: 'attached', timeout: 15_000 })
    await nestedComponent.selectOption(config.testComponentId)

    const nestedLinksSource = page.locator('select[name*="[slots]"][name*="[props][links][source_id]"]').first()
    await nestedLinksSource.waitFor({ state: 'attached', timeout: 15_000 })

    // The nested props arrive over AJAX: a boolean prop with a default gets it.
    await expect(nestedDefaultTrue).toBeChecked()
    await expect(page.locator('input[name*="[slots]"][name*="[props][boolean_with_default_false][source][value]"]').first()).not.toBeChecked()

    // The outer and nested links prop forms must have distinct wrapper ids.
    const ids = await page
      .locator('[id*="ui-patterns-prop-item-links"]')
      .evaluateAll(els => els.map(el => el.id))
    expect(ids.length).toBeGreaterThanOrEqual(2)
    expect(new Set(ids).size, 'wrapper ids must be unique').toBe(ids.length)

    // Nested textfield prop: filled now, checked after save and reopen.
    const nestedString = page.locator('input[name*="[slots]"][name*="[props][string][source][value]"]').first()
    const nestedDetails = nestedString.locator('xpath=ancestor::details[1]/summary')
    if ((await nestedDetails.count()) > 0) {
      await nestedDetails.first().click()
    }
    await nestedString.fill('hello-nested')
  })

  await test.step('3. BlockSource: embedded plugin form over AJAX', async () => {
    // After nesting there are two add-source selects, the outer slot and the
    // nested one: pick the outer by exact name, and open its details again,
    // it closes on each AJAX replace.
    await openDetails(page, 'Slot')
    await page
      .locator('select[name="settings[ui_patterns][slots][slot][add_more_button]"]')
      .selectOption({ label: 'Block' })
    const pluginSelect = page.locator('select[name*="[slots]"][name*="[plugin_id]"]').first()
    await pluginSelect.waitFor({ state: 'attached', timeout: 15_000 })
    // BlockSource shows only the blockForm() of the plugin, so pick a block
    // with settings.
    await pluginSelect.selectOption('system_branding_block')
    const useSiteLogo = page.locator('input[name*="[system_branding_block][block_branding][use_site_logo]"]').first()
    await useSiteLogo.waitFor({ state: 'attached', timeout: 15_000 })
    // The block plugin defaults it to TRUE, and the form arrived over AJAX.
    await expect(useSiteLogo).toBeChecked()
  })

  await test.step('4. Remove a source', async () => {
    const removeButtons = page.locator('[name*="_remove"]')
    const before = await removeButtons.count()
    expect(before).toBeGreaterThanOrEqual(2)
    // Remove the Block source added in step 3 (last row).
    await removeButtons.last().click()
    await expect(removeButtons).toHaveCount(before - 1, { timeout: 15_000 })
    await expect(page.locator('select[name*="[slots]"][name*="[plugin_id]"]')).toHaveCount(0)
  })

  await test.step('5. Save, reopen, check everything is kept', async () => {
    await page.getByLabel(/Region/).selectOption({ label: 'Content' })
    await page.getByRole('button', { name: 'Save block' }).click()
    await drupal.expectMessage('The block configuration has been saved.')

    const editHref = await page
      .getByRole('row', { name: config.testComponentLabel })
      .first()
      .getByRole('link', { name: /Edit/ })
      .first()
      .getAttribute('href')
    await page.goto(editHref!.split('?')[0])

    // Outer links prop: Menu source with menu=main.
    const outerSource = page.locator('select[name="settings[ui_patterns][props][links][source_id]"]')
    await expect(outerSource).toHaveValue('menu')
    await expect(page.locator('select[name="settings[ui_patterns][props][links][source][menu]"]')).toHaveValue('main')
    // Nested component and its textfield value.
    await expect(page.locator('select[name*="[slots]"][name*="[component_id]"]').first()).toHaveValue(
      config.testComponentId,
    )
    await expect(
      page.locator('input[name*="[slots]"][name*="[props][string][source][value]"]').first(),
    ).toHaveValue('hello-nested')
    // Checkbox and attributes props.
    await expect(page.locator('input[name="settings[ui_patterns][props][boolean][source][value]"]')).toBeChecked()
    await expect(page.locator('input[name="settings[ui_patterns][props][boolean_with_default_true][source][value]"]')).not.toBeChecked()
    // Defaults never touched must have been saved as TRUE.
    await expect(nestedDefaultTrue).toBeChecked()
    await expect(onByDefault).toBeChecked()
    await expect(page.locator('input[name="settings[ui_patterns][props][attributes][source][value]"]')).toHaveValue('class="from-block"')
  })

  await test.step('6. The placed block renders, form output and all', async () => {
    // The config the forms produced, rendered: the outer component with the
    // class its attributes prop set, and the nested component with its string.
    await page.goto('')
    const component = page.locator('.ui-patterns-test-component.from-block')
    await expect(component).toHaveCount(1)
    await expect(component.locator('> .ui-patterns-props-string')).toHaveText('on_by_default=1 off_by_default=0')
    const nested = component.locator('.ui-patterns-slots-slot .ui-patterns-test-component .ui-patterns-props-string')
    await expect(nested).toContainText('hello-nested')
    await expect(component.locator('.ui-patterns-slots-slot .ui-patterns-test-component .ui-patterns-props-boolean_with_default_true')).toHaveText('1')
  })

  await test.step('7. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})
