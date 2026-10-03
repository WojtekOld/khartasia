<?php

namespace Drupal\Tests\copyright_footer\Functional;

use Drupal\block\Entity\Block;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test case for a Copyright Footer block.
 *
 * @group Copyright Footer
 */
#[RunTestsInSeparateProcesses]
class CopyrightFooterTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'copyright_footer',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Permissions.
   *
   * @var array
   */
  protected array $perms = [
    'access content',
    'administer blocks',
  ];

  /**
   * A node.
   *
   * @var \Drupal\node\Entity\Node
   */
  public const ORGANIZATION_URL = 'https://git.drupalcode.org/project/copyright_footer';

  /**
   * A version URL.
   *
   * @var string
   */
  public const VERSION_URL = 'https://git.drupalcode.org/project/copyright_footer.git';

  /**
   * A version string used for testing.
   *
   * @var string
   */
  public const VERSION = '1.2.3';

  /**
   * A year of the origin.
   *
   * @var string
   */
  public string $yearOrigin;

  /**
   * A year to date.
   *
   * @var string
   */
  public string $yearToDate;

  /**
   * A organization name.
   *
   * @var string
   */
  protected string $organizationName = 'Copyright Footer';

  /**
   * A year.
   *
   * @var string
   */
  protected string $year;

  /**
   * A node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * A URL of the node.
   *
   * @var string
   */
  protected string $nodeUrl = '';

  /**
   * Set up test.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser($this->perms);
    $this->drupalLogin($user);

    // Create a dummy node.
    $this->createContentType(['type' => 'page']);
    $this->node = $this->drupalCreateNode();

    // Setup configuration dummy data.
    $this->year = (new \DateTime())->format('Y');
    $current_year = (int) $this->year;
    $this->yearOrigin = (string) random_int(1000, $current_year - 1);
    $this->yearToDate = (string) random_int($current_year + 1, 9999);
    $this->nodeUrl = "/node/{$this->node->id()}";
  }

  /**
   * Test the copyright footer black.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testConfigurationAllBlank(): void {

    // Place a Copyright footer block - All blank.
    $block = $this->placeCopyrightFooterBlock();
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("Copyright © {$this->year}");
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Organization name only.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOrganizationNameOnly(): void {

    // Place a Copyright footer block - Organization name only.
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains("© {$this->year} {$this->organizationName}");
    $this->assertSession()->elementNotExists('css', 'a[href="' . self::ORGANIZATION_URL . '"]');
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Organization w/ URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOrganizationWithUrl(): void {
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'organization_url' => self::ORGANIZATION_URL,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("© {$this->year} {$this->organizationName}");
    $this->assertSession()->elementExists('css', sprintf('a[href="%s"]', self::ORGANIZATION_URL));
    $block->delete();
  }

  /**
   * Tests displaying All Rights Reserved after the organization name.
   */
  public function testAllRightsReservedAfterOrganizationName(): void {
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'organization_url' => self::ORGANIZATION_URL,
      'all_rights_reserved_position' => 'organization',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(
      "© {$this->year} {$this->organizationName} All Rights Reserved.",
    );
    $this->assertSession()->elementTextEquals(
      'css',
      sprintf('a[href="%s"]', self::ORGANIZATION_URL),
      $this->organizationName,
    );
    $block->delete();
  }

  /**
   * Tests displaying All Rights Reserved after the version.
   */
  public function testAllRightsReservedAfterVersion(): void {
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'version' => self::VERSION,
      'version_url' => self::VERSION_URL,
      'all_rights_reserved_position' => 'version',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(
      "© {$this->year} {$this->organizationName} ver." . self::VERSION . ' All Rights Reserved.',
    );
    $this->assertSession()->elementTextEquals(
      'css',
      sprintf('a[href="%s"]', self::VERSION_URL),
      self::VERSION,
    );
    $block->delete();
  }

  /**
   * Tests hiding All Rights Reserved.
   */
  public function testAllRightsReservedIsNotDisplayed(): void {
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'version' => self::VERSION,
      'all_rights_reserved_position' => 'none',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('All Rights Reserved.');
    $block->delete();
  }

  /**
   * Place a Copyright footer block with invalid organization URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOrganizationWithInvalidUrlFallsBackToText(): void {
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'organization_url' => 'foo',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains("Copyright © {$this->year} {$this->organizationName}");
    $this->assertSession()->elementNotExists('css', 'a[href="foo"]');
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Year origin only.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testYearOriginOnly(): void {

    $block = $this->placeCopyrightFooterBlock([
      'year_origin' => $this->yearOrigin,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains("Copyright © {$this->yearOrigin}-{$this->year}");
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Year origin only (current year).
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCurrentYearAsOriginOnly(): void {

    $date = new \DateTime();
    $current_year_origin = $date->format('Y');
    $block = $this->placeCopyrightFooterBlock([
      'year_origin' => $current_year_origin,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains("Copyright © {$current_year_origin}");
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Year origin and year to date.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testYearOriginAndYearToDate(): void {
    $block = $this->placeCopyrightFooterBlock([
      'year_origin' => $this->yearOrigin,
      'year_to_date' => $this->yearToDate,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains("Copyright © {$this->yearOrigin}-{$this->yearToDate}");
    $block->delete();
  }

  /**
   * Place a Copyright footer block with legacy settings.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBackwardsCompatibleLegacyBlockConfiguration(): void {
    $block = $this->placeCopyrightFooterBlockFromLegacyConfig([
      'organization_name' => $this->organizationName,
      'year_origin' => $this->yearOrigin,
      'year_to_date' => $this->yearToDate,
      'version' => self::VERSION,
    ]);

    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains("© {$this->yearOrigin}-{$this->yearToDate} {$this->organizationName} ver." . self::VERSION);

    $block->delete();
  }

  /**
   * Place a Copyright footer block - Version only.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testVersionOnly(): void {

    // Place a Copyright footer block - Version only.
    $block = $this->placeCopyrightFooterBlock([
      'version' => self::VERSION,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $version = self::VERSION;
    $this->assertSession()
      ->pageTextContains("Copyright © {$this->year} ver.$version");
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Version w/ URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testVersionWithUrl(): void {

    $block = $this->placeCopyrightFooterBlock([
      'version' => self::VERSION,
      'version_url' => self::VERSION_URL,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $version = self::VERSION;
    $this->assertSession()->pageTextContains("Copyright © {$this->year} ver.$version");
    $this->assertSession()->elementExists('css', sprintf('a[href="%s"]', self::VERSION_URL));
    $block->delete();
  }

  /**
   * Place a Copyright footer block with invalid version URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testVersionWithInvalidUrlFallsBackToText(): void {
    $block = $this->placeCopyrightFooterBlock([
      'version' => self::VERSION,
      'version_url' => 'foo',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $version = self::VERSION;
    $this->assertSession()->pageTextContains("Copyright © {$this->year} ver.$version");
    $this->assertSession()->elementNotExists('css', 'a[href="foo"]');
    $block->delete();
  }

  /**
   * Place a Copyright footer block - Full parameters.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFullParameters(): void {

    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'organization_url' => self::ORGANIZATION_URL,
      'year_origin' => $this->yearOrigin,
      'year_to_date' => $this->yearToDate,
      'version' => self::VERSION,
      'version_url' => self::VERSION_URL,
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $version = self::VERSION;
    $this->assertSession()->pageTextContains("© {$this->yearOrigin}-{$this->yearToDate} {$this->organizationName} ver.$version");
    $this->assertSession()->elementExists('css', sprintf('a[href="%s"]', self::ORGANIZATION_URL));
    $this->assertSession()->elementExists('css', sprintf('a[href="%s"]', self::VERSION_URL));
    $block->delete();
  }

  /**
   * Tests custom formatting with supported tokens and escaped literal text.
   */
  public function testCustomFormat(): void {
    $block = $this->placeCopyrightFooterBlock([
      'organization_name' => $this->organizationName,
      'organization_url' => self::ORGANIZATION_URL,
      'year_origin' => $this->yearOrigin,
      'year_to_date' => $this->yearToDate,
      'version' => self::VERSION,
      'version_url' => self::VERSION_URL,
      'copyright_format' => '© [year] [organization-name] [version]',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(
      "© {$this->yearOrigin}-{$this->yearToDate} {$this->organizationName} ver." . self::VERSION,
    );
    $this->assertSession()->pageTextNotContains('Copyright ©');
    $this->assertSession()->elementExists('css', sprintf('a[href="%s"]', self::ORGANIZATION_URL));
    $this->assertSession()->elementExists('css', sprintf('a[href="%s"]', self::VERSION_URL));
    $block->delete();

    $block = $this->placeCopyrightFooterBlock([
      'year_origin' => $this->yearOrigin,
      'year_to_date' => $this->yearToDate,
      'copyright_format' => '<strong>[copyright]</strong> © [start-year] to [end-year] ([year])',
    ]);
    $this->drupalGet($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(200);
    $escaped_format = "<strong>©</strong> © {$this->yearOrigin} to "
      . "{$this->yearToDate} ({$this->yearOrigin}-{$this->yearToDate})";
    $this->assertSession()->pageTextContains(
      $escaped_format,
    );
    $block->delete();
  }

  /**
   * Create a Copyright Footer block.
   *
   * @return \Drupal\block\Entity\Block
   *   The Copyright Footer block object.
   */
  private function placeCopyrightFooterBlock($settings = []): Block {
    return $this->drupalPlaceBlock('copyright_footer', [
      'organization_name' => $settings['organization_name'] ?? '',
      'organization_url' => $settings['organization_url'] ?? NULL,
      'all_rights_reserved_position' => $settings['all_rights_reserved_position'] ?? 'none',
      'year_origin' => $settings['year_origin'] ?? '',
      'year_to_date' => $settings['year_to_date'] ?? '',
      'version' => $settings['version'] ?? '',
      'version_url' => $settings['version_url'] ?? NULL,
      'copyright_format' => $settings['copyright_format'] ?? '',
    ]);
  }

  /**
   * Place a Copyright footer block from a legacy settings style.
   *
   * @param array<string, mixed> $settings
   *   Legacy block settings.
   *
   * @return \Drupal\block\Entity\Block
   *   The Copyright Footer block object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function placeCopyrightFooterBlockFromLegacyConfig(array $settings = []): Block {
    $block = Block::create([
      'id' => strtolower("copyright_footer_legacy_{$this->randomMachineName(8)}"),
      'plugin' => 'copyright_footer',
      'region' => 'content',
      'theme' => 'stark',
      'status' => TRUE,
      'visibility' => [],
      'settings' => [
        'organization_name' => $settings['organization_name'] ?? '',
        'organization_url' => $settings['organization_url'] ?? NULL,
        'year_origin' => $settings['year_origin'] ?? '',
        'year_to_date' => $settings['year_to_date'] ?? '',
        'version' => $settings['version'] ?? '',
        'version_url' => $settings['version_url'] ?? NULL,
      ],
    ]);

    $block->save();
    $this->rebuildContainer();

    return $block;
  }

}
