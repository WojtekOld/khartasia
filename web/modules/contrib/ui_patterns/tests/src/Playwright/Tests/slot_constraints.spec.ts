import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import { openAllDetails, pick, select } from '../objects/ComponentForm'
import { Audit } from '../objects/Audit'
import { enableModules } from '../objects/Setup'

// Slot restrictions read from the component definition, over AJAX: maxItems
// disables the add select once reached and a remove enables it again;
// expected filters the nested Component selector. The kernel tests pin the
// form structure; this covers the rebuilt DOM and the saved block.

const COMPONENT = 'ui_patterns_test:test-slot-constraints'
const LABEL = 'UI Patterns Test slot constraints'
const WRAPPER = 'ui_patterns_test:test-wrapper-component'
const slot = (id: string, rest = ''): string => `settings[ui_patterns][slots][${id}]${rest}`
const nestedName = (id: string, delta: number): string => slot(id, `[sources][${delta}][source][component][component_id]`)

test('Slot restrictions: maxItems and expected over AJAX', { tag: ['@base', '@slot_constraints'] }, async ({ page, drupal }) => {
  const audit = new Audit(page, drupal, url => url.includes('/admin/'))
  let theme = 'claro'
  const addMax = select(page, slot('slot_max', '[add_more_button]'))
  const nested = (id: string, delta: number) => select(page, nestedName(id, delta))
  const optionValues = async (id: string, delta: number): Promise<string[]> =>
    (await nested(id, delta).locator('option').evaluateAll(els => els.map(el => (el as HTMLOptionElement).value))).filter(Boolean).sort()

  await test.step('0. Setup: modules, cleanup, watchdog marker, admin login', async () => {
    await enableModules(drupal)
    theme = (await drupal.drush(`ev "print \\Drupal::config('system.theme')->get('default');"`)).trim()
    await drupal.drush(
      `ev "\\Drupal::entityTypeManager()->getStorage('block')->delete(\\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['plugin' => 'ui_patterns:${COMPONENT}']));"`,
    )
    await audit.start()
    await drupal.loginAsAdmin()
  })

  await test.step('1. Place the block', async () => {
    await page.goto(`admin/structure/block/library/${theme}?region=content`)
    const row = page.getByRole('row', { name: LABEL }).first()
    const href = await row.getByRole('link', { name: 'Place block' }).getAttribute('href')
    await page.goto(href!.split('?')[0])
  })

  await test.step('2. maxItems: disabled at two sources, enabled again after a remove', async () => {
    await openAllDetails(page)
    await expect(addMax).toBeEnabled()
    await addMax.selectOption({ label: 'Component' })
    await nested('slot_max', 0).waitFor({ state: 'attached', timeout: 20_000 })
    await pick(page, nestedName('slot_max', 0), WRAPPER, slot('slot_max', '[sources][0][source][component][slots][wrapper][add_more_button]'))
    await expect(addMax).toBeEnabled()

    await addMax.selectOption({ label: 'Component' })
    await nested('slot_max', 1).waitFor({ state: 'attached', timeout: 20_000 })
    await pick(page, nestedName('slot_max', 1), WRAPPER, slot('slot_max', '[sources][1][source][component][slots][wrapper][add_more_button]'))
    await expect(addMax).toBeDisabled()

    // Remove the second row: the slot has room again.
    await page.locator('[name^="slot_max"][name$="_1_remove"]').click()
    await expect(nested('slot_max', 1)).toHaveCount(0, { timeout: 20_000 })
    await openAllDetails(page)
    await expect(addMax).toBeEnabled()

    await addMax.selectOption({ label: 'Component' })
    await nested('slot_max', 1).waitFor({ state: 'attached', timeout: 20_000 })
    await pick(page, nestedName('slot_max', 1), WRAPPER, slot('slot_max', '[sources][1][source][component][slots][wrapper][add_more_button]'))
    await expect(addMax).toBeDisabled()
  })

  await test.step('3. expected: the nested selector offers the expected components only', async () => {
    await openAllDetails(page)
    await select(page, slot('slot_expected', '[add_more_button]')).selectOption({ label: 'Component' })
    await nested('slot_expected', 0).waitFor({ state: 'attached', timeout: 20_000 })
    expect(await optionValues('slot_expected', 0)).toEqual(['ui_patterns_test:test-form-component', WRAPPER])
    await pick(page, nestedName('slot_expected', 0), WRAPPER, slot('slot_expected', '[sources][0][source][component][slots][wrapper][add_more_button]'))

    // A slot without expected list offers every visible component.
    await select(page, slot('slot_free', '[add_more_button]')).selectOption({ label: 'Component' })
    await nested('slot_free', 0).waitFor({ state: 'attached', timeout: 20_000 })
    const free = await optionValues('slot_free', 0)
    expect(free).toContain('ui_patterns_test:test-component')
    expect(free).not.toContain('ui_patterns_test:no-ui-component')
    await pick(page, nestedName('slot_free', 0), WRAPPER, slot('slot_free', '[sources][0][source][component][slots][wrapper][add_more_button]'))
  })

  await test.step('4. Save, reopen: both rows kept, the add select still disabled', async () => {
    await page.getByLabel(/Region/).selectOption({ label: 'Content' })
    await page.getByRole('button', { name: 'Save block' }).click()
    await drupal.expectMessage('The block configuration has been saved.')

    const editHref = await page
      .getByRole('row', { name: LABEL })
      .first()
      .getByRole('link', { name: /Edit/ })
      .first()
      .getAttribute('href')
    await page.goto(editHref!.split('?')[0])
    await openAllDetails(page)
    await expect(nested('slot_max', 0)).toHaveValue(WRAPPER)
    await expect(nested('slot_max', 1)).toHaveValue(WRAPPER)
    await expect(addMax).toBeDisabled()
    await expect(nested('slot_expected', 0)).toHaveValue(WRAPPER)
  })

  await test.step('5. No PHP error in watchdog, no JS error in console', async () => {
    await audit.expectClean()
  })
})
