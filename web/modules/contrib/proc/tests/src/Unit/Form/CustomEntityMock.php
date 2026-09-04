<?php
// phpcs:ignoreFile

namespace Drupal\Tests\proc\Unit\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Custom entity mock.
 *
 * @SuppressWarnings(PHPMD)
 */
class CustomEntityMock implements EntityInterface {

  /**
   *
   */
  public function id() {}

  /**
   *
   */
  public function uuid() {}

  /**
   *
   */
  public function getEntityTypeId() {
    return '';
  }

  /**
   *
   */
  public function bundle() {
    return '';
  }

  /**
   *
   */
  public function get($property_name) {}

  /**
   *
   */
  public function set($property_name, $value) {}

  /**
   *
   */
  public function hasField($field_name) {
    return TRUE;
  }

  /**
   *
   */
  public function getFieldDefinition($field_name) {}

  /**
   *
   */
  public function getTranslation($langcode) {}

  /**
   *
   */
  public function getUntranslated() {}

  /**
   *
   */
  public function language() {
    return new \Drupal\Core\Language\Language();
  }

  /**
   *
   */
  public function getTranslationLanguages($include_default = TRUE) {}

  /**
   *
   */
  public function isTranslatable() {}

  /**
   *
   */
  public function isNew() {
    return FALSE;
  }

  /**
   *
   */
  public function isDefaultTranslation() {}

  /**
   *
   */
  public function getOriginalId() {}

  /**
   *
   */
  public function getEntityType() {
    return new \Drupal\Core\Entity\EntityType(['id' => 'proc']);
  }

  /**
   *
   */
  public function getTypedData() {
    throw new \LogicException(__METHOD__ . '() is not implemented in the test mock.');
  }

  /**
   *
   */
  public function getCacheTags() {
    return [];
  }

  /**
   *
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   *
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   *
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    return new \Drupal\Core\Url('<none>');
  }

  /**
   *
   */
  public function toLink($text = NULL, $rel = 'canonical', array $options = []) {
    return new \Drupal\Core\Link((string) $text, new \Drupal\Core\Url('<none>'));
  }

  /**
   *
   */
  public function toArray() {
    return [];
  }

  /**
   *
   */
  public function getIterator() {}

  /**
   *
   */
  public function offsetExists($offset) {}

  /**
   *
   */
  public function offsetGet($offset) {}

  /**
   *
   */
  public function offsetSet($offset, $value) {}

  /**
   *
   */
  public function offsetUnset($offset) {}

  /**
   *
   */
  public function __sleep() {
    return [];
  }

  /**
   *
   */
  public function __wakeup() {}

  /**
   *
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return FALSE;
  }

  /**
   *
   */
  public function enforceIsNew($value = TRUE) {
    return $this;
  }

  /**
   *
   */
  public function label() {
  }

  /**
   *
   */
  public function hasLinkTemplate($key) {
    return FALSE;
  }

  /**
   *
   */
  public function uriRelationships() {
    return [];
  }

  /**
   *
   */
  public static function load($id) {
  }

  /**
   *
   */
  public static function loadMultiple(?array $ids = NULL) {
    return [];
  }

  /**
   *
   */
  public static function create(array $values = []) {
    return new static();
  }

  /**
   *
   */
  public function save() {
    return 0;
  }

  /**
   *
   */
  public function delete() {
  }

  /**
   *
   */
  public function preSave(EntityStorageInterface $storage) {
  }

  /**
   *
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
  }

  /**
   *
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
  }

  /**
   *
   */
  public function postCreate(EntityStorageInterface $storage) {
  }

  /**
   *
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
  }

  /**
   *
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
  }

  /**
   *
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
  }

  /**
   *
   */
  public function createDuplicate() {
    return new static();
  }

  /**
   *
   */
  public function referencedEntities() {
    return [];
  }

  /**
   *
   */
  public function getCacheTagsToInvalidate() {
    return [];
  }

  /**
   *
   */
  public function setOriginalId($id) {
    return $this;
  }

  /**
   *
   */
  public function getConfigDependencyKey() {
    return '';
  }

  /**
   *
   */
  public function getConfigDependencyName() {
    return '';
  }

  /**
   *
   */
  public function getConfigTarget() {
    return '';
  }

  /**
   *
   */
  public function addCacheContexts(array $cache_contexts) {
    return $this;
  }

  /**
   *
   */
  public function addCacheTags(array $cache_tags) {
    return $this;
  }

  /**
   *
   */
  public function mergeCacheMaxAge($max_age) {
    return $this;
  }

  /**
   *
   */
  public function addCacheableDependency($other_object) {
    return $this;
  }

}
