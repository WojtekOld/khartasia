<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary_per_node\Functional;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Term Glossary per node functionality.
 *
 * @group term_glossary_per_node
 */
class TermGlossaryPerNodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'term_glossary',
    'term_glossary_per_node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with appropriate permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The node type for testing.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * Test vocabularies.
   *
   * @var \Drupal\taxonomy\VocabularyInterface[]
   */
  protected $vocabularies = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the "article" content type.
    $this->nodeType = $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create two vocabularies.
    for ($i = 1; $i <= 2; $i++) {
      $vocab = Vocabulary::create([
        'vid' => 'vocab' . $i,
        'name' => 'Vocabulary ' . $i,
      ]);
      $vocab->save();
      $this->vocabularies['vocab' . $i] = $vocab;
    }

    // Create users.
    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer nodes',
      'create article content',
      'edit own article content',
      'access content',
      'edit term glossary node display',
    ]);
  }

  /**
   * Tests programmatic setup and default value validation for settings.
   *
   * This method ensures that:
   * - The glossary settings can be enabled and configured programmatically.
   * - Default values such as active state and vocabulary lists are correctly
   *   applied when creating nodes of a specific content type.
   * - Changes to the default settings are reflected in newly created nodes.
   */
  public function testBundleDefaultValuesProgrammatically(): void {
    // 1. Enable per-node glossary and set defaults on the node type.
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'active', TRUE);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override', TRUE);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override_default', 1);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'default_vocabulary', 'vocab1,vocab2');
    $this->nodeType->save();

    // Check programmatic node creation.
    $node = $this->drupalCreateNode(['type' => 'article']);
    $this->assertEquals(1, $node->term_glossary_active->value, 'Default active state is 1');
    $this->assertEquals('vocab1,vocab2', $node->term_glossary_vocabulary->value, 'Default vocabulary is vocab1,vocab2');

    // 2. Change defaults on the node type.
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override_default', 0);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'default_vocabulary', 'vocab2');
    $this->nodeType->save();

    $node2 = $this->drupalCreateNode(['type' => 'article']);
    $this->assertEquals(0, $node2->term_glossary_active->value, 'Default active state is 0');
    $this->assertEquals('vocab2', $node2->term_glossary_vocabulary->value, 'Default vocabulary is vocab2');
  }

  /**
   * Tests node form per-node settings.
   *
   * Enables per-node glossary with override and vocabulary
   * override on the article type. Logs in, visits the node
   * add form, asserts the term_glossary_active checkbox is
   * present and checked by default. Submits the form with
   * active=FALSE and vocabulary='vocab2', then loads the
   * saved node and verifies both values were persisted.
   */
  public function testNodeFormSettings(): void {
    $this->drupalLogin($this->adminUser);

    // Enable per-node glossary and set defaults on the node type.
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'active', TRUE);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override', TRUE);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override_vocabulary', TRUE);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override_default', 1);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'default_vocabulary', 'vocab1');
    $this->nodeType->save();

    // Visit node add form.
    $this->drupalGet('node/add/article');
    $assert_session = $this->assertSession();
    $assert_session->fieldExists('term_glossary_active');
    $assert_session->checkboxChecked('term_glossary_active');

    // We can't easily check the default value of entity_autocomplete with
    // multiple values via fieldExists but we can check if it is present.
    $assert_session->elementExists('css', 'input[name="term_glossary_vocabulary"]');

    // Submit node with custom settings.
    $edit = [
      'title[0][value]' => 'Test Node',
      'term_glossary_active' => FALSE,
      'term_glossary_vocabulary' => 'Vocabulary 2 (vocab2)',
    ];
    $this->submitForm($edit, 'Save');

    $node = $this->drupalGetNodeByTitle('Test Node');
    $this->assertEquals(0, $node->term_glossary_active->value);
    $this->assertEquals('vocab2', $node->term_glossary_vocabulary->value);
  }

  /**
   * Tests disabling override hides the node form fields.
   *
   * Enables per-node glossary but sets override=FALSE on the
   * article type. Logs in and visits the node add form.
   * Verifies neither term_glossary_active nor
   * term_glossary_vocabulary fields are present — editors
   * cannot override per-node settings when override is off.
   */
  public function testDisablingOverride(): void {
    $this->drupalLogin($this->adminUser);

    // Disable per-node override.
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'active', TRUE);
    $this->nodeType->setThirdPartySetting('term_glossary_per_node', 'override', FALSE);
    $this->nodeType->save();

    // Visit node add form.
    $this->drupalGet('node/add/article');
    $assert_session = $this->assertSession();
    $assert_session->fieldNotExists('term_glossary_active');
    $assert_session->fieldNotExists('term_glossary_vocabulary[target_id]');
  }

}
