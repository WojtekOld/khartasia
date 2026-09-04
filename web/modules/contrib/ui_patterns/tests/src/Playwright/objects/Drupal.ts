import { expect, Page } from '@playwright/test'
import { exec, execDrush } from '../utilities/DrupalExec'
import * as nodePath from 'node:path'
import * as fs from 'node:fs'
import { getRootDir } from '../utilities/DrupalFilesystem'
import type { DrupalSite } from '../fixtures/DrupalSite'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

/**
 * Utility methods for interacting with a Drupal site during Playwright tests.
 *
 * Supports both Drush-based and UI-based operations for common tasks such as
 * authentication and module installation.
 */
export class Drupal {
  readonly page: Page
  readonly drupalSite: DrupalSite

  constructor({ page, drupalSite }: { page: Page; drupalSite: DrupalSite }) {
    this.page = page
    this.drupalSite = drupalSite
  }

  /**
   * Sets the cookie which determines which simpletest multisite to use.
   */
  async setTestCookie(): Promise<void> {
    const context = this.page.context()
    const simpletestCookie = {
      name: 'SIMPLETEST_USER_AGENT',
      value: encodeURIComponent(this.drupalSite.userAgent),
      url: this.drupalSite.url,
    }
    await context.addCookies([simpletestCookie])
  }

  /**
   * Gets drupalSettings from the browser window object.
   */
  async getDrupalSettings() {
    // cspell:ignore domcontentloaded
    await this.page.waitForLoadState('domcontentloaded')
    await this.page.waitForTimeout(100)

    return await this.page.evaluate(() => {
      return window.drupalSettings || undefined
    })
  }

  hasDrush(): boolean {
    return this.drupalSite.hasDrush
  }

  async drush(command: string): Promise<string> {
    return await execDrush(command, this.drupalSite)
  }

  async loginAsAdmin(uid: number = 1): Promise<void> {
    utils.debug('Login with Drush...')
    const logInUrl = await this.drush(`user:login --uid=${uid} --no-browser`)
    await this.page.goto(logInUrl)
  }

  async login(
    { username, password }: { username: string; password?: string } = {
      username: this.drupalSite.username,
      password: this.drupalSite.password,
    },
  ): Promise<void> {
    if (!this.drupalSite.hasDrush && !password) {
      throw new Error('Password is required when drush is not available.')
    }
    const page = this.page
    if (this.drupalSite.hasDrush) {
      const loginUrl = await this.drush(`user:login --name=${username} --no-browser`)
      await page.goto(loginUrl)
    } else {
      await page.goto(`${this.drupalSite.url}/${config.logInUrl}`)
      await page.locator('[data-drupal-selector="edit-name"]').fill(username)
      await page.locator('[data-drupal-selector="edit-pass"]').fill(password ?? 'test_admin')
      await page.locator('[data-drupal-selector="edit-submit"]').click()
    }
    await expect(page.locator('h1')).toHaveText(username)
  }

  /**
   * Gets the uid of the currently logged in user.
   */
  async getUserId(): Promise<number> {
    const drupalSettings = await this.getDrupalSettings()
    if (drupalSettings && drupalSettings.user && drupalSettings.user.uid) {
      return parseInt(drupalSettings.user.uid, 10)
    }
    return 0
  }

  async installModules(modules: string[]): Promise<void> {
    if (this.drupalSite.hasDrush) {
      await this.drush(`pm:enable ${modules.join(' ')}`)
    } else {
      await this.page.goto(config.modules)
      for (const module of modules) {
        await this.page.locator(`input[name="modules[${module}][enable]"]`).check()
      }
      await this.page.locator('[data-drupal-selector="edit-submit"]').click()
      if (await this.page.locator('[data-drupal-selector="system-modules-confirm-form"]').count()) {
        await this.page.locator('[data-drupal-selector="edit-submit"]').click()
      }
    }
  }

  async enableTestExtensions() {
    const settingsFile = nodePath.resolve(getRootDir(), `${this.drupalSite.sitePath}/settings.php`)
    fs.chmodSync(settingsFile, 0o775)
    return await exec(`echo '$settings["extension_discovery_scan_tests"] = TRUE;' >> ${settingsFile}`)
  }

  async ajaxReady(): Promise<void> {
    await expect(this.page.locator('.ajax-progress, .ajax-progress--throbber, .ajax-progress--message')).toHaveCount(0)
  }

  async expectMessage(text: string): Promise<void> {
    const message = this.page.getByRole('contentinfo', { name: 'Status message' })
    expect(await message.textContent()).toContain(text)
    // BigPipe delivers the message before the page's last scripts: leaving
    // now aborts their load, which Firefox reports as a page error.
    await this.page.waitForLoadState('networkidle')
  }
}
