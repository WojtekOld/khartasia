import { mergeTests } from '@playwright/test'
import { drupalSite, drupal, beforeAllTests, beforeEachTest } from './DrupalSite'

export const test = mergeTests(drupalSite, drupal, beforeAllTests, beforeEachTest)
