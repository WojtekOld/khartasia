TMGMT DeepL (tmgmt_deepl)
---------------------

TMGMT DeepL module is a plugin for Translation Management Tool module (tmgmt).
It uses the DeepL API (https://developers.deepl.com/docs/) for automated
translation of the content. You can use the DeepL API Free (limited to 500.000
characters per month) or the DeepL API Pro for more than 500.000 characters.
More information on pricing can be found on https://www.deepl.com/pro#developer.

REQUIREMENTS
------------

This module requires TMGMT (http://drupal.org/project/tmgmt) module and Key module (http://drupal.org/project/key) to be
installed.

Also you will need to enter your DeepL API key. You can find
them on the page https://www.deepl.com/pro#developer after registration on
https://www.deepl.com.

CONFIGURATION
-------------

- add a new key of type "DeepL API Key" at /admin/config/system/keys/add
- add a new translation provider at /admin/tmgmt/translators
- choose the "DeepL API" provider plugin and select your DeepL API authentication key (if no key is visible here, check for correct key type)
- set additional settings related to the DeepL API

For more information on the DeepL API check: https://developers.deepl.com/docs/
