import { expect, Page } from '@playwright/test'
import type { Drupal } from './Drupal'

/**
 * PHP errors in watchdog and JS errors in the browser during a test.
 *
 * start() in the first step, expectClean() in the last one. JS errors are
 * kept only for the pages given by `audited`: pages rendered in the site
 * theme run scripts that are not under test.
 */
export class Audit {
  private watchdogMark = '0'
  private readonly jsErrors: string[] = []

  constructor(
    private readonly page: Page,
    private readonly drupal: Drupal,
    audited: (url: string) => boolean,
  ) {
    page.on('pageerror', err => {
      if (audited(page.url())) {
        this.jsErrors.push(`pageerror: ${String(err)}`)
      }
    })
  }

  async start(): Promise<void> {
    // Through Drupal: test sites prefix every table and drush sql:query does
    // not know the prefix.
    this.watchdogMark = (
      await this.drupal.drush(`ev "print \\Drupal::database()->query('SELECT COALESCE(MAX(wid), 0) FROM {watchdog}')->fetchField();"`)
    ).trim()
  }

  async expectClean(): Promise<void> {
    const log = await this.drupal.drush(
      `ev "print implode(PHP_EOL, array_map('json_encode', \\Drupal::database()->query('SELECT type, message, variables FROM {watchdog} WHERE wid > ${this.watchdogMark} AND severity <= 4 AND type = :type', [':type' => 'php'])->fetchAll()));"`,
    )
    const lines = log.split('\n').filter(l => l.trim().length > 0)
    expect(lines, `PHP warnings/errors during the run:\n${lines.join('\n')}`).toHaveLength(0)
    expect(this.jsErrors, `JS errors:\n${this.jsErrors.join('\n')}`).toHaveLength(0)
  }
}
