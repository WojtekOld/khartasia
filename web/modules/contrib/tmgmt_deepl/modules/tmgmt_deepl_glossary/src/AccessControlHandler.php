<?php

namespace Drupal\tmgmt_deepl_glossary;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the DeepL multilingual glossary entities.
 *
 * @see \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossary
 * @see \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionary
 */
class AccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'access deepl_glossary overview'),
      'update' => AccessResult::allowedIfHasPermissions($account, [
        'edit deepl_glossary entities',
        'edit deepl_glossary glossary entries',
      ], 'OR'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete deepl_glossary entities'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'add deepl_glossary entities');
  }

}
