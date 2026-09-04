# Playwright end-to-end tests

Tests for what PHPUnit cannot cover: the forms over AJAX, the contexts each
plugin type gives to the sources, real nesting, and the result on a real page.
Each spec runs one long test per plugin type, doing the same CRUD on the
component form: fill a prop and a slot, switch a source over AJAX, add and
remove a slot source, save, reopen and check everything is kept.

- `Tests/block.spec.ts` (`@block`), block plugin: switch a prop source and
  switch back, add and remove a slot source, the block plugin form inside
  BlockSource, a component inside a slot, then save and reopen.
- `Tests/field_formatter.spec.ts` (`@field_formatter`), field formatter on
  Manage display: the sources offered by the contexts, the chain tags item,
  referenced term, term field, property, the same chain inside a nested
  component, add and remove a slot source, then save, reopen, and the article
  page renders the whole chain.
- `Tests/views.spec.ts` (`@views`), the three places views uses components:
  the style plugin around the rows, the row plugin per row, and the component
  formatter on a views field. Each one configures in the views dialogs,
  applies, saves, reopens, then checks the page the view renders. The views
  field also builds a chain to the referenced term.
- `Tests/layout_builder.spec.ts` (`@layout_builder`), layout builder: a
  component layout as section and an entity component block in its slot, both
  mapped to fields of the layout builder entity, a field formatter inside the
  slot, add and remove a slot source, checkbox and attributes props, then save,
  reopen, and the article page renders. The forms are opened by URL, same forms
  as in the dialog.

Shared helpers live in `objects/`: `Audit` (watchdog and console check),
`Setup` (`enableModules`), `ComponentForm` (`select`, `pick`, `openDetails`,
`assertSlotAddRemove`), `TestContent` (seed the articles). Content for the
entity plugin types comes from `tests/modules/ui_patterns_test_content`
(test_article with tags and a related article, test_tags terms with a color).
All specs carry `@base`.

Every spec renders the saved config on a real page, closing the loop from the
form to the output.

The specs are written for the test site the pipeline installs, not for an
existing site: they place blocks, switch displays and edit views, and they
expect the pages a fresh install serves.

## Setup

```bash
npm install
npx playwright install chromium firefox
cp .env.example .env   # then uncomment ONE case
```

- **Case 1** (CI-like): tests install Drupal themselves via
  `core/scripts/test-site.php` using `tests/src/TestSite/PlaywrightTestSetup.php`.
- **Case 2** (existing site, e.g. DDEV): `DRUPAL_TEST_SKIP_INSTALL=true` +
  `DRUPAL_TEST_DRUSH_PREFIX='ddev'`; required modules are enabled over drush.

Both cases need `$settings['extension_discovery_scan_tests'] = TRUE;`.

## Run

```bash
npm run test               # firefox
npx playwright test --project=chromium -g @base
npm run test:headed        # watch the browser
npm run test:ui            # step through in the Playwright inspector
```

Case 1 is the fast one: a fresh site per run, about 20 seconds. From the
Drupal web root, in another terminal:

```bash
PHP_CLI_SERVER_WORKERS=8 php -S localhost:8000 .ht.router.php
```

Case 2 on DDEV takes minutes, every drush call goes through `ddev`.

## CI

`.gitlab-ci.yml` runs the `@base` specs on Chromium in a blocking `playwright`
job (Case 1, one test site per worker), then `merge_reports` publishes the
junit and HTML reports. Set the `SKIP_E2E_TESTS` variable to `1` to skip both.
The image pins the Playwright browsers, so bump `@playwright/test` and the
image digest together.
