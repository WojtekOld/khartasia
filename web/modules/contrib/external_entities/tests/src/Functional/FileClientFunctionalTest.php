<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\FileClientInterface;

/**
 * Tests file storage client for external entity.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class FileClientFunctionalTest extends ExternalEntitiesBrowserTestBase {

  use TestFileCreationTrait;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

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

    $this->fileSystem = $this->container->get('file_system');
    $file_path = $this->generateFile('test', 21, 2, 'text');
    $filename = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => basename($file_path), fn() => $this->fileSystem->basename($file_path));
    $directory = $this->fileSystem->dirname($file_path);
    $this->assertFileExists($file_path);

    // Storage client.
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
      'id' => 'test_file_xntt',
      'label' => 'Simple external entity',
      'label_plural' => 'Simple external entities',
      'base_path' => 'test-file-xntt',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'field_mapping_notes' => '',
      'data_aggregator' => [],
      'data_aggregator_notes' => '',
      'language_settings' => [],
      'persistent_cache_max_age' => 0,
    ]);

    // Sets aggregator.
    $type->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        [
          'id' => 'files',
          'config' => [
            'root' => $directory,
            'structure' => $filename,
            'matching_only' => FALSE,
            'record_type' => FileClientInterface::RECORD_TYPE_FILE,
            'performances' => [
              'use_index' => FALSE,
              'index_file' => '',
            ],
            'data_fields' => [
              'md5_checksum' => FALSE,
              'sha1_checksum' => FALSE,
              'field_list' => [],
            ],
          ],
        ],
      ],
    ]);
    $type->save();

    // Add fields.
    $this
      ->createField('test_file_xntt', 'field_path', 'string')
      ->createField('test_file_xntt', 'field_dirname', 'string')
      ->createField('test_file_xntt', 'field_basename', 'string')
      ->createField('test_file_xntt', 'field_filename', 'string')
      ->createField('test_file_xntt', 'field_extension', 'string')
      ->createField('test_file_xntt', 'field_mode', 'string')
      ->createField('test_file_xntt', 'field_size', 'integer')
      ->createField('test_file_xntt', 'field_mtime', 'timestamp')
      ->createField('test_file_xntt', 'field_md5', 'string')
      ->createField('test_file_xntt', 'field_sha1', 'string');

    $type
      // ID field mapping.
      ->setFieldMapperId('id', 'generic')->setFieldMapperConfig(
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
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // UUID field mapping.
      ->setFieldMapperId('uuid', 'generic')
      ->setFieldMapperConfig(
        'uuid',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Title field mapping.
      ->setFieldMapperId('title', 'generic')
      ->setFieldMapperConfig(
        'title',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'filename',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field path field mapping.
      ->setFieldMapperId('field_path', 'generic')
      ->setFieldMapperConfig(
        'field_path',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'path',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field dirname field mapping.
      ->setFieldMapperId('field_dirname', 'generic')
      ->setFieldMapperConfig(
        'field_dirname',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'dirname',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field basename field mapping.
      ->setFieldMapperId('field_basename', 'generic')
      ->setFieldMapperConfig(
        'field_basename',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'basename',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field filename field mapping.
      ->setFieldMapperId('field_filename', 'generic')
      ->setFieldMapperConfig(
        'field_filename',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'filename',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field extension field mapping.
      ->setFieldMapperId('field_extension', 'generic')
      ->setFieldMapperConfig(
        'field_extension',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'extension',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field mode field mapping.
      ->setFieldMapperId('field_mode', 'generic')
      ->setFieldMapperConfig(
        'field_mode',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'mode',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field size field mapping.
      ->setFieldMapperId('field_size', 'generic')
      ->setFieldMapperConfig(
        'field_size',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'size',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field mtime field mapping.
      ->setFieldMapperId('field_mtime', 'generic')
      ->setFieldMapperConfig(
        'field_mtime',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'mtime',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field md5 field mapping.
      ->setFieldMapperId('field_md5', 'generic')
      ->setFieldMapperConfig(
        'field_md5',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'md5',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Field sha1 field mapping.
      ->setFieldMapperId('field_sha1', 'generic')
      ->setFieldMapperConfig(
        'field_sha1',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'sha1',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      );
    $type->save();

    // Create the user with all needed permissions.
    $this->account = $this->drupalCreateUser([
      'administer external entity types',
      'view test_file_xntt external entity',
      'view test_file_xntt external entity collection',
      'access site reports',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Tests file storage client global features.
   */
  public function testFileClient() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Entity list check.
    $this->drupalGet('test-file-xntt');
    $assert->pageTextContains('test.txt');

    // First file test.
    $this->drupalGet('test-file-xntt/test.txt');
    $assert->pageTextContains('test.txt');
    $assert->pageTextContains('public://test.txt');
    $assert->pageTextContains('public:');
    // We do not check the value of the mode as it depends on shell defaults.
    $assert->pageTextContains('field_mode');
    $assert->pageTextContains('42');
    $assert->pageTextContains('field_mtime');

    $this->drupalGet('test-file-xntt/test.txt/edit');
    $assert->pageTextContains('test.txt');

    // Saving a file record is a no-op, but the edit form must still round-trip.
    $this->submitForm([], 'Save');
    $assert->statusCodeEquals(200);
    $this->drupalGet('test-file-xntt/test.txt');
    $assert->pageTextContains('public://test.txt');
  }

}
