# Taxonomy Term Glossary Tippy.js Tooltips

Displays the glossary term descriptions in tooltips using the
[Tippy.js](https://atomiks.github.io/tippyjs/) JavaScript library.

## Requirements

This module requires the `popper.js` and `tippy.js` external libraries,
which are not shipped with the module. They must be available in the
`/libraries` folder of your Drupal installation:

- **popper.js** can be downloaded
  [here](https://asset-packagist.org/package/npm-asset/popperjs--core).
- **tippy.js** can be downloaded
  [here](https://asset-packagist.org/package/detail?fullname=npm-asset/tippy.js).

The module needs to access the following files, so check the paths are
correct:

- `/libraries/popperjs/dist/umd/popper.js`
- `/libraries/tippyjs/dist/tippy.umd.js`
- `/libraries/tippyjs/dist/tippy.css`

When the library files are missing, a warning is displayed when the
module is installed, on the status report page and on the glossary
configuration form.

## Configuration

Go to the Taxonomy Term Glossary module configuration page
(`/admin/config/glossary`) and choose **Tooltips with Tippy.js** in the
integration type select field.

See the main [Taxonomy Term Glossary README](../../README.md) for the
full documentation.
