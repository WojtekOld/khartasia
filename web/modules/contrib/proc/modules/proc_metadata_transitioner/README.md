## INTRODUCTION
The Proc Metadata Transitioner module is a tool for managing the cipher text
metadata, specially the fetcher endpoint entry (machine name: fetcher_endpoint).
It allows adding and removing the metadata needed for re-encryption. Removal
works as an undo action for the adding operation. Its most common use case is to
adjust old proc cipher text entities (where fetcher endpoint is still missing as
metadata) to make them compatible with the current re-encryption process. So,
you should only be interested on using this module if you have proc cipher text
entities created under a proc version prior to 10.1.79, and you want to make
that protected content compatible with the current re-encryption process.

## INSTALLATION
Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## CONFIGURATION
Currently this module only supports one host entity type at a time. It has to
be configured accordingly. The configuration includes the following fields:
- Host Content Machine Name: the machine name of the host content entity type.
- Host Content Proc Fields: the machine names of the proc fields in the host
  content entity type.
- Recipients Fetcher: the relative or absolute path of the fetcher endpoint.
  This configuration supports [host_entity_id] as a token.
- Disable Label Matching: by default the module checks that the label of the
  hosted proc cipher text entity matches the label of the host content entity.
  This is to avoid adding/removing metadata to/from wrong proc cipher text
  entities. If you are sure that all proc cipher text entities in your system
  are correctly hosted, or if yo udo not rewrite their labels for matching the
  host entity, you can disable this check by enabling this option.
- Maximum Number of Operations per Batch: to avoid timeouts, the operations are
  performed in batches. This configuration allows setting the maximum number of
  entities to be processed per batch. Better is the performance of your server,
  smaller can be this number. Default is 500.

## USAGE
Once the module is installed and configured, you can use Drush commands to add
or remove the fetcher endpoint metadata from proc cipher text entities.
The following Drush commands are available:
- proc_metadata_transitioner-add-metadata: adds the fetcher endpoint metadata
  to proc cipher text entities.
- proc_metadata_transitioner-remove-metadata: removes the fetcher endpoint
  metadata from proc cipher text entities.
You can also trigger these operations via GUI by navigating to
/admin/config/content/proc/metadata_transitioner, selecting the wished operation
(Add metadata or Remove metadata), filling the range limit or leaving it empty
for scanning all the content and clicking on the Go button.

## THE RE-ENCRYPTION PROCESS
Your hosted protected content (proc cipher text entities attached to a host
content where they were created via a proc entity reference field) might have
been created using an endpoint for fetching the IDs of the recipients aimed at a
specific host content entity. Re-encryption means encrypting again that content
in case the fetcher endpoint returns now a different set of recipients. The
re-encryption process needs to know which fetcher endpoint to use for each
hosted protected content. This information is stored as metadata in the proc
cipher text entity. If that metadata is missing (what happens to protected
contents created prior to proc version 10.1.79), the re-encryption process
cannot be performed correctly. This module allows adding that missing metadata
to old proc cipher text entities, so they can be re-encrypted correctly.
