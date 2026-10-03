<?php

namespace Drupal\Tests\simplenews\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests that installing the module creates the issue body field.
 *
 * The body field is created in code after the optional configuration has been
 * imported, so this covers the wiring: installing simplenews results in a
 * simplenews_issue content type whose body field matches the site's body
 * storage.
 *
 * @group simplenews
 */
class SimplenewsBodyFieldInstallTest extends SimplenewsTestBase {

  /**
   * The body field exists after install and matches the storage.
   */
  public function testBodyFieldCreatedOnInstall() {
    $storage = FieldStorageConfig::loadByName('node', 'body');
    $this->assertNotNull($storage);

    $field = FieldConfig::loadByName('node', 'simplenews_issue', 'body');
    $this->assertNotNull($field, 'The body field was created at install.');
    $this->assertEquals($storage->getType(), $field->getType(), 'The body field type matches the storage.');

    // The form and view displays include the body.
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repository */
    $repository = \Drupal::service('entity_display.repository');
    $this->assertNotNull($repository->getFormDisplay('node', 'simplenews_issue')->getComponent('body'));
    foreach (['default', 'teaser', 'email_html', 'email_plain'] as $view_mode) {
      $this->assertNotNull($repository->getViewDisplay('node', 'simplenews_issue', $view_mode)->getComponent('body'), "Body is displayed in $view_mode.");
    }
  }

}
