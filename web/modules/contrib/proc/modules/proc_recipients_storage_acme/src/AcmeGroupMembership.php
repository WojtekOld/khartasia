<?php

namespace Drupal\proc_recipients_storage_acme;

/**
 * Hardcoded, fictional "eligibility group" membership.
 *
 * This class is the single source of truth for the Acme demonstration. In a
 * real application this data would come from an internal service, a directory,
 * a group module, or a remote endpoint — never hardcoded. Here it is a static
 * map of "user ID => timestamp when the user joined the group".
 *
 * The reasoning demonstrated by this module:
 * - Membership in this group is what makes a user an eligible recipient of any
 *   content encrypted through a proc field bound to the Acme recipient storage
 *   plugin.
 * - A user can only decrypt content that was encrypted for them, and content
 *   is only ever encrypted for the users who were already in the group at the
 *   moment of encryption. Therefore, when answering "who are the recipients of
 *   this cipher?", we must exclude every member who joined the group AFTER the
 *   cipher's cryptographic timestamp. Those later members are cryptographically
 *   impossible recipients for that particular cipher.
 *
 * @see \Drupal\proc_recipients_storage_acme\Controller\AcmeRecipientsController
 * @see \Drupal\proc_recipients_storage_acme\Plugin\RecipientStorage\AcmeRecipientStorage
 */
final class AcmeGroupMembership {

  /**
   * The fictional group membership: user ID => join timestamp (UTC seconds).
   *
   * The chosen timestamps are deliberately spread over several years so the
   * "joined after encryption" filtering is easy to observe in a demo:
   * - User 1 joined on Thu Jan 01 2015 00:00:00 GMT+0000.
   * - User 2 joined on Sat Mar 03 2018 09:46:40 GMT+0000.
   * - User 3 joined on Mon May 03 2021 19:33:20 GMT+0000.
   */
  private const MEMBERSHIP = [
    1 => 1420070400,
    2 => 1520070400,
    3 => 1690282510,
  ];

  /**
   * Returns the full group membership as a user ID => join timestamp map.
   *
   * @return array<int, int>
   *   Membership keyed by user ID, each value being the join timestamp.
   */
  public static function all(): array {
    return self::MEMBERSHIP;
  }

  /**
   * Returns the user IDs of every current group member.
   *
   * This is the raw, unfiltered eligibility list served by the fetcher
   * endpoint (see AcmeRecipientsController). It is the "target" set used by the
   * proc field widget to fill the wished recipients; it is NOT scoped to any
   * particular cipher's encryption time.
   *
   * @return int[]
   *   The user IDs of all group members.
   */
  public static function userIds(): array {
    return array_map('intval', array_keys(self::MEMBERSHIP));
  }

  /**
   * Returns the user IDs of members who joined at or before a given timestamp.
   *
   * Members whose join timestamp is strictly greater than $timestamp are
   * excluded, because they were not yet eligible when the cipher was encrypted
   * and could not possibly be recipients of it.
   *
   * When $timestamp is NULL (the cipher has no known cryptographic timestamp)
   * no time-based filtering can be applied and the full membership is returned.
   *
   * @param int|null $timestamp
   *   The cipher's cryptographic (encryption) timestamp, or NULL.
   *
   * @return int[]
   *   The user IDs eligible at the given timestamp.
   */
  public static function userIdsJoinedBy(?int $timestamp): array {
    if ($timestamp === NULL) {
      return self::userIds();
    }
    $eligible = [];
    foreach (self::MEMBERSHIP as $uid => $joined_at) {
      if ($joined_at <= $timestamp) {
        $eligible[] = (int) $uid;
      }
    }
    return $eligible;
  }

}
