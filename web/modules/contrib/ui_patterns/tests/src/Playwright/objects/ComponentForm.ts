import { expect, Locator, Page } from '@playwright/test'

// Helpers for the component form: selects named by their form path, AJAX
// rebuilds, collapsed details.

/** Prop forms render inside collapsed <details>; open one by its summary. */
export async function openDetails(scope: Locator | Page, label: string): Promise<void> {
  const summary = scope.getByRole('button', { name: label, exact: true }).first()
  const details = summary.locator('xpath=ancestor::details[1]')
  if ((await details.count()) > 0 && (await details.getAttribute('open')) === null) {
    await summary.click()
  }
}

/** Opens every <details> in scope: deep forms nest them several levels. */
export async function openAllDetails(scope: Locator | Page): Promise<void> {
  if ('goto' in scope) {
    await scope.evaluate(() => document.querySelectorAll('details').forEach(d => (d.open = true)))
  } else {
    await scope.evaluate(node => node.querySelectorAll('details').forEach(d => (d.open = true)))
  }
}

export function select(scope: Locator | Page, name: string): Locator {
  return scope.locator(`select[name="${name}"]`)
}

/** A source select turns into a hidden input when only one source fits. */
export function value(scope: Locator | Page, name: string): Locator {
  return scope.locator(`[name="${name}"]`)
}

/** Picks an option and waits for the AJAX rebuild to land the next element. */
export async function pick(scope: Locator | Page, name: string, option: string, next: string): Promise<void> {
  await openAllDetails(scope)
  await select(scope, name).selectOption(option)
  await value(scope, next).first().waitFor({ state: 'attached', timeout: 20_000 })
  await openAllDetails(scope)
}

/**
 * Adds a source to a slot then removes it, asserting the row comes and goes.
 * A slot is cardinality-multiple, so delete is testable there. The added
 * source is the last one, so removing the last button removes it: net zero.
 * Remove buttons carry a flat `..._<delta>_remove` name, so they are counted
 * across the form rather than by the slot's form path.
 */
export async function assertSlotAddRemove(scope: Locator | Page, slotName: string, option: string): Promise<void> {
  await openAllDetails(scope)
  const removes = scope.locator('[name$="_remove"]')
  const before = await removes.count()
  await select(scope, `${slotName}[add_more_button]`).selectOption(option)
  await expect(removes).toHaveCount(before + 1, { timeout: 20_000 })
  await removes.last().click()
  await expect(removes).toHaveCount(before, { timeout: 20_000 })
}
