/**
 * @file
 * Evicts an expired session password cache entry.
 */

import { removeCachedPassword } from './atomics/proc-remove-cached-pass-module.js';

/**
 * Removes the session-cached password when its TTL has expired.
 *
 * Reads the expiration timestamp stored under the current user's cache key.
 * When the key is absent the function is a no-op. When the key exists but the
 * stored deadline has passed the entire password cache is cleared via
 * removeCachedPassword so decryption is not attempted with a stale passphrase.
 *
 * @returns {boolean}
 *   True if the cache existed and was expired (and thus removed); false if the
 *   cache was absent or is still within its valid TTL window.
 */
export function evictExpiredPassCache() {
  const expiresAt = sessionStorage.getItem(
    `proc.password_cache_type.key_user_id.${drupalSettings.user.uid}`,
  );
  if (expiresAt === null) {
    return false;
  }
  const remainingTime = Number(expiresAt) - new Date().getTime();
  if (remainingTime <= 0) {
    removeCachedPassword();
    return true;
  }
  return false;
}

