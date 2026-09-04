/**
 * @file
 * Decrypts the cipher text.
 */

import { feedPassCache } from './proc-feed-pass-cache-module.js';
import { getPassSessionCachingSalt } from './atomics/proc-caching-salt-module.js';
import { removeCachedPassword } from './atomics/proc-remove-cached-pass-module.js';
import { processDecryption } from './proc-process-decryption-module.js';
import { getProcUrls } from './atomics/proc-get-urls-module.js';
import { triggerBackgroundReencryption } from './proc-background-reencrypt-module.js';

/**
 * Orchestrates decryption from the UI action link.
 *
 * This entry point toggles loader state, manages password caching policy,
 * prepares a temporary download anchor, resolves cipher URLs, and delegates the
 * actual per-item decryption to the processDecryption pipeline.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {*} opLink
 *   jQuery-wrapped action link that initiated decryption/update.
 *
 * @returns {Promise<void>}
 */
export async function decryptHandler($, opLink) {
  // Apply display block to the loader.
  const loaderElement = document.getElementById('proc-op-loader');
  if (loaderElement) {
    loaderElement.style.display = 'inline';
  }
  const cache_password = document.querySelector(
    '[name="cache_password"]',
  ) ? document.querySelector('[name="cache_password"]').value
    : 0;
  const secretPassString = document.querySelector('input[name="password"]').value;

  await feedPassCache(cache_password, drupalSettings.proc.proc_keyring_type, secretPassString, getPassSessionCachingSalt(), openpgp);
  if (cache_password === '0' || drupalSettings.proc.proc_keyring_type === 'keyring') {
    // Remove password from cache but allow it to be used when already typed in.
    removeCachedPassword();
  }
  if (!$('#proc-decrypting-info')[0]) {
    console.info(drupalSettings.proc.proc_labels.proc_introducing_decryption);
  }
  const temporaryDownloadLink = document.createElement('a');
  temporaryDownloadLink.style.display = 'none';
  document.body.appendChild(temporaryDownloadLink);

  const fullPassphrase = drupalSettings.proc.proc_pass.concat(secretPassString);

  try {
    await processDecryption(
      $,
      await getProcUrls(drupalSettings),
      openpgp,
      await openpgp.readPrivateKey({
        armoredKey: drupalSettings.proc.proc_privkey
      }),
      fullPassphrase,
      temporaryDownloadLink,
      opLink
    );

    // After successful decryption (not re-encryption), trigger background
    // re-encryption of pending update jobs via web worker.
    if (!opLink || (opLink[0] && opLink[0].id === 'decryption-link')) {
      triggerBackgroundReencryption(fullPassphrase);
    }
  } finally {
    if (loaderElement) {
      loaderElement.style.display = 'none';
    }
  }
}
