/**
 * @file
 * OpenPGP.js readiness helper.
 */

/**
 * Waits until the global OpenPGP.js instance is available.
 *
 * @param {number} [timeoutMs=5000]
 *   How long to wait before giving up.
 *
 * @returns {Promise<Object|null>}
 *   The OpenPGP.js instance, or null if it never became available.
 */
export function openpgpReady(timeoutMs) {
  timeoutMs = timeoutMs || 5000;
  if (window.openpgp) {
    return Promise.resolve(window.openpgp);
  }
  const start = Date.now();
  return new Promise((resolve) => {
    const tick = () => {
      if (window.openpgp) {
        resolve(window.openpgp);
      }
      else if (Date.now() - start >= timeoutMs) {
        resolve(null);
      }
      else {
        setTimeout(tick, 50);
      }
    };
    tick();
  });
}
