# Taxonomy Term Glossary

This flexible module uses a vocabulary (taxonomy) as a glossary.
it auto scans the fields (you configure it to) for uses of taxonomy
terms defined in your vocabulary.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/term_glossary).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/term_glossary).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

### Create glossary vocabulary

If you haven’t created a glossary vocabulary yet, you can do so by visiting
`/admin/structure/taxonomy/add`. Once created, add all the necessary terms to
your new vocabulary.

### Enable the Taxonomy term glossary

To enable the module, go to `/admin/config/glossary` and choose the
taxonomy vocabulary create above to use as a source of highlighted terms.

You can also enable the following options:

- **Match only once per content**: if enabled, will only match a single
occurrence of the term per content.
- **Match only once per field**: if enabled, will only match a single occurrence
of the term per field.
- **Match full-word only**: if enabled, will only match a full-word occurrence
of the term.
- **Match case-sensitive only**: if enabled, will only make a case-sensitive
match of the term.
- **Allow per-term matching options**: if enabled, allows overriding the global
matching options using custom fields on the taxonomy terms.
- **Allow the use of term synonyms**: if enabled, allows adding some synonyms to
the vocabulary terms and have them processed by the glossary (check below for
more details).
- **Exclude self-references**: if enabled, will prevent glossary links when a
term appears in its own content, including synonyms.

### Enable Term glossary filter

Taxonomy term glossary will parse the entity text fields to highlight the
found words.

Go to the display options of the text fields you want to parse in the entity
display configuration forms and check the option **Enable term glossary**.

## Advanced configuration

### Term view mode

You can define the Drupal **view mode** to use when rendering the terms
in a popup. When left empty, the term description will be used.

### JSON term cache duration

Enables browsers and proxies to cache JSON term results for the specified
duration (in seconds) to improve performance.
It only applies to anonymous visitors, because the results depend on what the
visitor is allowed to view.
If left empty, the default cache duration is one hour.
Set to 0 to disable caching entirely.

### Term synonyms

To enable the use of term synonyms, you will need to add a multivalued string
field to your taxonomy vocabulary and select it on the module configuration page
after enabling the **Use term synonyms** configuration option.

### Taxonomy Term Glossary per node submodule

If you want to use the Taxonomy Term Glossary module on a per-node basis, you
can do so by enabling this module and then going to the node type
configuration page (`/admin/structure/types/manage/[your_node_type]`).

- **Enable Term Glossary**: This option allows you to enable or disable the
  Taxonomy Term Glossary for this specific node type
- **Allow to enable/disable glossary per node**: This option allows you to
  enable or disable the Taxonomy Term Glossary on a per-node basis. If enabled,
  you will see a checkbox on the node creation/editing form to enable or disable
  the glossary for that specific node.
- **Default state for glossary per node**: Use this option to define the default
  state of the Taxonomy Term Glossary for this specific node type. If enabled,
  the glossary will be active by default when creating new nodes of this type.
- **Allow vocabulary override**: This option allows you to override the global
  vocabulary used by the Taxonomy Term Glossary for this specific node type.
  If enabled, you will be able to select a different vocabulary from the one
  configured globally or at the field display level, on a per-node basis.


### Per-term matching options

To use per-term matching options, you need to add some of the following
fields on your taxonomy:

- `field_full_word` **(numerical)** to allow overriding the global full-word
matching configuration.
- `field_case_sensitive` **(numerical)** to allow overriding the global
case-sensitivity matching configuration.
- `field_boundary_exceptions` **(text)** to allow overriding the global word
boundary exceptions configuration.

For the **numerical** fields, you can use the following values:

- If the taxonomy term field contains a `0` value, it will disable the global
configuration for this term.
- If the taxonomy term field contains a `1` value, it will enforce the term
matching option over the global one.

Every other value will have no effect, the global matching options will be used.

If not empty, the `field_boundary_exceptions` field value will override the
global configuration for this term.

You finally need to enable the **"Per term matching options"** configuration
option on the module settings page.

### Plugins

The Taxonomy Term Glossary module can now be extended using plugins.

An example submodule has been added to the project to help others create new
plugins. It is located in the `modules/term_glossary_abbr` directory.

It implements a simple handler plugin that creates `ABBR` elements instead of
`SPAN` for matched terms and synonyms in content text.

#### Tippy.js plugin

The **[Tippy.js](https://atomiks.github.io/tippyjs/)** plugin allows to display
your terms descriptions in nice looking tooltips.

You first need to have `popper.js` and `tippy.js` libraries available in your
`/web/libraries` folder of your Drupal installation.

- **popper.js** can be downloaded [here](https://asset-packagist.org/package/npm-asset/popperjs--core).
- **tippy.js** can be downloaded [here](https://asset-packagist.org/package/detail?fullname=npm-asset/tippy.js).

The plugin needs to access the following files, so check the paths are correct:

- `/libraries/popperjs/dist/umd/popper.js`
- `/libraries/tippyjs/dist/tippy.umd.js`
- `/libraries/tippyjs/dist/tippy.css`

When the library files are missing, a warning is displayed when the submodule
is installed, on the status report page and on the glossary configuration
form.

You then have to install the submodule **Taxonomy Term Glossary Tippy.js
Tooltips** containing the plugin we need to display Tippy.js tooltips.

Once installed, go to the Taxonomy Term Glossary module configuration page
(`/admin/config/glossary`) and choose **Tippy.js** in the integration type
select field.

You can then choose the type of HTML tag (`span` or `abbr`) to use to render the
matched terms and which tag attribute to use to store the term description that
will be displayed in the tooltips by **Tippy.js**.

And you should be ready to go.

## Maintainers

- Bálint Nagy - [nagy.balint](https://www.drupal.org/u/nagybalint)
- olivier.br - [olivier.br](https://www.drupal.org/u/olivierbr)
- Taggart Jensen - [taggartj](https://www.drupal.org/u/taggartj)
- Frank Mably - [mably](https://www.drupal.org/u/mably)
