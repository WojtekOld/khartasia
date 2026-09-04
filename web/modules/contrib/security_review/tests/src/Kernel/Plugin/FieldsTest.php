<?php

namespace Drupal\Tests\security_review\Kernel\Plugin;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\security_review\Kernel\SecurityReviewTestBase;

/**
 * Contains tests for the Fields plugin.
 *
 * @group security_review
 */
class FieldsTest extends SecurityReviewTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'filter',
    'field',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig(['node', 'user', 'system']);

    $this->createContentType(['type' => 'article']);

    /** @var \Drupal\security_review\SecurityCheckPluginManager $manager */
    $manager = $this->container->get('plugin.manager.security_review.security_check');
    $this->plugin = $manager->createInstance('fields');
  }

  /**
   * Tests the plugin succeeds correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFieldsPluginSuccess(): void {
    $this->createTestNode();

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);

    $this->assertArrayHasKey('findings', $sandbox);
    $this->assertEmpty($sandbox['findings']);
  }

  /**
   * Tests the plugin fails correctly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFieldsPluginFailure(): void {
    $node = $this->createTestNode(FALSE);

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);

    $this->assertArrayHasKey('findings', $sandbox);
    $this->assertNotEmpty($sandbox['findings']);
    $this->assertArrayHasKey('node', $sandbox['findings']);
    $nid = $node->id();
    $this->assertArrayHasKey($nid, $sandbox['findings']['node']);
    $this->assertArrayHasKey('body', $sandbox['findings']['node'][$nid]);
    $this->assertEquals('Javascript', $sandbox['findings']['node'][$nid]['body'][0]);
    $this->assertEquals('fb3f22ac0c23ef791946d0f321511f11d824c09f2cf830c7f2ea646fefd55e0f', $sandbox['findings']['node'][$nid]['body']['hash']);
  }

  /**
   * Tests the hushed setting.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFieldsPluginHushedSetting(): void {
    $this->config('security_review.settings')
      ->set('fields.known_risky_fields', [
        'fb3f22ac0c23ef791946d0f321511f11d824c09f2cf830c7f2ea646fefd55e0f|This is a test',
      ])
      ->save();

    $node = $this->createTestNode(FALSE);

    // Run the plugin.
    $sandbox = [];
    $finished = 0;
    // Run until the plugin is fully finished.
    while ($finished < 1) {
      $finished = $this->plugin->run(FALSE, $sandbox);
    }
    $this->assertEquals(1.0, $finished);

    $this->assertArrayHasKey('findings', $sandbox);
    $this->assertEmpty($sandbox['findings']);
    $this->assertNotEmpty($sandbox['hushed_findings']);
    $this->assertArrayHasKey('node', $sandbox['hushed_findings']);
    $nid = $node->id();
    $this->assertArrayHasKey($nid, $sandbox['hushed_findings']['node']);
    $this->assertArrayHasKey('body', $sandbox['hushed_findings']['node'][$nid]);
    $this->assertEquals('Javascript', $sandbox['hushed_findings']['node'][$nid]['body'][0]);
    $this->assertEquals('fb3f22ac0c23ef791946d0f321511f11d824c09f2cf830c7f2ea646fefd55e0f', $sandbox['hushed_findings']['node'][$nid]['body']['hash']);
  }

  /**
   * Create a test node.
   *
   * @param bool $pass
   *   If the node should contain JavaScript or not.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTestNode(bool $pass = TRUE): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test script node',
      'body' => [
        'value' => $pass ? 'Hello world' : '<script>alert("x")</script>',
        'format' => 'basic_html',
      ],
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();
    return $node;
  }

}
