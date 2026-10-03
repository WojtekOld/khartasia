<?php

namespace Drupal\copyright_footer\Plugin\Block;

/**
 * @file
 * Contains \Drupal\copyright_footer\Plugin\Block\CopyrightFooter.
 */

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Copyright Footer module for Block.
 */
#[Block(
  id: 'copyright_footer',
  admin_label: new TranslatableMarkup('Copyright Footer'),
  category: new TranslatableMarkup('Custom'),
)]
class CopyrightFooter extends BlockBase implements ContainerFactoryPluginInterface, CopyrightFooterInterface {

  /**
   * Constructs a CopyrightFooter block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {

    return [
      'organization_name' => '',
      'organization_url' => NULL,
      'all_rights_reserved_position' => '',
      'year_origin' => '',
      'year_to_date' => '',
      'version' => '',
      'version_url' => NULL,
      'copyright_format' => '',
      'label_display' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    $organization_url = trim($form_state->getValue('organization_url', '') ?: '');
    $version_url = trim($form_state->getValue('version_url', '') ?: '');

    if ($organization_url !== '' && !UrlHelper::isValid($organization_url, TRUE)) {
      $form_state->setErrorByName('organization_url', $this->t('The organization URL is invalid.'));
    }

    if ($version_url !== '' && !UrlHelper::isValid($version_url, TRUE)) {
      $form_state->setErrorByName('version_url', $this->t('The version URL is invalid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {

    $form['organization_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization name'),
      '#default_value' => $this->configuration['organization_name'],
    ];

    $form['organization_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Organization URL'),
      '#description' => $this->t('Leave blank if not necessary.'),
      '#default_value' => $this->configuration['organization_url'],
    ];

    $form['year_origin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year origin from'),
      '#description' => $this->t('Leave blank if not necessary.'),
      '#default_value' => $this->configuration['year_origin'],
    ];

    $form['year_to_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year to date'),
      '#description' => $this->t('Leave blank and the current year (@year) is shown automatically. Use a 4-digit year.',
        ['@year' => $this->getCurrentYear()]),
      '#default_value' => $this->configuration['year_to_date'],
    ];

    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#description' => $this->t('Leave blank if not necessary.'),
      '#default_value' => $this->configuration['version'],
    ];

    $form['version_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Version URL'),
      '#description' => $this->t('Leave blank if not necessary. It works w/ the version number above.')
      . $this->t("If you don't input the version number, this field will be simply ignored."),
      '#default_value' => $this->configuration['version_url'],
    ];

    $form['all_rights_reserved_position'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display "All Rights Reserved."'),
      '#options' => [
        self::ALL_RIGHTS_RESERVED_POSITION_NONE => $this->t('Do not display'),
        self::ALL_RIGHTS_RESERVED_POSITION_ORGANIZATION => $this->t('After the organization name'),
        self::ALL_RIGHTS_RESERVED_POSITION_VERSION => $this->t('After the version'),
      ],
      '#default_value' => $this->getAllRightsReservedPosition(),
    ];

    $form['copyright_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom copyright format'),
      '#description' => $this->t(
        'Supported tokens: @tokens. Leave blank for the default translated output. HTML is escaped.',
        ['@tokens' => implode(', ', self::SUPPORTED_TOKENS)],
      ),
      '#default_value' => $this->configuration['copyright_format'],
      '#maxlength' => 512,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $year_origin = $this->normalizeYearValue($form_state->getValue('year_origin'));
    $year_to_date = $this->normalizeYearValue($form_state->getValue('year_to_date'));
    $copyright_format = trim((string) $form_state->getValue('copyright_format'));

    $form_state->setValue('year_origin', $year_origin);
    $form_state->setValue('year_to_date', $year_to_date);
    $form_state->setValue('copyright_format', $copyright_format);

    if ($year_origin !== '' && !$this->isValidYear($year_origin)) {
      $form_state->setErrorByName('year_origin', $this->t('Year origin from must be a 4-digit year.'));
    }

    if ($year_to_date !== '' && !$this->isValidYear($year_to_date)) {
      $form_state->setErrorByName('year_to_date', $this->t('Year to date must be a 4-digit year.'));
    }

    if ($year_origin !== ''
      && $year_to_date !== ''
      && $this->isValidYear($year_origin) && $this->isValidYear($year_to_date)
      && (int) $year_origin > (int) $year_to_date
    ) {
      $form_state->setErrorByName('year_to_date', $this->t('Year to date must be greater than or equal to Year origin from.'));
    }

    $unsupported_tokens = $this->findUnsupportedTokens($copyright_format);
    if ($unsupported_tokens !== []) {
      $form_state->setErrorByName(
        'copyright_format',
        $this->t('Unsupported token(s): @tokens. Supported tokens are: @supported.', [
          '@tokens' => implode(', ', $unsupported_tokens),
          '@supported' => implode(', ', self::SUPPORTED_TOKENS),
        ]),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['organization_name'] = $form_state->getValue('organization_name');
    $organization_url = trim($form_state->getValue('organization_url', '') ?: '');
    $this->configuration['organization_url'] = $organization_url !== '' ? $organization_url : NULL;
    $this->configuration['all_rights_reserved_position'] = $this->normalizeAllRightsReservedPosition(
      $form_state->getValue('all_rights_reserved_position'),
    );
    unset($this->configuration['all_rights_reserved']);
    $this->configuration['year_origin'] = $form_state->getValue('year_origin');
    $this->configuration['year_to_date'] = $form_state->getValue('year_to_date');
    $this->configuration['version'] = $form_state->getValue('version');
    $version_url = trim($form_state->getValue('version_url', '') ?: '');
    $this->configuration['version_url'] = $version_url !== '' ? $version_url : NULL;
    $this->configuration['copyright_format'] = $form_state->getValue('copyright_format');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $year = $this->getCurrentYear();

    // From $year_to_date to Present.
    $year_to_date = empty($this->configuration['year_to_date'])
      ? $year
      : $this->configuration['year_to_date'];

    // Organization.
    $organization = (string) ($this->configuration['organization_name'] ?? '');
    $organization_url = $this->buildSafeUrl($this->configuration['organization_url'] ?: '');

    // Organization w/ URL.
    if (!empty($organization)
    && $organization_url instanceof Url) {
      $organization = Link::fromTextAndUrl($this->configuration['organization_name'], $organization_url)->toString();
    }

    $all_rights_reserved_position = $this->getAllRightsReservedPosition();
    if ($all_rights_reserved_position === self::ALL_RIGHTS_RESERVED_POSITION_ORGANIZATION
      && $organization !== '') {
      $organization = $this->appendAllRightsReserved($organization);
    }

    // Version.
    $version = (string) ($this->configuration['version'] ?? '');
    if (!empty($version)) {
      $version_text = $this->t('ver.@version', [
        '@version' => $version,
      ]);
      $version_url = $this->buildSafeUrl($this->configuration['version_url'] ?: '');

      $version = $version_text;

      // Version w/ URL.
      if ($version_url instanceof Url) {
        $version = $this->t('ver.@version', [
          '@version' => Link::fromTextAndUrl($this->configuration['version'], $version_url)->toString(),
        ]);
      }

      if ($all_rights_reserved_position === self::ALL_RIGHTS_RESERVED_POSITION_VERSION) {
        $version = $this->appendAllRightsReserved($version);
      }
    }

    $single_year = empty($this->configuration['year_origin'])
      || ("{$this->configuration['year_origin']}" === $year_to_date);

    if (empty($this->configuration['copyright_format'])) {
      return $single_year
        ? [
          '#type' => 'markup',
          '#markup' => $this->t('Copyright &copy; @year @organization @version', [
            '@year' => $year,
            '@organization' => $organization,
            '@version' => $version,
          ]),
        ]
        : [
          '#type' => 'markup',
          '#markup' => $this->t('Copyright &copy; @year_origin-@year_to_date @organization @version', [
            '@year_origin' => $this->configuration['year_origin'],
            '@year_to_date' => $year_to_date,
            '@organization' => $organization,
            '@version' => $version,
          ]),
        ];
    }

    $display_year = $single_year
      ? $year
      : "{$this->configuration['year_origin']}-{$year_to_date}";

    return $this->buildCustomFormat([
      '[copyright]' => '©',
      '[year]' => $display_year,
      '[start-year]' => empty($this->configuration['year_origin'])
        ? $year
        : $this->configuration['year_origin'],
      '[end-year]' => $year_to_date,
      '[organization-name]' => $organization,
      '[version]' => $version,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $year_origin = $this->configuration['year_origin'] ?: '';
    $year_to_date = $this->configuration['year_to_date'] ?: '';

    if ($year_origin !== '' && $year_to_date !== '') {
      return Cache::PERMANENT;
    }

    return Cache::mergeMaxAges(parent::getCacheMaxAge(), $this->getSecondsUntilNextYear());
  }

  /**
   * Gets the current year in the configured site timezone.
   */
  protected function getCurrentYear(): string {
    return $this->dateFormatter->format(
      $this->time->getRequestTime(),
      'custom',
      'Y',
      $this->getSiteTimezone(),
    );
  }

