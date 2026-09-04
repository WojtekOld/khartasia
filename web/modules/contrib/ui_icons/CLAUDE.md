# CLAUDE.md

Guidance for working in the `ui_icons` Drupal module (project `drupal/ui_icons`).

## What this is

Generic icon manager for Drupal. It does not implement the icon system itself:
the Icon API lives in core since 11.1 (`Drupal\Core\Theme\Icon\*`, plugin
manager `plugin.manager.icon_pack`). This module adds the UI on top: a form
element to search and configure an icon, plus integrations (field, media, menu,
CKEditor 5, text filter, UI Patterns, Canvas).

Current branch line is **1.1.x**, Drupal 11.3+ and Drupal 12. The 1.0.x line
shipped an API backport through `ui_icons_backport` and `ui_icons_iconify_api`,
both removed here.

`core_version_requirement: ^11.3 || ^12.0` in every `*.info.yml`, kept in sync.
The floor is the core support policy, not an API need: the Icon API this module
builds on landed in 11.1, but 11.1 and 11.2 lost security coverage in June 2026.
`composer.json` declares no `drupal/core` requirement on purpose: the
drupal.org packaging facade derives it from the info file, so a constraint here
would only be a second place to drift.

Icon packs are declared by any extension in `*.icons.yml`. An icon id is
`pack_id:icon_id`, built with `IconDefinition::createIconId()`. Extractors
`path`, `svg`, `svg_sprite` are core, `font` is provided here.

## Layout

The root module is only the autocomplete form element. Everything else is a
submodule.

| Path | Content |
| --- | --- |
| `src/Element/IconAutocomplete.php` | The `icon_autocomplete` form element. Central piece, rebuilds itself and the extractor settings sub-form through ajax on `autocompleteclose change`. |
| `src/IconSearch.php` | Search service `ui_icons.search`, backs the autocomplete route. |
| `src/IconPreview.php`, `src/Template/IconPreviewTwigExtension.php` | Preview rendering, exposed to Twig as `icon_preview()`. |
| `src/Controller/` | Ajax endpoints, see `ui_icons.routing.yml` (`/ui-icons/ajax/autocomplete/icons`, `/ui-icons/ajax/preview/icons`). |
| `templates/icon-selector.html.twig` | Markup of the element. Provides the `.ui-icons-preview-icon` and `.ui-icons-settings-wrapper` hooks used by tests and themes. |
| `src/Hook/UiIconsHooks.php` | `help`, `theme` and `preprocess_icon_selector`. |
| `css/*.icon.autocomplete.css` | Per admin theme overrides (gin, dsfr, daisyui), picked by `UiIconsHooks::preprocessIconSelector()` via its `isThemeActive()` helper. |

Submodules worth knowing:

- `ui_icons_field`: field type `ui_icon`, widgets `icon_widget` / `icon_link_widget`, formatters `icon_formatter` / `icon_link_formatter`.
- `ui_icons_picker`: `icon_picker` element, a modal grid picker alternative to the autocomplete.
- `ui_icons_text`: `icon_embed` filter, turns `<drupal-icon data-icon-id data-icon-settings>` into rendered markup.
- `ui_icons_ckeditor5`: toolbar button and widget producing that `<drupal-icon>` tag. JS source in `js/ckeditor5_plugins/icon/src/`, bundle committed in `js/build/icon.js` (webpack, rebuild after editing source).
- `ui_icons_patterns`: UI Patterns **2.x only**. An `icon` prop type (`IconPropType`) plus source plugins (`IconSource`, `IconRenderableSource`, `FieldIconSource`, `LinkIconSource`). UI Patterns 1.x support is gone: `ui_patterns_settings` 3.x dropped the whole `SettingType` plugin system, so the old `IconSettingType` was deleted. Never reintroduce a `Plugin/UiPatterns/SettingType/` plugin.
- `ui_icons_canvas`, `ui_icons_media`, `ui_icons_menu`, `ui_icons_font`, `ui_icons_library`: integrations, one concern each.

## Tests

PHPUnit tests live in `tests/src/{Unit,Kernel,Functional}` per module and carry
`#[Group('ui_icons')]`, which is what CI filters on. Fixtures come from
`tests/modules/ui_icons_test`, its packs (`test_path`, `test_svg`,
`test_settings`, ...) are declared in `ui_icons_test.icons.yml`. Templates and
setting defaults there are asserted verbatim by tests, so changing that file
breaks tests on purpose.

Make targets run from the project root, they wrap ddev:

```
make unit / kernel / functional / func-js    # phpunit testsuites
make phpunit F=path/to/Test.php              # single file
```

Playwright covers the browser flows. Specs in `tests/src/Playwright/Tests`,
fixtures and the `Drupal` page object in `tests/src/Playwright/{fixtures,objects}`.

```
npm run test          # firefox, @base tagged tests
npm run test:headed   # same, visible browser
npx playwright test -c playwright.local.config.ts tests/src/Playwright/Tests/ckeditor.spec.ts --project=chromium
```

The local config boots a PHP built-in server, and each worker installs its own
Drupal with `core/scripts/test-site.php` driven by `.env` (copy `.env.dist`).
Test setup is done per test with `drupal.installModules()` and
`drupal.drush('php:eval "..."')`, so keep it idempotent: tests in a worker share
one site.

CKEditor 5 icon coverage is Playwright only (`Tests/ckeditor.spec.ts`), the old
`FunctionalJavascript/IconPluginTest.php` was removed.

## QA

Config is local to the module: `.phpcs.xml` (Drupal + DrupalPractice + strict
types required), `phpstan.neon` (level 5), `.phpmd.xml`. Spelling is configured
per job in `.gitlab-ci.yml` (`_CSPELL_IGNORE_PATHS`), there is no `.cspell.json`.
From the project root: `make qa` runs the PHP and lint set,
`make phpcs F=... S=web/modules/custom/ui_icons`, `make phpstan`.

**There is no PHPStan baseline.** Level 5 is clean, and
`reportUnmatchedIgnoredErrors: true` makes a dead `ignoreErrors` entry an error,
so keep it that way instead of suppressing new findings. This only holds with
the module's require-dev installed: without `linkit` and `link_attributes` you
get ~66 bogus `class.notFound` errors from the field submodules.

`phpstan.neon` excludes files depending on contrib that is *not* in require-dev
(ui_patterns, canvas). If you add such a file, add it there too.

`.phpmd.baseline.xml` holds exactly two entries, `CouplingBetweenObjects` on
`IconEmbed` and `IconAutocomplete`. Both are inherent to what those classes are
(a filter plugin and a form element referencing a lot of core), so they are
accepted rather than designed around. Everything else must stay under the
thresholds, do not grow the baseline. Its paths are stored `/builds/project/ui_icons/...`
and rewritten by CI, so it will not match when run locally.

## Conventions

- `declare(strict_types=1);` everywhere, enforced by phpcs.
- All hooks are `#[Hook]` classes in `src/Hook/<CamelCaseModule>Hooks.php`, one
  per module, autowired. There is no `.module` file left in the project and
  none should come back.
- Icon settings are always keyed by pack id (`[$pack_id => $settings]`) when
  passed as `#default_settings`. Flattening them silently empties the extractor
  sub-form.
- Never hand write markup for an icon, render through the icon pack template.
