/**
 * @file
 * Persists decrypted passphrases in session storage when caching is enabled.
 */

import { evictExpiredPassCache } from './proc-evict-expired-pass-cache-module.js';

/**
 * Stores a passphrase in encrypted session cache with an expiration policy.
 *
 * The passphrase is encrypted with a browser/session-derived salt and then
 * stored under the current user cache keys. Expired cache entries are removed
 * before writing a new value.
 *
 * @param {string} cache_password
 *   Cache mode flag from the decryption form (`'1'` enables caching).
 * @param {string} keyringCacheType
 *   Configured cache TTL mode (`keyring_1h`, `keyring_2h`, etc.).
 * @param {string} secretPassString
 *   Raw private-key password fragment to cache.
 * @param {string} passwordSessionCachingSalt
 *   Browser/session fingerprint string used as symmetric decrypt password.
 * @param {Object} openpgp
 *   OpenPGP.js library instance.
 *
 * @returns {Promise<void>}
 */
export async function feedPassCache(cache_password, keyringCacheType, secretPassString, passwordSessionCachingSalt, openpgp) {
  if (cache_password === '1' && keyringCacheType !== 'keyring') {
    // Cache password.
    await (async () => {
      const message = await openpgp.createMessage({
        text: secretPassString,
      });
      // Check if an expiration time is already set.
      if (
        sessionStorage.getItem(
          `proc.password_cache_type.key_user_id.${drupalSettings.user.uid}`,
        )
      ) {
        // A cache entry already exists: evict it if its TTL has expired.
        evictExpiredPassCache();
      } else {
        sessionStorage.setItem(
          `proc.password_cache.key_user_id.${drupalSettings.user.uid}`,
          await openpgp.encrypt({
            message,
            passwords: [passwordSessionCachingSalt],
          }),
        );
        // If expiration time is set to 1 hour, then count 1 hour from now.
        let expirationTime = new Date().getTime() + 3600000;
        // If expiration time is set to 2 hours, then count 2 hours from now.
        if (keyringCacheType === 'keyring_2h') {
          expirationTime += 3600000;
        }
        sessionStorage.setItem(
          `proc.password_cache_type.key_user_id.${drupalSettings.user.uid}`,
          expirationTime,
        );
      }
    })();
  }
}