  /**
   * Gets the configured site timezone.
   */
  protected function getSiteTimezone(): string {
    return $this->configFactory->get('system.date')->get('timezone.default') ?: date_default_timezone_get();
  }

  /**
   * Gets the seconds remaining until the next site-local year starts.
   */
  protected function getSecondsUntilNextYear(): int {
    $request_time = $this->time->getRequestTime();
    $timezone = new \DateTimeZone($this->getSiteTimezone());
    $current_date = (new \DateTimeImmutable("@$request_time"))->setTimezone($timezone);
    $next_year = (int) $current_date->format('Y') + 1;
    $next_year_start = new \DateTimeImmutable("$next_year-01-01 00:00:00", $timezone);

    return max(1, $next_year_start->getTimestamp() - $request_time);
  }

  /**
   * Builds a custom format from escaped text and supported token fragments.
   *
   * @param array<string, string|MarkupInterface> $tokens
   *   Token values keyed by their bracketed token names.
   *
   * @return array
   *   A render array containing escaped text and safe generated links.
   */
  private function buildCustomFormat(array $tokens): array {
    $format = (string) $this->configuration['copyright_format'];
    $parts = preg_split(
      '/(\[[a-z][a-z-]*\])/',
      $format,
      -1,
      PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
    );

    $build = [];
    foreach ($parts ?: [] as $part) {
      $value = $tokens[$part] ?? $part;
      $build[] = $value instanceof MarkupInterface
        ? ['#markup' => $value]
        : ['#plain_text' => $value];
    }

    return $build;
  }

