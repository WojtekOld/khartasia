<?php

namespace Drupal\proc_recipients_storage_acme\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\proc_recipients_storage_acme\AcmeGroupMembership;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Serves the fictional Acme "eligible recipients" list.
 *
 * A site builder points a proc field's "Endpoint for fetching user IDs of
 * recipients" setting at this route
 * ("/proc_recipients_storage_acme/recipients"). The proc field widget calls it
 * client-side to learn the current, up-to-date set of eligible recipients and
 * writes it as the cipher's wished (target) recipients.
 *
 * The endpoint returns a flat JSON list of user IDs (e.g. [1, 2, 3]), which is
 * one of the shapes accepted by the widget's recipient fetcher. This is the
 * unfiltered eligibility list: it is NOT scoped to any cipher's encryption
 * time. Time-based scoping ("who could actually be a recipient of THIS cipher")
 * is applied later by the Acme recipient storage plugin when resolving the
 * recipients of a specific cipher.
 *
 * @see \Drupal\proc_recipients_storage_acme\AcmeGroupMembership
 * @see \Drupal\proc_recipients_storage_acme\Plugin\RecipientStorage\AcmeRecipientStorage
 */
class AcmeRecipientsController extends ControllerBase {

  /**
   * Returns the hardcoded list of eligible recipient user IDs as JSON.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing a flat list of user IDs, e.g. [1, 2, 3].
   */
  public function recipients(): JsonResponse {
    return new JsonResponse(AcmeGroupMembership::userIds());
  }

}
