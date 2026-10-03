<?php

namespace Drupal\Tests\copyright_footer\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormState;
use Drupal\copyright_footer\Plugin\Block\CopyrightFooter;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests validation for the copyright footer block settings.
 *
 * @group Copyright Footer
 */
#[RunTestsInSeparateProcesses]
class CopyrightFooterBlockValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'copyright_footer',
  ];

  /**
   * Tests the site timezone and cache expiry for dynamic year output.
   */
  public function testDynamicYearUsesSiteTimezoneAndExpiresAtNewYear(): void {
    $timezone = 'Pacific/Kiritimati';
    $this->config('system.date')->set('timezone.default', $timezone)->save();

    $before_new_year = new \DateTimeImmutable('2026-12-31 09:59:30', new \DateTimeZone('UTC'));
    $plugin = $this->createPluginAtTime($before_new_year->getTimestamp(), [
      'year_origin' => '2020',
    ]);
    $build = $plugin->build();

    $this->assertStringContainsString('2020-2026', (string) $build['#markup']);
    $this->assertSame(30, $plugin->getCacheMaxAge());

    $after_new_year = new \DateTimeImmutable('2026-12-31 10:00:30', new \DateTimeZone('UTC'));
    $plugin = $this->createPluginAtTime($after_new_year->getTimestamp(), [
      'year_origin' => '2020',
    ]);
    $build = $plugin->build();
    $next_new_year = new \DateTimeImmutable('2028-01-01 00:00:00', new \DateTimeZone($timezone));

    $this->assertStringContainsString('2020-2027', (string) $build['#markup']);
    $this->assertSame(
      $next_new_year->getTimestamp() - $after_new_year->getTimestamp(),
      $plugin->getCacheMaxAge(),
    );
  }

  /**
   * Tests that a fixed year range is permanently cacheable.
   */
  public function testFixedYearRangeIsPermanentlyCacheable(): void {
    $plugin = $this->createPluginAtTime(1767225600, [
      'year_origin' => '2020',
      'year_to_date' => '2025',
    ]);

    $this->assertSame(Cache::PERMANENT, $plugin->getCacheMaxAge());
  }

  /**
   * Tests an explicit range that starts in the current year.
   */
  public function testCurrentYearOriginWithExplicitEndYearRendersFixedRange(): void {
    $this->config('system.date')->set('timezone.default', 'UTC')->save();
    $year_origin = random_int(1970, 9998);
    $year_to_date = $year_origin + random_int(1, 9999 - $year_origin);
    $current_year = new \DateTimeImmutable(
      "{$year_origin}-01-01 00:00:00",
      new \DateTimeZone('UTC'),
    );
    $plugin = $this->createPluginAtTime($current_year->getTimestamp(), [
      'year_origin' => (string) $year_origin,
      'year_to_date' => (string) $year_to_date,
    ]);
    $build = $plugin->build();

    $this->assertStringContainsString("{$year_origin}-{$year_to_date}", (string) $build['#markup']);
    $this->assertSame(Cache::PERMANENT, $plugin->getCacheMaxAge());
  }

  /**
   * Tests that year fields accept blank values.
   */
  public function testBlankYearsAreAllowed(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => '',
      'year_to_date' => '',
    ]);

    $plugin->blockValidate([], $form_state);

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Tests that optional URL fields accept blank values and store them as NULL.
   */
  public function testBlankUrlsAreStoredAsNull(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues([
      'organization_url' => '',
      'version_url' => '  ',
    ]);

    $plugin->blockSubmit([], $form_state);

    $configuration = $plugin->getConfiguration();
    $this->assertNull($configuration['organization_url']);
    $this->assertNull($configuration['version_url']);
  }

  /**
   * Tests that the All Rights Reserved position is stored.
   */
  public function testAllRightsReservedPositionIsStored(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues(['all_rights_reserved_position' => 'version']);

    $plugin->blockSubmit([], $form_state);

    $this->assertSame('version', $plugin->getConfiguration()['all_rights_reserved_position']);
    $this->assertArrayNotHasKey('all_rights_reserved', $plugin->getConfiguration());
  }

  /**
   * Tests backward compatibility for the former boolean option.
   */
  public function testLegacyAllRightsReservedOptionUsesOrganizationPosition(): void {
    $plugin = $this->createPluginAtTime(1767225600, [
      'organization_name' => 'Example organization',
      'all_rights_reserved' => TRUE,
    ]);
    $build = $plugin->build();

    $this->assertStringContainsString('Example organization All Rights Reserved.', (string) $build['#markup']);
  }

  /**
   * Tests the All Rights Reserved radio options and their form position.
   */
  public function testAllRightsReservedPositionFormOptions(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form = $plugin->blockForm([], new FormState());

    $this->assertSame('radios', $form['all_rights_reserved_position']['#type']);
    $this->assertSame([
      'none' => 'Do not display',
      'organization' => 'After the organization name',
      'version' => 'After the version',
    ], array_map('strval', $form['all_rights_reserved_position']['#options']));
    $this->assertSame('none', $form['all_rights_reserved_position']['#default_value']);
    $this->assertSame([
      'version_url',
      'all_rights_reserved_position',
      'copyright_format',
    ], array_slice(array_keys($form), -3));
    $this->assertSame(
      'Custom copyright format',
      (string) $form['copyright_format']['#title'],
    );
  }

  /**
   * Tests that year fields must be 4-digit numbers.
   */
  public function testInvalidYearFormatIsRejected(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => '20ab',
      'year_to_date' => '999',
    ]);

    $plugin->blockValidate([], $form_state);

    $errors = $form_state->getErrors();
    $this->assertSame('Year origin from must be a 4-digit year.', (string) $errors['year_origin']);
    $this->assertSame('Year to date must be a 4-digit year.', (string) $errors['year_to_date']);
  }

  /**
   * Tests that valid year ranges are accepted and normalized.
   */
  public function testValidYearRangesAreAccepted(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => ' 2000 ',
      'year_to_date' => ' 2099 ',
    ]);

    $plugin->blockValidate([], $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame('2000', $form_state->getValue('year_origin'));
    $this->assertSame('2099', $form_state->getValue('year_to_date'));

    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => '2026',
      'year_to_date' => '2026',
    ]);

    $plugin->blockValidate([], $form_state);

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Tests that the ending year cannot be earlier than the origin year.
   */
  public function testDescendingYearRangeIsRejected(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => '2026',
      'year_to_date' => '2025',
    ]);

    $plugin->blockValidate([], $form_state);

    $errors = $form_state->getErrors();
    $this->assertSame('Year to date must be greater than or equal to Year origin from.', (string) $errors['year_to_date']);
  }

  /**
   * Tests validation and normalization of custom format tokens.
   */
  public function testCopyrightFormatTokensAreValidated(): void {
    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', []);
    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => '',
      'year_to_date' => '',
      'copyright_format' => '  [copyright] © [year] [start-year] [end-year] [organization-name] [version]  ',
    ]);

    $plugin->blockValidate([], $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame(
      '[copyright] © [year] [start-year] [end-year] [organization-name] [version]',
      $form_state->getValue('copyright_format'),
    );

    $form_state = new FormState();
    $form_state->setValues([
      'year_origin' => '',
      'year_to_date' => '',
      'copyright_format' => '[unknown] [site:name]',
    ]);

    $plugin->blockValidate([], $form_state);

    $errors = $form_state->getErrors();
    $this->assertSame(
      'Unsupported token(s): [site:name], [unknown]. Supported tokens are: '
      . '[copyright], [year], [start-year], [end-year], '
      . '[organization-name], [version].',
      (string) $errors['copyright_format'],
    );
  }

  /**
   * Creates the block plugin with a fixed request timestamp.
   *
   * @param int $request_time
   *   The timestamp returned by the time service.
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\copyright_footer\Plugin\Block\CopyrightFooter
   *   The block plugin.
   */
  private function createPluginAtTime(int $request_time, array $configuration): CopyrightFooter {
    $time = $this->createStub(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($request_time);
    $this->container->set('datetime.time', $time);

    $plugin = $this->container->get('plugin.manager.block')->createInstance('copyright_footer', $configuration);
    assert($plugin instanceof CopyrightFooter);

    return $plugin;
  }

}
