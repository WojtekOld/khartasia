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

/** Opens every closed slot row on the way to a nested form path. */
export async function openSlotRows(scope: Locator | Page, name: string): Promise<void> {
  await openAllDetails(scope)
  const re = /\[sources\]\[\d+\]/g
  let m: RegExpExecArray | null = re.exec(name)
  for (; m !== null; m = re.exec(name)) {
    const prefix = name.slice(0, m.index + m[0].length)
    // An open row shows its selector as a select; closed, a hidden input.
    if ((await scope.locator(`select[name="${prefix}[source_id]"]`).count()) > 0) {
      continue
    }
    const row = scope.locator(`.uip-slot-row:has([name="${prefix}[node_id]"])`).last()
    if ((await row.count()) === 0) {
      continue
    }
    // A folded source form holds buttons of its own, hidden: ours shows.
    await row.locator('.uip-source-selector-toggle--open:visible').first().click()
    await scope.locator(`select[name="${prefix}[source_id]"]`).first().waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(scope)
  }
}

/**
 * A closed prop renders no source selector: its own button loads it.
 *
 * The button sits in the prop row; the innermost row holding the path's own
 * node_id is the right one, whatever the nesting.
 */
export async function openSourceSelector(scope: Locator | Page, name: string): Promise<void> {
  if (!name.endsWith('[source_id]')) {
    return
  }
  await openSlotRows(scope, name)
  if ((await select(scope, name).count()) > 0) {
    return
  }
  await sourceRow(scope, name).locator('.uip-source-selector-toggle--open:visible').first().click()
  await select(scope, name).first().waitFor({ state: 'attached', timeout: 20_000 })
  await openAllDetails(scope)
}

/** The button adding a source row to the slot at this form path. */
export function slotAddButton(scope: Locator | Page, slotName: string): Locator {
  return scope.locator(`[data-uip-slot-add="${slotName}"]`)
}

/** The row a prop or a slot source is configured in. */
export function sourceRow(scope: Locator | Page, name: string): Locator {
  const prefix = name.endsWith('[source_id]') ? name.slice(0, -'[source_id]'.length) : name
  return scope
    .locator(`[id*="ui-patterns-prop-row"]:has([name="${prefix}[node_id]"]), .uip-slot-row:has([name="${prefix}[node_id]"])`)
    .last()
}

const escapeRegExp = (s: string): string => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

/** The deltas of the slot's own rows; nested slots share the name prefix. */
async function directSlotDeltas(scope: Locator | Page, slotName: string): Promise<number[]> {
  const names: (string | null)[] = await scope
    .locator(`[name^="${slotName}[sources]["][name$="[node_id]"]`)
    .evaluateAll(els => els.map(el => el.getAttribute('name')))
  const re = new RegExp(`^${escapeRegExp(slotName)}\\[sources\\]\\[(\\d+)\\]\\[node_id\\]$`)
  return names.flatMap(n => {
    const m = n?.match(re)
    return m ? [Number(m[1])] : []
  })
}

/**
 * Adds a source to a slot: the Add button appends a row arriving open,
 * then the row's own selector picks the source. Waits for `next` when the
 * pick loads a form over AJAX.
 */
export async function addSlotSource(
  scope: Locator | Page,
  slotName: string,
  option: string | { label: string },
  next?: string,
): Promise<void> {
  await openAllDetails(scope)
  const deltas = await directSlotDeltas(scope, slotName)
  const delta = deltas.length ? Math.max(...deltas) + 1 : 0
  await slotAddButton(scope, slotName).click()
  await value(scope, `${slotName}[sources][${delta}][node_id]`).first().waitFor({ state: 'attached', timeout: 20_000 })
  await openAllDetails(scope)
  const selector = select(scope, `${slotName}[sources][${delta}][source_id]`)
  await selector.selectOption(option)
  // Core disables the select while its switch request runs; a submit posted
  // meanwhile would drop the disabled select from the form data.
  await expect(selector).toBeEnabled({ timeout: 20_000 })
  if (next) {
    await value(scope, next).first().waitFor({ state: 'attached', timeout: 20_000 })
    await openAllDetails(scope)
  }
}

/** Changes a source: opens the selector if needed, then picks the option. */
export async function pickSource(scope: Locator | Page, name: string, option: string): Promise<void> {
  await openSourceSelector(scope, name)
  await select(scope, name).selectOption(option)
}

/** Picks an option and waits for the AJAX rebuild to land the next element. */
export async function pick(scope: Locator | Page, name: string, option: string, next: string): Promise<void> {
  await openAllDetails(scope)
  await pickSource(scope, name, option)
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
export async function assertSlotAddRemove(
  scope: Locator | Page,
  slotName: string,
  option: string,
): Promise<void> {
  await openAllDetails(scope)
  const removes = scope.locator('[name$="_remove"]')
  const before = await removes.count()
  await addSlotSource(scope, slotName, option)
  await expect(removes).toHaveCount(before + 1, { timeout: 20_000 })
  await removes.last().click()
  await expect(removes).toHaveCount(before, { timeout: 20_000 })
}
