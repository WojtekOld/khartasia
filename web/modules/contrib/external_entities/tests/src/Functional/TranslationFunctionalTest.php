<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper\ConditionalPropertyMapper;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests file storage client for external entity.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class TranslationFunctionalTest extends ExternalEntitiesBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'external_entities_test',
    'filter',
    'language',
    'content_translation',
  ];

  /**
   * A user with administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    global $base_url;
    // Enable French, Spanish and Italian.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('it')->save();
    // @todo Also test when default language is not English.
    // Set default language.
    // \Drupal::languageManager()->getLanguageConfigOverride(
    // $langcode, 'system.site')->set('default_langcode', $langcode)->save();
    // Setup datasets.
    $xntt_json_controller = $this
      ->container
      ->get('controller_resolver')
      ->getControllerFromDefinition(
        '\Drupal\external_entities_test\Controller\ExternalEntitiesJsonController::listItems'
      )[0];
    $xntt_json_controller->setRawData('main', [
      '1' => [
        'id' => '1',
        'title' => 'Title one',
        'short_text' => 'Just a short string',
        'french' => [
          'title' => 'Titre un',
          'short_text' => 'Juste une courte chaîne de caractères',
        ],
      ],
      '2' => [
        'id' => '2',
        'title' => 'Title two',
        'short_text' => 'Just another short string',
        'french' => [
          'title' => 'Titre deux',
          'short_text' => 'Juste une autre courte chaîne',
        ],
      ],
      '3' => [
        'id' => '3',
        'title' => 'Title three',
        'short_text' => 'A short string',
        'french' => [],
      ],
    ]);

    $xntt_json_controller->setRawData('spanish', [
      '1' => [
        'id' => '1',
        'title' => 'Título uno',
        'short_text' => 'Sólo una cadena corta de caracteres',
      ],
      '3' => [
        'id' => '3',
        'title' => 'Título tres',
        'short_text' => 'Sólo otra cadena corta',
      ],
      '4' => [
        'id' => '4',
        'title' => 'Título cuatro',
        'short_text' => 'Otra cadena de caracteres',
        'french' => [
          'title' => 'Titre quatre',
          'short_text' => 'Courte chaîne 4',
        ],
      ],
    ]);

    // Setup tested external entity type.
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
      'id' => 'trans_external_entity',
      'label' => 'Translatable external entity',
      'label_plural' => 'Translatable external entities',
      'base_path' => 'trans-external-entity',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
    ]);

    // Sets main aggregator.
    $type->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/main',
            'endpoint_options' => [
              'single' => '',
              'count' => '',
              'count_mode' => NULL,
              'cache' => FALSE,
              'limit_qcount' => 0,
              'limit_qtime' => 0,
            ],
            'response_format' => 'json',
            'data_path' => [
              'list' => '',
              'single' => '',
              'keyed_by_id' => FALSE,
              'count' => '',
            ],
            'pager' => [
              'default_limit' => 50,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
            'api_key' => [
              'type' => 'none',
              'header_name' => '',
              'key' => '',
            ],
            'http' => [
              'headers' => '',
            ],
            'parameters' => [
              'list' => [],
              'list_param_mode' => 'query',
              'single' => [],
              'single_param_mode' => 'query',
            ],
            'filtering' => [
              'drupal' => TRUE,
              'basic' => FALSE,
              'basic_fields' => [],
              'list_support' => 'none',
              'list_join' => '',
            ],
          ],
        ],
      ],
    ]);
    $type->save();

    // Add fields.
    $this
      ->createField('trans_external_entity', 'plain_text', 'string');

    // Enable translation.
    $this->enableEntityLanguage('fr');
    $this->enableEntityLanguage('es');

    // Set field mappers...
    // ID field mapping.
    $type->setFieldMapperId('id', 'generic');
    $type->setFieldMapperConfig(
      'id',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'direct',
            'config' => [
              'mapping' => 'id',
              'required_field' => TRUE,
              'main_property' => TRUE,
              'data_processors' => [
                [
                  'id' => 'default',
                  'config' => [],
                ],
              ],
            ],
          ],
        ],
        'debug_level' => 0,
      ]
    );
    // UUID field mapping.
    $type->setFieldMapperId('uuid', 'generic');
    $type->setFieldMapperConfig(
      'uuid',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'direct',
            'config' => [
              'mapping' => 'id',
              'required_field' => FALSE,
              'main_property' => TRUE,
              'data_processors' => [
                [
                  // We do not want a generated uuid to override id on save.
                  'id' => 'readonly',
                  'config' => [],
                ],
              ],
            ],
          ],
        ],
        'debug_level' => 0,
      ]
    );
    // Title field mapping.
    $type->setFieldMapperId('title', 'generic');
    $type->setFieldMapperConfig(
      'title',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'direct',
            'config' => [
              'mapping' => 'title',
              'required_field' => TRUE,
              'main_property' => TRUE,
              'data_processors' => [
                [
                  'id' => 'default',
                  'config' => [],
                ],
              ],
            ],
          ],
        ],
        'debug_level' => 0,
      ]
    );
    // Plain text field mapping.
    $type->setFieldMapperId('plain_text', 'generic');
    $type->setFieldMapperConfig(
      'plain_text',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'simple',
            'config' => [
              'mapping' => 'short_text',
              'required_field' => FALSE,
              'main_property' => TRUE,
              'data_processors' => [
                [
                  'id' => 'default',
                  'config' => [],
                ],
              ],
            ],
          ],
        ],
        'debug_level' => 0,
      ],
    );
    $type->save();

    // Allow external entity to be translated.
    // Add language config.
    \Drupal::service('content_translation.manager')->setEnabled(
      'trans_external_entity',
      'trans_external_entity',
      TRUE
    );
    \Drupal::service('router.builder')->setRebuildNeeded();

    // Set language overrides.
    $type->setLanguageSettings([
      'overrides' => [
        'es' => [
          'data_aggregator' => [
            'id' => 'single',
            'config' => [
              'storage_clients' => [
                [
                  'id' => 'rest',
                  'config' => [
                    'endpoint' => $base_url . '/external-entities-test/spanish',
                    'endpoint_options' => [
                      'single' => '',
                      'count' => '',
                      'count_mode' => NULL,
                      'cache' => FALSE,
                      'limit_qcount' => 0,
                      'limit_qtime' => 0,
                    ],
                    'response_format' => 'json',
                    'data_path' => [
                      'list' => '',
                      'single' => '',
                      'keyed_by_id' => FALSE,
                      'count' => '',
                    ],
                    'pager' => [
                      'default_limit' => 50,
                      'type' => 'pagination',
                      'page_parameter' => 'page',
                      'page_parameter_type' => 'pagenum',
                      'page_start_one' => FALSE,
                      'page_size_parameter' => 'pageSize',
                      'page_size_parameter_type' => 'pagesize',
                    ],
                    'api_key' => [
                      'type' => 'none',
                      'header_name' => '',
                      'key' => '',
                    ],
                    'http' => [
                      'headers' => '',
                    ],
                    'parameters' => [
                      'list' => [],
                      'list_param_mode' => 'query',
                      'single' => [],
                      'single_param_mode' => 'query',
                    ],
                    'filtering' => [
                      'drupal' => TRUE,
                      'basic' => FALSE,
                      'basic_fields' => [],
                      'list_support' => 'none',
                      'list_join' => '',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        'fr' => [
          'field_mappers' => [
            'id' => [
              'id' => 'generic',
              'config' => [
                'property_mappings' => [
                  'value' => [
                    // Only provide an ID if the title field is not empty.
                    // Therefore, when a French translation does not have a
                    // title, it means there is no French translation available.
                    'id' => 'conditional',
                    'config' => [
                      'mapping' => 'french.title',
                      'condition' => ConditionalPropertyMapper::CONDITION_NOT_EMPTY,
                      'condition_value' => '',
                      'save_option' => ConditionalPropertyMapper::SAVE_OPTION_IF,
                      'property_mappers' => [
                        [
                          'id' => 'direct',
                          'config' => [
                            'mapping' => 'id',
                            'required_field' => TRUE,
                            'main_property' => TRUE,
                            'data_processors' => [
                              [
                                'id' => 'default',
                                'config' => [],
                              ],
                            ],
                          ],
                        ],
                      ],
                      'required_field' => TRUE,
                      'main_property' => TRUE,
                    ],
                  ],
                ],
                'debug_level' => 0,
              ],
            ],
            'uuid' => [
              'id' => 'generic',
              'config' => [
                'property_mappings' => [
                  'value' => [
                    'id' => 'direct',
                    'config' => [
                      'mapping' => 'id',
                      'required_field' => TRUE,
                      'main_property' => TRUE,
                      'data_processors' => [
                        [
                          'id' => 'default',
                          'config' => [],
                        ],
                      ],
                    ],
                  ],
                ],
                'debug_level' => 0,
              ],
            ],
            'title' => [
              'id' => 'generic',
              'config' => [
                'property_mappings' => [
                  'value' => [
                    'id' => 'simple',
                    'config' => [
                      'mapping' => 'french.title',
                      'required_field' => TRUE,
                      'main_property' => TRUE,
                      'data_processors' => [
                        [
                          'id' => 'default',
                          'config' => [],
                        ],
                      ],
                    ],
                  ],
                ],
                'debug_level' => 0,

              ],
            ],
            'plain_text' => [
              'id' => 'generic',
              'config' => [
                'property_mappings' => [
                  'value' => [
                    'id' => 'simple',
                    'config' => [
                      'mapping' => 'french.short_text',
                      'required_field' => FALSE,
                      'main_property' => TRUE,
                      'data_processors' => [
                        [
                          'id' => 'default',
                          'config' => [],
                        ],
                      ],
                    ],
                  ],
                ],
                'debug_level' => 0,
              ],
            ],
          ],
        ],
      ],
    ]);
    $type->save();

    // Create the user with all needed permissions.
    $this->account = $this->drupalCreateUser([
      'administer external entity types',
      'view trans_external_entity external entity',
      'update trans_external_entity external entity',
      'delete trans_external_entity external entity',
      'view trans_external_entity external entity collection',
      'create trans_external_entity external entity',
      'create content translations',
      'delete content translations',
      'update content translations',
      'translate editable entities',
      'translate any entity',
      'access site reports',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Helper function to enable a language.
   *
   * @param string $langcode
   *   The language code.
   */
  private function enableEntityLanguage(string $langcode): void {
    $config = \Drupal::service('config.factory')->getEditable('language.entity.' . $langcode);
    $config->set('status', 1)->save();
  }

  /**
   * Tests creation of a rule and then triggering its execution.
   */
  public function testTranslatableExternalEntity() {
    $url_prefix = Url::fromUri('base:/')->toString();

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Test language setup.
    $languages = \Drupal::languageManager()->getLanguages();
    $this->assertTrue(in_array('fr', array_keys($languages)), 'French language is enabled.');
    $this->assertTrue(in_array('es', array_keys($languages)), 'Spanish language is enabled.');

    // Check "main test endpoint".
    $trans_json = Json::decode($this->drupalGet('external-entities-test/main'));
    $this
      ->assertSession()
      ->statusCodeEquals(200);
    $this
      ->assertCount(3, $trans_json);
    $this
      ->assertSession()
      ->responseHeaderEquals('Content-Type', 'application/json');

    // Check "Spanish test endpoint".
    $trans_json = Json::decode($this->drupalGet('external-entities-test/spanish'));
    $this
      ->assertSession()
      ->statusCodeEquals(200);
    $this
      ->assertCount(3, $trans_json);
    $this
      ->assertSession()
      ->responseHeaderEquals('Content-Type', 'application/json');

    // Check translatable external entity exists.
    $this->drupalGet('admin/structure/external-entity-types');
    $assert->pageTextContains('Translatable external entity');

    // Default language entity list check: all three titles should be there.
    $this->drupalGet('trans-external-entity');
    $assert->pageTextContainsOnce('Title one');
    $assert->pageTextContainsOnce('Title two');
    $assert->pageTextContainsOnce('Title three');
    // No 4th element.
    $assert->responseNotContains('trans-external-entity/4/edit');

    // French entity list check: only French translation for 1 and 2.
    $this->drupalGet('fr/trans-external-entity');
    $assert->pageTextContainsOnce('Titre un');
    $assert->pageTextContainsOnce('Titre deux');
    // Not translated to French, English title should be displayed.
    $assert->pageTextContainsOnce('Title three');
    // No 4th element.
    $assert->responseNotContains('trans-external-entity/4/edit');

    // Spanish entity list check: only spanish translation for 1, 2 and 4 but
    // 4 does not correspond to an existing entity in default language.
    $this->drupalGet('es/trans-external-entity');
    $assert->pageTextContainsOnce('Título uno');
    // Not translated to Spanish, English title should be displayed.
    $assert->pageTextContainsOnce('Title two');
    $assert->pageTextContainsOnce('Título tres');
    // No entity 4.
    $assert->pageTextNotContains('Título cuatro');
    $assert->responseNotContains('trans-external-entity/4/edit');

    // Italian entity list check: nothing in Italian.
    $this->drupalGet('it/trans-external-entity');
    // Contains English titles.
    $assert->pageTextContainsOnce('Title one');
    $assert->pageTextNotContains('Titre un');
    $assert->pageTextNotContains('Título uno');

    // Entity 1 - default language.
    $this->drupalGet('trans-external-entity/1');
    $assert->pageTextContains('Title one');
    $assert->pageTextContainsOnce('Just a short string');
    $assert->pageTextNotContains('Titre un');
    $assert->pageTextNotContains('Title two');

    // Entity 1 - French.
    $this->drupalGet('fr/trans-external-entity/1');
    $assert->pageTextContains('Titre un');
    $assert->pageTextContainsOnce('Juste une courte chaîne de caractères');
    $assert->pageTextNotContains('Title one');
    $assert->pageTextNotContains('Just a short string');

    // Entity 1 - Spanish.
    $this->drupalGet('es/trans-external-entity/1');
    $assert->pageTextContains('Título uno');
    $assert->pageTextContainsOnce('Sólo una cadena corta de caracteres');
    $assert->pageTextNotContains('Title one');
    $assert->pageTextNotContains('Just a short string');

    // Entity 1 - Italian.
    $this->drupalGet('it/trans-external-entity/1');
    // No Italian translation, fallback to English.
    $assert->pageTextContains('Title one');
    $assert->pageTextContainsOnce('Just a short string');

    // Entity 1 - default language translations.
    $this->drupalGet('trans-external-entity/1/translations');
    $assert->pageTextContains('Translations of Title one');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/edit');
    // "Delete" for English (at top).
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/delete');
    // French translation.
    $assert->pageTextContainsOnce('Titre un');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/delete');
    // Spanish translation.
    $assert->pageTextContainsOnce('Título uno');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/delete');
    // Publication status.
    // @todo Check status of each language individualy.
    $assert->pageTextContains('Published');
    $assert->pageTextContains('Not translated');

    // Entity 1 - French translations.
    $this->drupalGet('fr/trans-external-entity/1/translations');
    $assert->pageTextContains('Translations of Titre un');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    $assert->pageTextContainsOnce('Title one');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/edit');
    // No "Delete" for English as it is replaced by the "French" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/1/delete');
    // French translation.
    $assert->pageTextContains('Titre un');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/delete');
    // Spanish translation.
    $assert->pageTextContainsOnce('Título uno');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/delete');

    // Entity 1 - Spanish translations.
    $this->drupalGet('es/trans-external-entity/1/translations');
    $assert->pageTextContains('Translations of Título uno');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    $assert->pageTextContainsOnce('Title one');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/edit');
    // No "Delete" for English as it is replaced by the "French" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/1/delete');
    // French translation.
    $assert->pageTextContainsOnce('Titre un');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/delete');
    // Spanish translation.
    $assert->pageTextContains('Título uno');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/delete');

    // Entity 1 - Italian translations.
    $this->drupalGet('it/trans-external-entity/1/translations');
    $assert->pageTextContains('Translations of Title one');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/edit');
    // No "Delete" for English as it is replaced by the "French" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/1/delete');
    // French translation.
    $assert->pageTextContainsOnce('Titre un');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/delete');
    // Spanish translation.
    $assert->pageTextContainsOnce('Título uno');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/translations/add/en/it');
    // "Edit" for Italian but...
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/edit');
    // "Delete" for Italian but...
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/delete');

    // Entity 2 - default language.
    $this->drupalGet('trans-external-entity/2');
    $assert->pageTextContains('Title two');
    $assert->pageTextContainsOnce('Just another short string');

    // Entity 2 - French.
    $this->drupalGet('fr/trans-external-entity/2');
    $assert->pageTextContains('Titre deux');
    $assert->pageTextContainsOnce('Juste une autre courte chaîne');

    // Entity 2 - Spanish.
    $this->drupalGet('es/trans-external-entity/2');
    $assert->pageTextContains('Title two');
    $assert->pageTextContainsOnce('Just another short string');

    // Entity 3 - default language.
    $this->drupalGet('trans-external-entity/3');
    $assert->pageTextContains('Title three');
    $assert->pageTextContainsOnce('A short string');

    // Entity 3 - French.
    $this->drupalGet('fr/trans-external-entity/3');
    $assert->pageTextContains('Title three');
    $assert->pageTextContainsOnce('A short string');

    // Entity 3 - Spanish.
    $this->drupalGet('es/trans-external-entity/3');
    $assert->pageTextContains('Título tres');
    $assert->pageTextContainsOnce('Sólo otra cadena corta');

    // Entity 2 - default language translations.
    $this->drupalGet('trans-external-entity/2/translations');
    $assert->pageTextContains('Translations of Title two');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/2/edit');
    // "Delete" for English (at top).
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/2/delete');
    // French translation.
    $assert->pageTextContainsOnce('Titre deux');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/2/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/2/delete');
    // No Spanish translation.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/2/translations/add/en/es');
    // No "edit" for Spanish.
    $assert->responseNotContains('"' . $url_prefix . 'es/trans-external-entity/2/edit');
    // No "delete" for Spanish.
    $assert->responseNotContains('"' . $url_prefix . 'es/trans-external-entity/2/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/2/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/2/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/2/delete');

    // Entity 3 - default language translations.
    $this->drupalGet('trans-external-entity/3/translations');
    $assert->pageTextContains('Translations of Title three');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/3/edit');
    // "Delete" for English (at top).
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/3/delete');
    // No French translation.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/3/translations/add/en/fr');
    // No "edit" for French.
    $assert->responseNotContains('"' . $url_prefix . 'fr/trans-external-entity/3/edit');
    // No "delete" for French.
    $assert->responseNotContains('"' . $url_prefix . 'fr/trans-external-entity/3/delete');
    // Spanish translation.
    $assert->pageTextContainsOnce('Título tres');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/3/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/3/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/3/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/3/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/3/delete');

    // Entity 1 - French translations.
    $this->drupalGet('fr/trans-external-entity/1/translations');
    $assert->pageTextContains('Translations of Titre un');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    $assert->pageTextContainsOnce('Title one');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/edit');
    // No "Delete" for English as it is replaced by the "French" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/1/delete');
    // French translation.
    $assert->pageTextContains('Titre un');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/delete');
    // Spanish translation.
    $assert->pageTextContainsOnce('Título uno');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/delete');

    // Entity 1 - Spanish translations.
    $this->drupalGet('es/trans-external-entity/1/translations');
    $assert->pageTextContains('Translations of Título uno');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    $assert->pageTextContainsOnce('Title one');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/1/edit');
    // No "Delete" for English as it is replaced by the "French" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/1/delete');
    // French translation.
    $assert->pageTextContainsOnce('Titre un');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/1/delete');
    // Spanish translation.
    $assert->pageTextContains('Título uno');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/1/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/1/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/1/delete');

    // Entity 2 - Spanish translations.
    // @todo Check if we should not have a 404 instead as there is not such a
    // translation.
    $this->drupalGet('es/trans-external-entity/2/translations');
    $assert->pageTextContains('Translations of Title two');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/2/edit');
    // No "Delete" for English as it is replaced by the "Spanish" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/2/delete');
    // French translation.
    $assert->pageTextContainsOnce('Titre deux');
    // "Edit" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/2/edit');
    // "Delete" for French.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/2/delete');
    // No Spanish translation.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/2/translations/add/en/es');
    // "Edit" for Spanish at top.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/2/edit');
    // "Delete" for Spanish at top.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/2/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/2/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/2/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/2/delete');

    // Entity 3 - French translations.
    // @todo Check if we should not have a 404 instead as there is not such a
    // translation.
    $this->drupalGet('fr/trans-external-entity/3/translations');
    $assert->pageTextContains('Translations of Title three');
    // English as default.
    $assert->pageTextContains('English (Original language)');
    // "Edit" for English.
    $assert->responseContains('"' . $url_prefix . 'trans-external-entity/3/edit');
    // No "Delete" for English as it is replaced by the "French" delete at top.
    $assert->responseNotContains('"' . $url_prefix . 'trans-external-entity/3/delete');
    // No French translation.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/3/translations/add/en/fr');
    // "Edit" for French at top.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/3/edit');
    // "Delete" for French at top.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/3/delete');
    // Spanish translation.
    $assert->pageTextContainsOnce('Título tres');
    // "Edit" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/3/edit');
    // "Delete" for Spanish.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/3/delete');
    // "Add" for Italian.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/3/translations/add/en/it');
    // No "edit" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/3/edit');
    // No "delete" for Italian.
    $assert->responseNotContains('"' . $url_prefix . 'it/trans-external-entity/3/delete');

    // Entity 4 - nothing.
    $this->drupalGet('trans-external-entity/4');
    $this
      ->assertSession()
      ->statusCodeEquals(404);

    // Edit entity 1 - default language.
    $this->drupalGet('trans-external-entity/1/edit');
    $this->fillField('Title', 'Updated title 1');
    $this->fillField('plain_text', 'Simple short string');
    $this->pressButton('Save');
    $assert->pageTextContains('Updated title 1');
    $assert->pageTextContainsOnce('Simple short string');

    // Edit entity 1 - French.
    $this->drupalGet('fr/trans-external-entity/1/edit');
    $this->fillField('Title', 'Titre 1er');
    $this->fillField('plain_text', 'Nouveau texte 1');
    $this->pressButton('Save');
    $this->drupalGet('trans-external-entity/1');
    $assert->pageTextContains('Updated title 1');
    $assert->pageTextContainsOnce('Simple short string');
    $this->drupalGet('fr/trans-external-entity/1');
    $assert->pageTextContains('Titre 1er');
    $assert->pageTextContainsOnce('Nouveau texte 1');

    // Edit entity 1 - Spanish.
    $this->drupalGet('es/trans-external-entity/1/edit');
    $this->fillField('Title', 'Primero título');
    $this->fillField('plain_text', 'Nuevo texto 1');
    $this->pressButton('Save');
    $this->drupalGet('trans-external-entity/1');
    $assert->pageTextContains('Updated title 1');
    $assert->pageTextContainsOnce('Simple short string');
    $this->drupalGet('es/trans-external-entity/1');
    $assert->pageTextContains('Primero título');
    $assert->pageTextContainsOnce('Nuevo texto 1');
    $this->drupalGet('fr/trans-external-entity/1');
    $assert->pageTextContains('Titre 1er');
    $assert->pageTextContainsOnce('Nouveau texte 1');

    // Add Spanish translation to entity 2.
    $this->drupalGet('es/trans-external-entity/2/translations/add/en/es');
    $this->fillField('Title', 'Título 2');
    $this->fillField('plain_text', 'Segundo texto');
    $this->pressButton('Save');
    $this->drupalGet('es/trans-external-entity/2');
    $assert->pageTextContains('Título 2');
    $assert->pageTextContainsOnce('Segundo texto');

    // Add French translation to entity 3.
    $this->drupalGet('fr/trans-external-entity/3/translations/add/en/fr');
    $this->fillField('Title', 'Titre 3');
    $this->fillField('plain_text', 'Troisième text');
    $this->pressButton('Save');
    $this->drupalGet('fr/trans-external-entity/3');
    $assert->pageTextContains('Titre 3');
    $assert->pageTextContainsOnce('Troisième text');

    // Try to remove French translation to entity 2.
    $this->drupalGet('fr/trans-external-entity/2');
    $assert->pageTextContains('Titre deux');
    $this->drupalGet('fr/trans-external-entity/2/delete');
    $this->pressButton('Delete French translation');
    $assert->pageTextContains('External entity translation Titre deux (fr) can not be removed as it uses the same external data source as its corresponding untranslated entity.');
    // Not deleted.
    $this->drupalGet('fr/trans-external-entity/2');
    $assert->pageTextContains('Titre deux');
    $assert->pageTextNotContains('Title two');
    $this->drupalGet('trans-external-entity/2');
    $assert->pageTextContains('Title two');

    // Remove Spanish translation to entity 2.
    $this->drupalGet('es/trans-external-entity/2/delete');
    $this->pressButton('Delete Spanish translation');
    $assert->pageTextContains('The translatable external entity Título 2 Spanish translation has been deleted.');
    $this->drupalGet('es/trans-external-entity/2');
    $assert->pageTextNotContains('Título tres');
    $assert->pageTextContains('Title two');
    $this->drupalGet('trans-external-entity/2');
    $assert->pageTextContains('Title two');

    // Remove entity 3.
    $this->drupalGet('trans-external-entity/3/delete');
    $this->pressButton('Delete all translations');
    $this->drupalGet('trans-external-entity/3');
    $this
      ->assertSession()
      ->statusCodeEquals(404);

    // Put back entity 3.
    $this->drupalGet('trans-external-entity/add');
    $this->fillField('ID', '3');
    $this->fillField('Title', 'Title 3');
    $this->fillField('plain_text', 'Third text');
    $this->pressButton('Save');
    $this->drupalGet('trans-external-entity/3');
    $assert->pageTextContains('Title 3');
    $assert->pageTextContainsOnce('Third text');
    // Check French translation not there for entity 3.
    $this->drupalGet('fr/trans-external-entity/3');
    $assert->pageTextContains('Title 3');
    $assert->pageTextNotContains('Titre 3');
    $assert->pageTextContainsOnce('Third text');
    $assert->pageTextNotContains('Troisième text');
    // Check Spanish translation is still there for entity 3.
    $this->drupalGet('es/trans-external-entity/3');
    $assert->pageTextContains('Título tres');
    $assert->pageTextNotContains('Title 3');
    $assert->pageTextContainsOnce('Sólo otra cadena corta');
    $assert->pageTextNotContains('Third text');

    // Create entity 4.
    $this->drupalGet('trans-external-entity/add');
    $this->fillField('ID', '4');
    $this->fillField('Title', 'Title four');
    $this->fillField('plain_text', 'Fourth text');
    $this->pressButton('Save');
    $this->drupalGet('trans-external-entity/4');
    $assert->pageTextContains('Title four');
    $assert->pageTextContainsOnce('Fourth text');
    // Check Spanish translation is there for entity 4.
    $this->drupalGet('es/trans-external-entity/4');
    $assert->pageTextContains('Título cuatro');
    $assert->pageTextNotContains('Title 4');
    $assert->pageTextContainsOnce('Otra cadena de caracteres');
    $assert->pageTextNotContains('Fourth text');
    // Check French translation is not there for entity 4 and does not come from
    // Spanish source but uses English as fallback.
    $this->drupalGet('fr/trans-external-entity/4');
    $assert->pageTextContains('Title four');
    $assert->pageTextNotContains('Titre quatre');
    $assert->pageTextContainsOnce('Fourth text');
    $assert->pageTextNotContains('Courte chaîne 4');

    // Create entity 5.
    $this->drupalGet('trans-external-entity/add');
    $this->fillField('ID', '5');
    $this->fillField('Title', 'Title five');
    $this->fillField('plain_text', 'Fifth text');
    $this->pressButton('Save');
    $this->drupalGet('trans-external-entity/5/translations');
    $assert->pageTextContains('Title five');
    // No French translation.
    $assert->responseContains('"' . $url_prefix . 'fr/trans-external-entity/5/translations/add/en/fr');
    // No Spanish translation.
    $assert->responseContains('"' . $url_prefix . 'es/trans-external-entity/5/translations/add/en/es');
    // No Italian translation.
    $assert->responseContains('"' . $url_prefix . 'it/trans-external-entity/5/translations/add/en/it');
  }

}
