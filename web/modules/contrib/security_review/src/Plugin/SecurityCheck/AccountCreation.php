<?php

declare(strict_types=1);

namespace Drupal\security_review\Plugin\SecurityCheck;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\security_review\Attribute\SecurityCheck;
use Drupal\security_review\CheckResult;
use Drupal\security_review\SecurityCheckBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks whether visitors can create accounts.
 */
#[SecurityCheck(
  id: 'account_creation',
  title: new TranslatableMarkup('Account Creation'),
  description: new TranslatableMarkup('Checks whether visitors can create accounts.'),
  namespace: new TranslatableMarkup('Security Review'),
  success_message: new TranslatableMarkup('Visitors cannot create accounts.'),
  info_message: new TranslatableMarkup('Visitors can create accounts.'),
  help: [
    new TranslatableMarkup("Note: This is not a security vulnerability per se, but more a security recommendation."),
    new TranslatableMarkup('Limiting the ability of untrusted users to create accounts. Account creation is the single greatest target of spammers and Google link jackers (people who will try and post links to their own sites from your own simply to promote their search engine rank). Allowing anonymous users to create accounts with no oversight or confirmation encourages the creation of bogus accounts by automated programs, otherwise known as bots. Attackers will look to create accounts in your Drupal site because by default Drupal distinguishes between two types of users: authenticated and unauthenticated users. If an unauthenticated (anonymous) user can create an account they can effectively elevate their own privileges. Read more about <a href="https://www.drupal.org/docs/user_guide/en/config-user.html">Configuring User Account Settings</a> on Drupal.org.'),
  ]
)]
class AccountCreation extends SecurityCheckBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(bool $cli = FALSE): void {
    $result = CheckResult::SUCCESS;
    $findings = [];

    $userConfig = $this->configFactory->get('user.settings');
    $register = $userConfig->get('register');

    if (in_array($register, ['visitors', 'visitors_admin_approval'])) {
      $findings[] = $register;
    }

    if (!empty($findings)) {
      $result = CheckResult::INFO;
    }

    $this->createResult($result, $findings);
  }

  /**
   * {@inheritdoc}
   */
  public function getDetails(array $findings, array $hushed = [], bool $returnString = FALSE): array|string {
    $output = $returnString ? '' : [];

    if (empty($findings)) {
      return $output;
    }

    $paragraphs = [];
    $paragraphs[] = $this->t("Visitors are able to create accounts.");
    if ($returnString) {
      $output .= implode("", $paragraphs);
    }
    else {
      $output[] = [
        '#theme' => 'check_evaluation',
        '#finding_items' => $paragraphs,
      ];
    }

    return $output;
  }

}
