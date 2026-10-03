# External Entities For Drupal.org

This module provides external entity examples using the drupal.org APIs.

https://www.drupal.org/drupalorg/docs/apis/rest-and-other-apis

## Types

### Drupal.org REST Issue

**list**: /drupalorg-rest-issue

Uses the drupal.org REST endpoint at https://www.drupal.org/api-d7/node.json.

### Drupal.org JSONAPI Module

**list**: /drupalorg-jsonapi-module

Uses the drupal.org JSONAPI https://www.drupal.org/jsonapi.

This was recently added for Project Browser and not yet mentioned in the docs.

## Fields

Includes a body field for both types.

More fields can be added to the External Entity types, and mapped in the
configuration.

TODO: Add more fields.

## Views

**Drupal.org REST Issues**: /drupalorg-rest-issue-view

**Drupal.org JSONAPI Modules**: /drupalorg-jsonapi-module-view

Uses the new External Entities Views plugin that's now included in v3.
https://www.drupal.org/project/external_entities/issues/2538706

### Known issues

- Pager doesn't appear.
- Preview throws errors and doesn't work.

## TODO

- Create custom StorageClient plugin for Project Browser.
- Convert this to a recipe.
