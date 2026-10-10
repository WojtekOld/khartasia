<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Kernel\Plugin\Block;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\term_glossary\Plugin\Block\GlossaryAlphabeticalBock;
use Drupal\Tests\term_glossary\Traits\GlossaryTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the GlossaryAlphabeticalBock block plugin.
 *
 * @group term_glossary
 */
#[CoversClass(GlossaryAlphabeticalBock::class)]
#[Group('term_glossary')]
#[RunTestsInSeparateProcesses]
class GlossaryAlphabeticalBockTest extends KernelTestBase {

  use GlossaryTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'taxonomy',
    'text',
    'field',
    'filter',
    'term_glossary',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installGlossaryTestDependencies();
  }

  /**
   * Creates a block plugin instance with the given configuration.
   *
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\term_glossary\Plugin\Block\GlossaryAlphabeticalBock
   *   The block plugin instance.
   */
  protected function createBlockInstance(array $configuration = []): GlossaryAlphabeticalBock {
    $block_manager = $this->container->get('plugin.manager.block');
    $block = $block_manager->createInstance('glossary_alphabetical_bock', $configuration);
    assert($block instanceof GlossaryAlphabeticalBock);
    return $block;
  }

  /**
   * Tests that the default configuration is correct.
   *
   * Verifies that search_type defaults to 'all', that plugin
   * discovery resolved the correct id and provider, and that
   * parent::defaultConfiguration() was merged in (label_display).
   */
  public function testDefaultConfiguration(): void {
    $block = $this->createBlockInstance();
    $configuration = $block->getConfiguration();

    $this->assertSame('all', $configuration['search_type']);
    $this->assertSame('glossary_alphabetical_bock', $configuration['id']);
    $this->assertSame('term_glossary', $configuration['provider']);
    $this->assertArrayHasKey('label_display', $configuration);
  }

  /**
   * Tests the build output with default configuration.
   *
   * Asserts the render array uses the correct theme hook,
   * passes the default search_type as #type, and attaches
   * the glossary.alpha library.
   */
  public function testBuildDefault(): void {
    $block = $this->createBlockInstance();
    $build = $block->build();

    $this->assertSame('glossary_alphabetical_block', $build['#theme']);
    $this->assertSame('all', $build['#type']);
    $this->assertContains('term_glossary/glossary.alpha', $build['#attached']['library']);
  }

  /**
   * Tests the build output with search_only configuration.
   *
   * Confirms that setting search_type to 'search_only' flows
   * through to #type in the render array while theme and
   * library remain unchanged.
   */
  public function testBuildSearchOnly(): void {
    $block = $this->createBlockInstance(['search_type' => 'search_only']);
    $build = $block->build();

    $this->assertSame('glossary_alphabetical_block', $build['#theme']);
    $this->assertSame('search_only', $build['#type']);
    $this->assertContains('term_glossary/glossary.alpha', $build['#attached']['library']);
  }

  /**
   * Tests the build output with only_letters configuration.
   *
   * Completes coverage of all three search_type variants in
   * build(), ensuring 'only_letters' produces the expected
   * render array.
   */
  public function testBuildOnlyLetters(): void {
    $block = $this->createBlockInstance(['search_type' => 'only_letters']);
    $build = $block->build();

    $this->assertSame('glossary_alphabetical_block', $build['#theme']);
    $this->assertSame('only_letters', $build['#type']);
    $this->assertContains('term_glossary/glossary.alpha', $build['#attached']['library']);
  }

  /**
   * Tests the block configuration form structure.
   *
   * Validates that search_type is a required select with
   * exactly three options (search_only, all, only_letters)
   * and that the default value matches the default config.
   */
  public function testBlockForm(): void {
    $block = $this->createBlockInstance();
    $form_state = new FormState();
    $form = $block->buildConfigurationForm([], $form_state);

    $this->assertArrayHasKey('search_type', $form);
    $this->assertSame('select', $form['search_type']['#type']);
    $this->assertTrue($form['search_type']['#required']);
    $this->assertSame('all', $form['search_type']['#default_value']);

    $options = $form['search_type']['#options'];
    $this->assertCount(3, $options);
    $this->assertArrayHasKey('search_only', $options);
    $this->assertArrayHasKey('all', $options);
    $this->assertArrayHasKey('only_letters', $options);
  }

  /**
   * Tests the form default value reflects the block configuration.
   *
   * Creates an instance with 'only_letters' and verifies that
   * the form #default_value reads from configuration rather
   * than being hardcoded to the default.
   */
  public function testBlockFormDefaultValueReflectsConfiguration(): void {
    $block = $this->createBlockInstance(['search_type' => 'only_letters']);
    $form_state = new FormState();
    $form = $block->buildConfigurationForm([], $form_state);

    $this->assertSame('only_letters', $form['search_type']['#default_value']);
  }

  /**
   * Tests the block submit handler updates the configuration.
   *
   * Asserts search_type is 'all' before submit, then calls
   * submitConfigurationForm() with 'search_only' and verifies
   * the configuration was updated. Extra form state values
   * (label, label_display, provider) satisfy the parent class.
   */
  public function testBlockSubmit(): void {
    $block = $this->createBlockInstance();
    $this->assertSame('all', $block->getConfiguration()['search_type']);

    $form_state = new FormState();
    $form_state->setValues([
      'search_type' => 'search_only',
      'label' => 'Glossary',
      'label_display' => 'visible',
      'provider' => 'term_glossary',
    ]);

    $form = [];
    $block->submitConfigurationForm($form, $form_state);

    $this->assertSame('search_only', $block->getConfiguration()['search_type']);
  }

}
