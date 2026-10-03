<?php

namespace Drupal\copyright_footer\Plugin\Block;

/**
 * Defines the contract for the Copyright Footer block plugin.
 */
interface CopyrightFooterInterface {

  /**
   * The module development version.
   */
  public const VERSION = '3.x-dev';

  /**
   * Do not display an All Rights Reserved notice.
   */
  public const ALL_RIGHTS_RESERVED_POSITION_NONE = 'none';

  /**
   * Display an All Rights Reserved notice after the organization name.
   */
  public const ALL_RIGHTS_RESERVED_POSITION_ORGANIZATION = 'organization';

  /**
   * Display an All Rights Reserved notice after the version.
   */
  public const ALL_RIGHTS_RESERVED_POSITION_VERSION = 'version';

  /**
   * Tokens supported by the optional copyright format.
   */
  public const SUPPORTED_TOKENS = [
    '[copyright]',
    '[year]',
    '[start-year]',
    '[end-year]',
    '[organization-name]',
    '[version]',
  ];

}
