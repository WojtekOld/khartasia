/**
 * Builds a browser-fingerprint salt used for passphrase cache encryption.
 *
 * The salt is derived from enumerable navigator properties while skipping
 * deprecated fields that vary across browsers and can destabilize caching.
 *
 * @returns {string}
 *   Serialized navigator fingerprint used as OpenPGP symmetric password.
 */
export function getPassSessionCachingSalt() {
  const _navigator = {};
  const deprecatedItems = [
    'webkitTemporaryStorage',
    'webkitPersistentStorage'
  ];
  for (const i in navigator) {
    if (!(deprecatedItems.indexOf(i) > -1)) {
      _navigator[i] = navigator[i];
    }
  }
  return JSON.stringify(_navigator);
}
