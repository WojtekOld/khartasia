CONTENTS OF THIS FILE
---------------------

 * Introduction
 * AI-generated and AI-modified content
 * Icons used for display
 * Requirements
 * Installation
 * Configuration
 * Similar projects
 * Maintainers

INTRODUCTION
------------

The attribution module provides content attribution and license configuration
for the site and for any fieldable entity as nodes and media. It provides a
set of default licenses and allows for import of additional licenses from the
list of SPDX license list and custom defined licenses.

The list of licenses includes more than 400 licenses including the various
flavours and versions of the Creative Commons and of course the
GNU General Public License.

 * For a list of all supported licenses
   https://spdx.org/licenses/

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/attribution

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/attribution

AI-GENERATED AND AI-MODIFIED CONTENT
-------------------------------------

The attribution field can record whether content is human-created,
AI-generated, or AI-modified, along with the AI tool used, the prompt, and
the prompt editor (for AI-generated or AI-modified content). This is
intended to help sites disclose the origin of AI-generated or
AI-manipulated content, for example to meet EU AI Act Article 50
transparency requirements. The module only provides the data model and
display; it does not enforce any particular disclosure policy.

New fields default to no creation type set (unspecified). Existing fields
and content are not automatically classified; site owners should review and
update existing content themselves.

ICONS USED FOR DISPLAY
----------------------

 * Creative commons icons: see [CC policies](https://creativecommons.org/policies/)
 * AI icons: see [EU proposal](https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content)

REQUIREMENTS
------------

This module requires no other Drupal modules besides Drupal core's Field module.

It requires the composer/spdx-licenses PHP library for importing licenses from the
[SPDX license list](https://spdx.org/licenses/).
This is installed automatically when you require the module via Composer.

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

 * Configure the user permissions in Administration » People » Permissions:

   - Use the Attributions Licenses page at /admin/structure/attribution-license

     List of licenses for usage with the attribution block and fields. Optionally
     import (<em>Add license</em>) from the SPDX list or
     <em>Add (a) custom license</em> (e.g. by a certain stock provider).

 * Configure an attribution block in Administration » Structure » Block layout

   - Use the attribution block and configure a license and disclaimer for
     the site.

 * Configure a field in Administration
   » Structure » Content types » Article » Add field

   - Select field type <em>Attribution</em> and configure display and form
     display accordingly.
   - Configure the field in form display: Select a widget according to the fields you want to display.
   - Configure the field in display: The Default formatter offers several customization settings.
     The HTML formatter renders a structured (`<dl>`) list of all attribution fields. All other formatters
     may be deprecated in a future version of this module.

SIMILAR PROJECTS
----------------

 * Media Attribution
   Attach attribution and license information to media images.
   https://www.drupal.org/project/media_attribution

 * Creative Commons
   Allows users to select and assign a Creative Commons license to a node and
   any attached content, or to the entire site.
   https://www.drupal.org/project/creativecommons

 * License field
   This field can be used on node content types as well as Media entities.
   https://www.drupal.org/project/license_field

 * Creative Commons Widget
   Turns a regular CCK text field into a field for selecting and displaying
   Creative Commons licenses.
   https://www.drupal.org/project/cc_widget

 * Creative Commons Lite
   This module allows users to add Creative Commons license to any type of
   drupal node.
   https://www.drupal.org/project/creativecommons_lite

 * Licenses Vocabulary
   This module takes the inspiration from media_attribution but is not limited
   to media.
   https://www.drupal.org/project/licenses_vocabulary

 * Creative commons field
   Defines a field type for attaching creative commons license types.
   https://www.drupal.org/project/creative_commons
