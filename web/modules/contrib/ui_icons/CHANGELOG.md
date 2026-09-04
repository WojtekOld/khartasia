## [2.0.0] - 2026-08-03

### 🚀 Features

- [#3591878](https://www.drupal.org/project/ui_icons/issues/3591878) Add Drupal Canvas integration for icon_widget
- [#3591879](https://www.drupal.org/project/ui_icons/issues/3591879) Make the ui_icon field/widget editable as a Drupal Canvas component input
- [#3469108](https://www.drupal.org/project/ui_icons/issues/3469108) UI Icons CKEditor 5: update icon
- Add update function to remove obsolete UI Icons modules

### 🐛 Bug Fixes

- [#3591875](https://www.drupal.org/project/ui_icons/issues/3591875) Cannot uninstall UI Icons Fields and UI Icon Picker
- [#3591877](https://www.drupal.org/project/ui_icons/issues/3591877) IconType field type silently breaks main-property consumers: declare target_id as the main property
- [#3591883](https://www.drupal.org/project/ui_icons/issues/3591883) fix: Icon picker icons are vertically misaligned under Gin admin theme
- [#3591880](https://www.drupal.org/project/ui_icons/issues/3591880) Drupal 12 compatibility fixes, playwright tests, changelog to prepare release and styles
- Update changelog for release 2.0.0, add new features and bug fixes
- Update DRUPAL_TEST_SETUP_FILE path and add Playwright tests for icon picker functionality
- Correct spelling of "pluralised" to "pluralized" in test method name

### 💼 Other

- D12 compat in info.yml
- Resolve "Sites breaks if another modules adds an icon without target_id"
- [#3589768](https://www.drupal.org/project/ui_icons/issues/3589768) Drupal 12 compatibility
- Enhance UI Icons Module Functionality and Code Quality

- Updated CKEditor 5 icon coverage documentation for clarity on QA configurations.
- Refactored IconType class to use getDefaultValueLiteral for better default value handling.
- Improved IconWidget class by specifying item type and optimizing default value assignment.
- Streamlined IconSelectForm by resolving dialog options and enhancing filter functionality.
- Added validation methods in UiIconsTextHooks for allowed attributes and filter order.
- Refactored IconEmbed class to extract settings and attributes from drupal-icon nodes.
- Updated PHPStan configuration to remove unnecessary baseline and improve error reporting.
- Enhanced IconAutocomplete class with better validation and value building logic.
- Introduced UiIconsFontRequirements class to check for Font library dependencies.

### 🚜 Refactor

- Remove deprecated UI Icons modules and related files, bump to v2 as Drupal 11.3+ and 12 targets
- Remove obsolete ui_icons_font module and related files

### 🧪 Testing

- Add unit and kernel tests for ui_icons module functionality

### ⚙️ Miscellaneous Tasks

- Comment out unused variables for next major test configuration in CI
## [1.1.2] - 2026-04-16

### 🚀 Features

- [#3583727](https://www.drupal.org/project/ui_icons/issues/3583727) IconWidget does not pass field description to icon selector element

### 🐛 Bug Fixes

- [#3574207](https://www.drupal.org/project/ui_icons/issues/3574207) Incompatibility with Commerce 3.3.x and possibly with other modules
- [#3585046](https://www.drupal.org/project/ui_icons/issues/3585046) Using the Icon Picker modal causes the window to scroll
- [#3576144](https://www.drupal.org/project/ui_icons/issues/3576144) Can not select icon from link as prop
- [#3576144](https://www.drupal.org/project/ui_icons/issues/3576144) remove double class source

### 🎨 Styling

- Fix phpstan baseline
## [1.1.1] - 2026-02-10

### 🐛 Bug Fixes

- Better not found icon preview message
## [1.1.0] - 2025-10-15

### 💼 Other

- [#3526011](https://www.drupal.org/project/ui_icons/issues/3526011) by pdureau, mogtofu33: Add PluginSettingsInterface::settingsSummary()
- [#3533771](https://www.drupal.org/project/ui_icons/issues/3533771) by anairamzap: less strict Core requirement
- [#3531200](https://www.drupal.org/project/ui_icons/issues/3531200) by grimreaper: Menu link content should remain mandatory
- [#3538036](https://www.drupal.org/project/ui_icons/issues/3538036) chore: update logo
## [1.1.0-beta6] - 2025-06-20

### 🐛 Bug Fixes

- Minor css and style on js

### 💼 Other

- [#3531320](https://www.drupal.org/project/ui_icons/issues/3531320) by mogtofu33: Layout Builder icon field

### 🎨 Styling

- Fix js and css
## [1.1.0-beta5] - 2025-06-18

### 🐛 Bug Fixes

- *(https://www.drupal.org/project/ui_icons/issues/3520203)* Automated Drupal 11 compatibility fixes for ui_icons
- Fix (No issue): fix tests and autocomplete

### 💼 Other

- *(https://www.drupal.org/project/ui_icons/issues/3513731)* Ui_icons_patterns : add tag "widget" to UI Patterns source 'icon'
- [#3527311](https://www.drupal.org/project/ui_icons/issues/3527311) by woldtwerk: fix css selector for picker actions
- [#3494488](https://www.drupal.org/project/ui_icons/issues/3494488) by grimreaper: Field widget: icon position not displayed
- [#3517081](https://www.drupal.org/project/ui_icons/issues/3517081) by plopesc, gxleano: Integrate UI Icons Menu with Navigation
- [#3527052](https://www.drupal.org/project/ui_icons/issues/3527052) by gxleano, mogtofu33: Enhance Icon Field Styling for Better Visual Consistency
- *(https://www.drupal.org/project/ui_icons/issues/3530536)* Provide autocomplete result in a grid

### 🎨 Styling

- Fix style issues
## [1.1.0-beta4] - 2025-02-04

### 💼 Other

- Add icon
- [#3502099](https://www.drupal.org/project/ui_icons/issues/3502099) by huangweiqiu, mogtofu33: conflict with gin theme
## [1.1.0-beta3] - 2025-01-24

### 💼 Other

- [#3501905](https://www.drupal.org/project/ui_icons/issues/3501905) by pdureau, just_like_good_vibes, mogtofu33: Incorrect composer dependency for 1.1.x

### 📚 Documentation

- Fix generated doc site
- Fix wrong menu
## [1.1.0-beta2] - 2025-01-17

### 💼 Other

- [#3495080](https://www.drupal.org/project/ui_icons/issues/3495080) by grimreaper, mogtofu33: Icon autocomplete: ajax progress
- [#3495026](https://www.drupal.org/project/ui_icons/issues/3495026) by stephane aimar, mogtofu33: Remove Unbreakable space in Icons Menu
- [#3492751](https://www.drupal.org/project/ui_icons/issues/3492751) by bspeare, mogtofu33: Permissions to the ui_icons_picker...
- [#3495859](https://www.drupal.org/project/ui_icons/issues/3495859) by grimreaper, mogtofu33: update doc, branch compatibility, incompatibility, link to Drupal doc
- [#3492255](https://www.drupal.org/project/ui_icons/issues/3492255) by grimreaper, mogtofu33: Add an icon for the icon field type
- [#3493153](https://www.drupal.org/project/ui_icons/issues/3493153) by grimreaper, mogtofu33: Config schema miss
- [#3499917](https://www.drupal.org/project/ui_icons/issues/3499917) by mogtofu33: Icon picker library display
- *(https://www.drupal.org/project/ui_icons/issues/3500444)* Field link icon shown if ui_icons_menu is enabled with link display
- *(https://www.drupal.org/project/ui_icons/issues/3500444)* Qa fix
## [1.1.0-beta1] - 2025-01-09

### 🐛 Bug Fixes

- Missing method createMockIcon
- Missing method createMockIcon, forgot use

### 💼 Other

- [#3494720](https://www.drupal.org/project/ui_icons/issues/3494720) by mogtofu33, pdureau, grimreaper: Drupal 11.1 compatibility
- [#3498368](https://www.drupal.org/project/ui_icons/issues/3498368) by steveoriol, mogtofu33: Settings in the formatter icon are not saved
## [1.0.0-beta3] - 2024-12-05

### 🐛 Bug Fixes

- Hotfix library component attributes
- Double icon check on embed
- Search on direct icon id
- Ajax naming of element
- Service and collector
- Fixes
- Patterns 2 keys fix
- Patterns 2 fallback fix
- Update patterns component test
- Missing check on formatter for settings as array
- Backport required for drupal <= 11.0

### 💼 Other

- [#3481526](https://www.drupal.org/project/ui_icons/issues/3481526) by pdureau: UI Patterns integration: fix warning
- [#3480422](https://www.drupal.org/project/ui_icons/issues/3480422) by tomsaw: Improve css in library view by using grid auto-fill
- [#3484392](https://www.drupal.org/project/ui_icons/issues/3484392) by smustgrave, pdureau: ui_icons_patterns, from machine_name to string
- [#3484351](https://www.drupal.org/project/ui_icons/issues/3484351) by mogtofu33: Backport Icon API
- [#3487275](https://www.drupal.org/project/ui_icons/issues/3487275) by mogtofu33: Form element autocomplete search
- [#3487207](https://www.drupal.org/project/ui_icons/issues/3487207) by mogtofu33: Extractor font and iconify update, library update, isolate search
- [#3485067](https://www.drupal.org/project/ui_icons/issues/3485067) by steveoriol, g4mbini, mogtofu33, grimreaper: Icons on menu do not works
- [#3484282](https://www.drupal.org/project/ui_icons/issues/3484282) by grimreaper, mogtofu33: Media source plugin
- [#3488557](https://www.drupal.org/project/ui_icons/issues/3488557) by mogtofu33: Gitlab CI issue on some tests and phpmd
- [#3488558](https://www.drupal.org/project/ui_icons/issues/3488558) by just_like_good_vibes, grimreaper, mogtofu33: Add a composer.json file
- [#3489123](https://www.drupal.org/project/ui_icons/issues/3489123) by grimreaper, mogtofu33: Icon not found when rendered from renderable array
- [#3489673](https://www.drupal.org/project/ui_icons/issues/3489673) by grimreaper: regression with ui_icons_menu
- [#3488789](https://www.drupal.org/project/ui_icons/issues/3488789) by pdureau, just_like_good_vibes, mogtofu33: Use #allowed_icon_pack with UI Patterns 2
- [#3489855](https://www.drupal.org/project/ui_icons/issues/3489855) by mogtofu33: Beta last issues before release
- [#3490520](https://www.drupal.org/project/ui_icons/issues/3490520) by mogtofu33: Backport of API and fix for beta3
- [#3490520](https://www.drupal.org/project/ui_icons/issues/3490520) by mogtofu33: Backport of API and fix for beta3
- *(https://www.drupal.org/project/ui_icons/issues/3490520)* Backport of API and fix for beta3
- [#3491590](https://www.drupal.org/project/ui_icons/issues/3491590) by mogtofu33: CI job test coverage
- [#3491700](https://www.drupal.org/project/ui_icons/issues/3491700) by just_like_good_vibes, mogtofu33: Add new UI patterns source for fields
- [#3482495](https://www.drupal.org/project/ui_icons/issues/3482495) by grimreaper, mogtofu33: Linkit with attributes with icon

### 📚 Documentation

- Update general doc, remove API only part

### 🎨 Styling

- Typo and commented code fixed.
- Fix phpstan main filename and unused variable
- Fix test style
- Move phpstan error to baseline

### 🧪 Testing

- Add a preview test

### ⚙️ Miscellaneous Tasks

- Update to sync api in core
- Update last core mr
- Init composer.json and fix phpstan config
- Backport api
- Update based on current core mr
- Disable coverage job for now as it's slow
## [1.0.0-beta2] - 2024-10-17

### 🐛 Bug Fixes

- Allowed icon pack format for field

### 💼 Other

- Add FontExtractor
- [#3476941](https://www.drupal.org/project/ui_icons/issues/3476941) by mogtofu33: CKeditor limited allowed HTML
- [#3474947](https://www.drupal.org/project/ui_icons/issues/3474947) by mogtofu33: IconPicker issues
- [#3477003](https://www.drupal.org/project/ui_icons/issues/3477003) by grimreaper, mogtofu33: Fatal error on 1.0.0-beta1
- [#3477049](https://www.drupal.org/project/ui_icons/issues/3477049) by grimreaper, mogtofu33: Fatal error in ui_icons_patterns
- [#3477078](https://www.drupal.org/project/ui_icons/issues/3477078) by mogtofu33: Refactor IconFinder
- *(https://www.drupal.org/project/ui_icons/issues/3478430)* Refactor manager and finder
- [#3478116](https://www.drupal.org/project/ui_icons/issues/3478116) by mogtofu33, pdureau: Rename ui_icon render element to icon
- [#3477435](https://www.drupal.org/project/ui_icons/issues/3477435) by mogtofu33: Autocomplete element theme
- Debug code
- [#3481067](https://www.drupal.org/project/ui_icons/issues/3481067) by pdureau: Make twig function more tolerant
- [#3480910](https://www.drupal.org/project/ui_icons/issues/3480910) by mogtofu33, pdureau: Rename *.ui_icons.yml to *.icons.yml
- [#3480536](https://www.drupal.org/project/ui_icons/issues/3480536) by grimreaper: UI Icons: change libraries paths
- [#3481411](https://www.drupal.org/project/ui_icons/issues/3481411) by grimreaper: UI Icons: UI Patterns Library 2.x permission update
- [#3466827](https://www.drupal.org/project/ui_icons/issues/3466827) by mogtofu33, pdureau: Json validation of Icon set definition

### 📚 Documentation

- Update definition
## [1.0.0-beta1] - 2024-09-25

### 🚀 Features

- *(link field)* Icon selector choice in link field
- Better no cache based on Twig debug state

### 🐛 Bug Fixes

- Form element settings working with menu
- *(iconify)* Type on collection name
- *(ckeditor)* Deleted icon preview
- *(schema)* Add field settings, fix widget
- *(schema)* Clean color
- *(template)* Default template clean_class
- *(picker)* Update doc and change placeholder
- *(lib)* Missing dependencies
- *(text)* Allow star for drupal-icon attributes
- *(library)* Better list with natsort

### 💼 Other

- [#3470447](https://www.drupal.org/project/ui_icons/issues/3470447) by pdureau, mogtofu33: Field formatters compatibility with Layout Builder
- [#3470547](https://www.drupal.org/project/ui_icons/issues/3470547) by mogtofu33, pdureau: Check if ui_icons_field is working with views
- [#3470448](https://www.drupal.org/project/ui_icons/issues/3470448) by pdureau, mogtofu33: Move storage logic from Field Widget to Field Type
- [#3470960](https://www.drupal.org/project/ui_icons/issues/3470960) by mogtofu33: Allow icon picker selection
- [#3470807](https://www.drupal.org/project/ui_icons/issues/3470807) by mogtofu33, sea2709: Insert Icon button is disabled in CKEditor 5
- [#3469135](https://www.drupal.org/project/ui_icons/issues/3469135) by mogtofu33: Add a Plugin Extractor with API
- [#3472999](https://www.drupal.org/project/ui_icons/issues/3472999) by mogtofu33, pdureau: [1.0.0-beta1] Add color setting
- [#3472598](https://www.drupal.org/project/ui_icons/issues/3472598) by mogtofu33: CKEditor Icon link
- [#3474554](https://www.drupal.org/project/ui_icons/issues/3474554) by pdureau, mogtofu33: Rename *BaseInterface
- [#3474541](https://www.drupal.org/project/ui_icons/issues/3474541) by pdureau: SvgSpriteExtractor XML structure and remote URL
- [#3475202](https://www.drupal.org/project/ui_icons/issues/3475202) by mogtofu33, pdureau: Clean code, no default template, better preview
- [#3476776](https://www.drupal.org/project/ui_icons/issues/3476776) by mogtofu33: Beta1 release tasks

### 🚜 Refactor

- *(iconify)* Cleaner code
- *(autocomplete)* Cleaner code

### 📚 Documentation

- Better readme with more information
- Update documentation and modules info
- *(help)* Fix link to readme
- Update and fix names
- Add mkdocs
- Update doc link

### 🎨 Styling

- Style(): fix text consistency
- Fix styling and phpstan
- Minor refactor around consistency, remove magic numbers
- Phpstan ignore tests and phpcs line length
- Return types and double quotes
- *(doc)* Clean and fix doc
- Missing interface
- Tests doc fix
- Fix tests and minor style issues
- *(phpstan)* Raise level and fix new errors
- *(phpmd)* Add rules and baseline
- *(phpmd)* Wrong config name
- *(phpmd)* Update rules and use baseline
- *(phpmd)* Fix ci path for baseline

### 🧪 Testing

- *(iconify)* Create tests
- *(field)* Add functional, better kernel form value
- *(field)* Fix path in ci by using only filename
- *(ckeditor)* Add icon plugin functional test
- Add ckeditor controller and text filter tests
- Fix and doc
- Fix test not performing anything
- Add menu,fix ckeditor plugin theme
- Fix failing natsort list options

### ⚙️ Miscellaneous Tasks

- *(qa)* Cspell clean list
- *(iconify)* Split extractor and icons examples
- Move manual extractor to test, update finder test
- *(ci)* Enable phpmd

### ◀️ Revert

- *(cfb89d9a)* Cache based on twig dev
## [1.0.0-alpha3] - 2024-08-27

### 🚀 Features

- Icon definition for twig include full id

### 🐛 Bug Fixes

- Reduce search min length to 2 chars
- Menu not working fix
- Better library
- Minor fixes
- Required is not json valid
- Library gap and title
- *(ci)* Minor fixes to pass ci

### 💼 Other

- [#3468577](https://www.drupal.org/project/ui_icons/issues/3468577), [#3468578](https://www.drupal.org/project/ui_icons/issues/3468578) by mogtofu33: Field and field link multiple and default value
- [#3469119](https://www.drupal.org/project/ui_icons/issues/3469119) by mogtofu33: Field formatter details, no required in settings
- [#3466324](https://www.drupal.org/project/ui_icons/issues/3466324) by mogtofu33, grimreaper: [1.0.0-alpha3] Config Schema: flatten structure
- [#3466256](https://www.drupal.org/project/ui_icons/issues/3466256) by pdureau, mogtofu33: Add UI Patterns integration module
- [#3469138](https://www.drupal.org/project/ui_icons/issues/3469138) by mogtofu33: Fancy Icon picker
- [#3469117](https://www.drupal.org/project/ui_icons/issues/3469117) by pdureau, mogtofu33: Make Settings JSON schema compliant

### 🎨 Styling

- JS behavior name
- Js use strict
- Minor template class fix
- Fix eslint
- Fix js variable name

### 🧪 Testing

- Fix new icon_full_id on definition
## [1.0.0-alpha2] - 2024-08-16

### 🚀 Features

- Add hero icons as example
- Move twig logic to preprocess for icon input

### 🐛 Bug Fixes

- Minor fixes, phpstan config, yml prettier ignore
- *(type)* Type error and return
- Schema type error failing widget save allowed iconset
- Config objects simplier
- *(autocomplete)* Allow input #value for icon
- *(finder)* Better suffix handling
- Minor css and js fix
- Add default template default size
- Better iconset definition id error message
- Field multiple add another previous icon disappear
- Change name selector
- Better autocomplete form element
- Minor logic and variable name
- Library and search when no icons
- Better search by words
- Icon element settings instead of context
- Icon form allowed and default fix
- Better icons loaded in pack manager

### 💼 Other

- [#3466339](https://www.drupal.org/project/ui_icons/issues/3466339) by pdureau, mogtofu33: [1.0.0-alpha2] SvgExtractor: Avoid use of raw filter
- [#3466334](https://www.drupal.org/project/ui_icons/issues/3466334) by pdureau, Grimreaper, mogtofu33: [1.0.0-alpha2] Drupal Core requirement
- [#3466878](https://www.drupal.org/project/ui_icons/issues/3466878) by pdureau, mogtofu33: [2.0.0-alpha2] Don't show icon settings...
- [#3466907](https://www.drupal.org/project/ui_icons/issues/3466907) by mogtofu33, pdureau: [1.0.0-alpha2] Rename Icon packs and UIIcon
- *(https://www.drupal.org/project/ui_icons/issues/3466824)* [1.0.0-alpha2] Handle icon id and friendly name
- [#3467776](https://www.drupal.org/project/ui_icons/issues/3467776) by mogtofu33: [1.0.0-alpha3] Form element settings validation
- [#3466252](https://www.drupal.org/project/ui_icons/issues/3466252) by pdureau: Move icon library to /admin/appearance/ui-libraries

### 📚 Documentation

- Todo and better autocomplete
- Add group for icon packs
- Update todos

### 🎨 Styling

- Fix most Drupal ci errors
- Ignore phpstan unknown trait
- Fix ignore build and webpack files
- Ignore eslint unresolved, ignore phpstan hook_help return
- Eslint rule disable fix comma
- Clean unneeded service
- Ci, typo
- Proper variable naming

### 🧪 Testing

- Fix ci module path
- Update test for paths on CI

### ⚙️ Miscellaneous Tasks

- *(ci)* Init Drupal CI
- Add more examples
- Rename extractor getIcons to avoid mix with icon pack manager
- Better invalid icon message
- Library: fix form and display
- Fix examples
## [1.0.0-alpha1] - 2024-08-06

### 💼 Other

- Initial commit.

### 🧪 Testing

- Update tests, create base and fix namespace
