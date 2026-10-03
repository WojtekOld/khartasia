External Entities File and Image Fields Support
***********************************************

This module enables the use of Drupal file and image fields with external entity
types using file URI (path or URL) as file source instead of a database file
identifier.

With the provided fiel field mapper, it becomes possible to use Drupal file and
image features on external file/image fields. For instance image styles can be
used on image fields: remote images can be processed on the local Drupal
server to generate corresponding thumbnails.

===============================

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * How it works
 * Security
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * Maintainers

INTRODUCTION
------------

Drupal file and image fields are only working with file objects stored in Drupal
database. Those fields use a database file identifier rather than a path or a
URL. Therefore, since external entity data is not stored into Drupal database,
those fields can't be used natively with external entities.

To get around this problem, a first solution was to use the
[ImageCache External](https://www.drupal.org/project/imagecache_external) module
for image URLs provided by remote content. While it allowed to display images,
that module relies on the link field type and it was not possible to use other
module features that work on file and image fields (image styles, some views
options, Flex Slider, etc.).

This module provides a way to use file path or URL provided by an external
entity source as a file or image field. To use it, just add a file or image
field to your external entity type and use the "File field mapper" for that
field in the external entity type "Field mapping" section. You are only required
to map the "External file URI" field property, but you may also map other
properties (ie. "Title" or "Alternative text" for images for instance).

To ensure the external source does not provide invalid URIs, you may specify
a regular expression to validate the URIs (ie. "Regular expression to filter
allowed URI (optional)"). For instance, you may restrict to secure HTTP URL
using "https://.*", restrict to jpeg files using ".*\.jpg", or both using
"https://.*\.jpg". It is strongly encouraged to specify a regular expression to
filter for security reasons: if the external source is compromised, it could
provide crafted URIs to try to access to private or local files (unless your
site only supports the 'public://' file stream).

Since some file URL may not contain the file extension while the file extension
is required by Drupal to determine the file (mime) type, it is possible to
"force" the file extension and append virtually that extension to the remote
file so Drupal can validate the file type and use it.

HOW IT WORKS
------------

Since Drupal file and image fields are mapped to managed file identifiers, this
module uses and intercepts special file field identifiers (prefixed by "xntt-")
to manage external files. Those special identifiers follow the form
"xntt-[xntt_type_name]-[xntt_instance_id]-[file_field_name]#[delta]" are used to
generate a file path using the stream wrapper "xntt:". From that path, the
stream wrapper is able to retrieve the corresponding external entity instance
that holds the field containing the file path or URL and then serve that file.

However, using string for file identifiers is not supported by Drupal by
default. Therefore, many adjustements were made to get arround that problem,
especially in form validation. Many classes were added to either replace of wrap
existing file classes and only have the original class behavior used for regular
files.

Hooks:
- hook_entity_preload
  Used to load external files before they could get treated as managed files,
  and avoid having Drupal trying to load them as managed files.
- hook_entity_storage_load
  Dynamically crafts external file identifiers to have them loaded by
  hook_entity_preload implementation.
- hook_field_widget_single_element_form_alter
  Alters external entity editing forms using the file field mapper and replaces
  their 'managed_file' form elements by 'external_file' form elements.
- xntt_file_field_field_info_alter
  Used to adjust constraint validations on external entity saving.
- xntt_file_field_entity_field_storage_info_alter
  Changes file reference type to external file reference to avoid validation
  errors.

Classes:
- Drupal\xntt_file_field\Element\ExternalFile
  'external_file' form element used to wrap 'managed_file' form element.
  It restricts file validation to Drupal managed files only. It also adds the
  "External URI" field and remove file upload/remove buttons for external files.
- Drupal\xntt_file_field\Entity\Query\*
  Provides query service on external file entities. Used when uninstalling the
  module.
- Drupal\xntt_file_field\Entity\ExternalFile
  Wraps the Drupal 'File' entity for external files, allows the use of string
  identifier for external file entities, and limit operations on external files.
- Drupal\xntt_file_field\FileUsage
  Provides a service that wraps the 'file.usage' service to support external
  files. External file usage can not be monitored while Drupal managed files
  can. This service just falls back on the original 'file.usage' for Drupal
  managed files, and provides a different behavior for external files.
- Drupal\xntt_file_field\Plugin\EntityReferenceSelection\ExternalFileSelection
  Overrides file selection to allow selection on unknown external files.
- Drupal\xntt_file_field\Plugin\Field\FieldType\ExternalFileFieldItemList
  Remove valid reference constraint for external files when saving an external
  entity.
- Drupal\xntt_file_field\Plugin\Validation\Constraint\ExternalFileValidationConstraint
  and ExternalFileValidationConstraintValidator
  This constraint is used to skip external file identifiers validation when
  saving an external entity.
- Drupal\xntt_file_field\Plugin\Validation\Constraint\ExternalReferenceAccessConstraint
  and ExternalReferenceAccessConstraintValidator
  This constraint is used to skip external file access validation when
  saving an external entity.
- Drupal\xntt_file_field\StreamWrapper\XnttStream
  Stream wrapper used to support he 'xntt://' stream that links a special path
  to a given external entity instance file field item. It is used internally to
  load external files according to their (string) identifiers.
- Drupal\xntt_file_field\ExternalFileStorage
  Storage used to manage external files.

SECURITY
--------

Since it is possible to map any local file, the interface allows to use a
regular expression pattern to filter provided external file path and only allow
those passing the filter. Therefore, it is up to the person who defines the
mapping through the external entity type definition to limit access to whatever
part of the local file system or remote URLs.

For instance, if an external source provides a crafted URI like
'private://some_private_file.txt', it may expose private files. Either you trust
your external source and believe it can not be compromised and will only provide
'https://...' kind of URIs (...but it is better to trust no one ;) ), or you
want to make sure only 'https://some.server.com/...' or
'http://some.server.com/...' URIs are provided and you can specify the following
regular expression: 'https?://some\.server\.com/.*'.
Explanations:
- The full regexp uses '#' delimiters and includes '^' and '$' and those must
  need to be provided in the regexp (ie. '#^' . $your_regexp . '$#').
- 'https?' matches both 'http' and 'https'.
- '//:' matches the end of the protocol. No need to escape slashes as we use '#'
  as regexp delimiters.
- 'some\.server\.com/': we need to escape dots which are considered as wildcards
  by regexp otherwise.
- '.*' matches the rest of the URI which can be whatever. We need to match it as
  we end the regexp by '$' to match the whole provided URI against the regexp.

Furthermore, this module does not allow any original file alteration but only
read access. However, for images, you can use image styles to generated modified
cached local images.

REQUIREMENTS
------------

This module requires the following modules:

 * [External Entities](https://www.drupal.org/project/external_entities) v3+

INSTALLATION
------------

 * This module is a sub-module of the External Entities module. Just enable it
   either through the "Extend" admin UI or using drush.

CONFIGURATION
-------------

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will just provides a "File field mapper" type to file or
image field types in the "Field mapping" section of an external entity type.

TROUBLESHOOTING
---------------

When an image style is not applyed on an external image while the image can be
displayed if it is not using a style, it might be because the image MIME type
is not detected properly. You can force the MIME type using the field mapper
setting "Force file extension (optional)". We also noticed some JPEG files are
not working: it might be because of their metadata but the real reason has not
been found yet (a local copy of the file can be processed without problems,
meaning the problem is related to the HTTP protocol).

MAINTAINERS
-----------

Current maintainers:
 * Valentin Guignon (guignonv) - https://www.drupal.org/u/guignonv