  /**
   * Finds bracketed tokens that are not supported.
   *
   * @return string[]
   *   Unsupported tokens in alphabetical order.
   */
  private function findUnsupportedTokens(string $format): array {
    preg_match_all('/\[[^\[\]]+\]/', $format, $matches);
    $unsupported_tokens = array_values(array_diff(
      array_unique($matches[0]),
      self::SUPPORTED_TOKENS,
    ));
    sort($unsupported_tokens);

    return $unsupported_tokens;
  }

  /**
   * Gets the configured All Rights Reserved display position.
   *
   * Supports the former boolean setting by treating an enabled value as the
   * organization-name option until the block configuration is saved again.
   */
  private function getAllRightsReservedPosition(): string {
    $position = $this->configuration['all_rights_reserved_position'] ?? '';
    if ($position !== '') {
      return $this->normalizeAllRightsReservedPosition($position);
    }

    return !empty($this->configuration['all_rights_reserved'])
      ? self::ALL_RIGHTS_RESERVED_POSITION_ORGANIZATION
      : self::ALL_RIGHTS_RESERVED_POSITION_NONE;
  }

  /**
   * Normalizes an All Rights Reserved display position.
   */
  private function normalizeAllRightsReservedPosition(mixed $position): string {
    return is_string($position) && in_array($position, [
      self::ALL_RIGHTS_RESERVED_POSITION_NONE,
      self::ALL_RIGHTS_RESERVED_POSITION_ORGANIZATION,
      self::ALL_RIGHTS_RESERVED_POSITION_VERSION,
    ], TRUE)
      ? $position
      : self::ALL_RIGHTS_RESERVED_POSITION_NONE;
  }

  /**
   * Appends the All Rights Reserved notice to safe or plain text.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $value
   *   The text or safe markup to extend.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The extended value.
   */
  private function appendAllRightsReserved(string|MarkupInterface $value): string|MarkupInterface {
    $rights_reserved = $this->t('All Rights Reserved.');
    if ($value instanceof MarkupInterface) {
      return Markup::create((string) $value . ' ' . Html::escape((string) $rights_reserved));
    }

    return $this->t('@value @rights_reserved', [
      '@value' => $value,
      '@rights_reserved' => $rights_reserved,
    ]);
  }

  /**
   * Normalizes an optional year field.
   */
  private function normalizeYearValue(mixed $value): string {
    return trim((string) $value);
  }

  /**
   * Checks whether a value is a valid 4-digit year.
   */
  private function isValidYear(string $value): bool {
    return (bool) preg_match('/^\d{4}$/', $value);
  }

  /**
   * Build URL object from URI with safe fallback.
   *
   * @param string $uri
   *   The target URI.
   *
   * @return \Drupal\Core\Url|null
   *   The URL object when valid.
   */
  private function buildSafeUrl(string $uri): ?Url {
    $uri = trim($uri);
    if ($uri === '' || !UrlHelper::isValid($uri, TRUE)) {
      return NULL;
    }

    return Url::fromUri($uri);
  }

}
