<?php

declare(strict_types=1);

namespace Drupal\Tests\simplenews\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\SubscriberInterface;

/**
 * Tests spooling a send-on-publish issue after the node has been saved.
 *
 * Covers two symptoms of the same root cause: spooling used to run in
 * hook_node_presave(), before the node's final saved state is known.
 *
 * - A new node has no ID yet in hook_node_presave(), so the recipient
 *   handler built the spool row with entity_id = NULL and the save aborted.
 * - Another module can still change the node's published state after
 *   hook_node_presave() runs (Scheduler does this in hook_entity_presave(),
 *   which runs after node-specific hooks), so a decision made during
 *   hook_node_presave() can be based on a published state that does not
 *   match what actually gets saved.
 *
 * @group simplenews
 *
 * @see https://www.drupal.org/project/simplenews/issues/2625412
 * @see https://www.drupal.org/project/simplenews/issues/3438042
 */
class SendOnPublishTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'views',
    'node',
    'simplenews',
    'simplenews_republish_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('simplenews_subscriber');
    $this->installEntitySchema('simplenews_subscriber_history');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('simplenews', ['simplenews_mail_spool']);
    $this->installConfig(['system']);

    // Send via cron, so addIssue() does not attempt an immediate send (which
    // needs a fully configured mailer that is out of scope for this test).
    $this->config('simplenews.settings')->set('mail.use_cron', TRUE)->save();

    // A minimal newsletter-enabled content type with only the issue field, so
    // the test does not depend on a body field (its storage type varies by
    // Drupal version) and stays focused on the spool behaviour.
    NodeType::create(['type' => 'newsletter', 'name' => 'Newsletter'])->save();
    FieldStorageConfig::create([
      'field_name' => 'simplenews_issue',
      'entity_type' => 'node',
      'type' => 'simplenews_issue',
      'settings' => ['target_type' => 'simplenews_newsletter'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'simplenews_issue',
      'entity_type' => 'node',
      'bundle' => 'newsletter',
      'label' => 'Issue',
    ])->save();

    // A newsletter with one active subscriber, so the default recipient
    // handler returns a recipient and the spool insert actually runs.
    Newsletter::create(['id' => 'test', 'name' => 'Test newsletter'])->save();
    $subscriber = Subscriber::create([
      'mail' => 'subscriber@example.com',
      'status' => SubscriberInterface::ACTIVE,
    ]);
    $subscriber->subscribe('test');
    $subscriber->save();
  }

  /**
   * Creates a saved, unpublished issue that is set to send on publish.
   */
  protected function createScheduledIssue(): Node {
    $node = Node::create([
      'type' => 'newsletter',
      'title' => 'Scheduled issue',
      'status' => 0,
      'simplenews_issue' => [
        'target_id' => 'test',
        'status' => SIMPLENEWS_STATUS_SEND_PUBLISH,
      ],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Returns the number of spool rows for a node.
   */
  protected function countSpooled(Node $node): int {
    return (int) \Drupal::database()->select('simplenews_mail_spool', 's')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $node->id())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * A new node published on its first save with SEND_PUBLISH is spooled.
   *
   * Spooling used to run in hook_node_presave(), where a new node has no ID
   * yet, so the recipient handler built the spool row with
   * entity_id = $issue->id() = NULL and the save aborted (a malformed INSERT
   * for the select-based handler, or a "Column 'entity_id' cannot be null"
   * integrity violation for entity-query handlers). Spooling in
   * hook_node_insert() instead runs after the node has an ID.
   */
  public function testSendOnPublishNewNode(): void {
    $node = Node::create([
      'type' => 'newsletter',
      'title' => 'Issue one',
      'status' => 1,
      'simplenews_issue' => [
        'target_id' => 'test',
        'status' => SIMPLENEWS_STATUS_SEND_PUBLISH,
      ],
    ]);

    // The node's first save. Without the hook_node_insert() deferral this
    // would abort, because the issue is spooled before the node has an ID.
    $node->save();

    // Once fixed, the issue is spooled against the now-saved node.
    $this->assertEquals(1, $this->countSpooled($node), 'The issue is spooled to the one active subscriber.');

    // The issue moves out of the send-on-publish state once spooled.
    $this->assertEquals(SIMPLENEWS_STATUS_SEND_PENDING, (int) $node->simplenews_issue->status);
  }

  /**
   * An issue that ends up unpublished must not be sent.
   *
   * Simulates Scheduler unpublishing a node with a future publish date: the
   * user publishes the node, but the helper unpublishes it again in entity
   * presave, after simplenews's node hook has run.
   */
  public function testNotSentWhenUnpublishedAfterPresave(): void {
    $node = $this->createScheduledIssue();

    \Drupal::state()->set('simplenews_republish_test.unpublish', TRUE);
    $node->setPublished();
    $node->save();

    $this->assertEquals(0, $this->countSpooled($node), 'Not spooled when it ends up unpublished.');

    // It stays queued to send on publish, and unpublished.
    $node = Node::load($node->id());
    $this->assertEquals(SIMPLENEWS_STATUS_SEND_PUBLISH, (int) $node->simplenews_issue->status);
    $this->assertFalse($node->isPublished());
  }

  /**
   * An issue that ends up published in an update must be sent.
   *
   * This is Scheduler's cron path: when the publish date arrives, the node is
   * published and saved, an ordinary update.
   */
  public function testSentWhenPublishedByUpdate(): void {
    $node = $this->createScheduledIssue();

    $node->setPublished();
    $node->save();

    $this->assertEquals(1, $this->countSpooled($node), 'Spooled when published.');
    $node = Node::load($node->id());
    $this->assertEquals(SIMPLENEWS_STATUS_SEND_PENDING, (int) $node->simplenews_issue->status);
  }

  /**
   * An issue published by another module in entity presave must be sent.
   *
   * Simulates Scheduler's publish-immediately behaviour for past publish
   * dates: the node is saved unpublished, but the helper publishes it in
   * entity presave, after simplenews's node hook has run.
   */
  public function testSentWhenPublishedAfterPresave(): void {
    $node = $this->createScheduledIssue();

    \Drupal::state()->set('simplenews_republish_test.publish', TRUE);
    // An ordinary re-save; the node is still unpublished when node presave
    // runs, and only becomes published in the helper's entity presave.
    $node->save();

    $this->assertEquals(1, $this->countSpooled($node), 'Spooled when published in entity presave.');
    $node = Node::load($node->id());
    $this->assertEquals(SIMPLENEWS_STATUS_SEND_PENDING, (int) $node->simplenews_issue->status);
    $this->assertTrue($node->isPublished());
  }

}
