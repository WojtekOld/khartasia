import { defineConfig, devices } from '@playwright/test'

import dotenv from 'dotenv'
import path from 'path'

dotenv.config({ path: path.resolve(__dirname, '.env'), quiet: true })

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './tests/src/Playwright',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  // One worker on CI, and on an existing site (Case 2): specs share it.
  // Fresh installs get one site per worker and can run in parallel.
  workers: process.env.CI || process.env.DRUPAL_TEST_SKIP_INSTALL ? 1 : undefined,
  reporter: process.env.CI
    ? [['dot'], ['html', { open: 'never' }], ['junit', { outputFile: 'test-results/playwright.xml' }]]
    : [['list', { printSteps: true }], ['html']],
  /* https://playwright.dev/docs/test-timeouts */
  timeout: process.env?.DRUPAL_TEST_SKIP_INSTALL ? 300_000 : 360_000,
  use: {
    /* Playwright requires the ending slash. */
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: {
      mode: 'only-on-failure',
      fullPage: true,
    },
    video: {
      mode: 'retain-on-failure',
      size: { width: 1280, height: 900 },
    },
    actionTimeout: process.env.CI ? 10_000 : process.env.DRUPAL_TEST_SKIP_INSTALL ? 8_000 : 20_000,
  },
  projects: [
    {
      name: 'setup',
      testMatch: /global\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        deviceScaleFactor: 1,
        viewport: { width: 1920, height: 1080 },
      },
      dependencies: ['setup'],
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        deviceScaleFactor: 1,
        viewport: { width: 1920, height: 1080 },
      },
      dependencies: ['setup'],
    },
  ],
})
