# Getting started

## Install the module

Install Copyright Footer as a contributed Drupal module. With Composer:

```bash
composer require drupal/copyright_footer
```

For general installation guidance, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Place the block

Copyright Footer has no standalone configuration page. Go to
`/admin/structure/block`, place the **Copyright Footer** block in the theme
region used for your site's footer, then open its configuration form.

The default output is translated and includes the copyright symbol, the current
year or configured year range, and any organization or version details you add.
