# Sophron

Sophron of Syracuse (Greek: _Σώφρων ὁ Συρακούσιος_, fl. 430 BC) was a _writer
of mimes_ ([Wikipedia](https://en.wikipedia.org/wiki/Sophron)).

Sophron of Drupal is a module to enhance MIME type management, based on the
[FileEye/MimeMap](https://github.com/FileEye/MimeMap) library.

## Features

* Enhances Drupal's MIME type detection based on file extension to recognise
  1200+ MIME types from 1600+ file extensions (vs Drupal's 360 MIME types and
  475 file extensions).
* Provides an extensive MIME type management API through [FileEye/MimeMap](https://github.com/FileEye/MimeMap).
* Optionally replaces Drupal's core MIME type extension-based guesser map.
* Provides a JSON extract of the MIME type definitions.

## Installation

* Install the required module packages with Composer. From the Drupal
  installation root directory, type
```
$ composer require drupal/sophron
```
  This will download both the module and any dependency. Composer will
  automatically download the module's release that is most appropriate for the
  version of Drupal currently installed.

* Enable the module. Navigate to _Manage > Extend_. Check the box next to the
  module and then click the 'Install' button at the bottom.

## Override Drupal core MIME type guessing

The Sophron module by itself just provides a service and an API for MIME type
management. You can enable the complimentary **Sophron guesser** module to let
Sophron replace Drupal core's MIME type map, overriding core's guessing
results.

## Alter MIME type mapping at runtime

Sopron can manage alternative MIME type maps through FileEye/MimeMap features,
but also allows runtime changes to the map in use, through three combinable
methods:

* Using the Sophron configuration UI (see below) to indicate any mapping
  changes.
* Via code, implementing event subscribers that react to the
  ```Drupal\sophron\Event\MapEvent::INIT``` event that is **fired by Sophron**
  when a map is initialised, and making the changes through the FileEye/MimeMap
  API.
* When the _Sophron guesser_ module is installed, implementing event subscribers
  that react to the ```Drupal\Core\File\Event\MimeTypeMapLoadedEvent``` event
  that is **fired by Drupal core** when the map is loaded, and making the
  changes through the ```Drupal\Core\File\MimeType\MimeTypeMapInterface``` API.
* **NOTE** The Sophron guesser module does not invoke the legacy (deprecated in
  Drupal 11.2, removed in 12.0) ```hook_file_mimetype_mapping_alter``` hook.

## Retrieve the MIME type definitions as a JSON

Visit ```/sophron/mime-types.json```; it will return a JSON feed of all the MIME
types defined, with their description, aliases and related file extensions.
The ```access mime types json feed``` permission is required to access the URL.

## Configuration

* Go to _Administration » Configuration » System » MIME Types_.

* In the _'Mapping'_ tab, select the most appropriate map for your needs. In
  the _'Mapping commands'_ textbox you can add additional mapping commands (see
  format below) that will be executed at runtime when the selected map is
  initialised (i.e. before any MIME type guessing is performed).
  If there are mapping errors or gaps between Drupal core mappings and the
  selected map mappings, specific reports are provided.

* The _'MIME types'_ tab contains a report of all supported MIME types, with
  their associated file extensions, descriptions, and aliases.

* The _'File extension'_ tab contains a report of all supported file extensions,
  with their associated MIME types and MIME type descriptions.

## Mapping commands

Mapping commands should be entered in the following format, one command per
line:
```
- {method: foo, arguments: [arg1, arg2, ...]}
```
Where _foo_ is a method from the _FileEye\MimeMap\Map\AbstractMap_ class,
and the _argN_ are the method's arguments.

Most useful commands:

* _{method: addTypeExtensionMapping, arguments: [type, extension]}_ adds a
  type-to-extension mapping.
  Example: ```- {method: addTypeExtensionMapping, arguments: [application/dsptype, tsp]}```

* _{method: addTypeAlias, arguments: [type, alias]}_ adds an alias of a
  MIME type.
  Example: ```- {method: addTypeAlias, arguments: [application/atom+xml, application/atom]}```

* _{method: setExtensionDefaultType, arguments: [extension, type]}_ changes
  the default extension for a MIME type.
  Example: ```- {method: setExtensionDefaultType, arguments: [asc, text/plain]}```

* _{method: removeType, arguments: [type]}_ removes the entire mapping of a
  type.
  Example: ```- {method: removeType, arguments: [text/plain]}```

## Updating Sophron map

Sophron uses a MIME type map that is built from [FileEye/MimeMap](https://github.com/FileEye/MimeMap)
default map, with the adjustments needed to make it fully compatible with
Drupal's core MIME type mapping. This map is in the stored in the
```Drupal\sophron\Map\DrupalMap``` PHP class.

MimeMap provides an utility to update the code of the PHP map classes. Sophron's
map class can be updated starting from upstream's default one by running

```
$ cd [project_directory]
$ vendor/bin/fileeye-mimemap update --class=\\Drupal\\sophron\\Map\\DrupalMap --script=modules/contrib/sophron/resources/drupal_map_build.yml
```

The ```drupal_map_build.yml``` script instructs the utility to start the map
update from the ```FileEye\MimeMap\Map\DefaultMap``` class with the command

```
# We use the default MimeMap map as a starting point.
-
    - 'Starting from MimeMap default map'
    - selectBaseMap
    - [\FileEye\MimeMap\Map\DefaultMap]
```

then run the adjustments required to make the map compatible with Drupal core
with the command

```
# Then apply Drupal specific overrides.
-
    - 'Applying Drupal overrides'
    - applyOverrides
    -
        -
            - [addTypeExtensionMapping, [application/atomserv+xml, atomsrv]]
            - [addTypeExtensionMapping, [application/dsptype, tsp]]
            - [addTypeExtensionMapping, [application/hta, hta]]
            - ...
```

## Creating custom MIME type to extension maps

The ```fileeye-mimemap update``` utility can also be used to add new maps by
copy/pasting an existing class, renaming it, and running the utility with a
custom script that makes the required changes. The custom map can then be set
as the one to be used by Sophron's in the module configuration.
