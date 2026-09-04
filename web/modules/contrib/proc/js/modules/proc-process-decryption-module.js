/**
 * @file
 * Process decryption of cipher text.
 */

import { processCache } from './atomics/proc-process-cache-module.js';
import { decryptPrivateKey } from './atomics/proc-decrypt-privkey-module.js';
import { procDecrypt } from './atomics/proc-decrypt-module.js';
import { handleDecryptionResult } from './proc-handle-decryption-result-module.js';

/**
 * Decrypts all configured Proc ciphers and delegates post-processing.
 *
 * For each cipher ID this function retrieves the armored payload (using cache
 * when possible), unlocks the private key, decrypts the payload in the expected
 * format, and forwards the decrypted output to the result handler.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {string[]} procURLs
 *   Fetch/cache URLs indexed by cipher position.
 * @param {Object} openpgp
 *   OpenPGP.js library instance.
 * @param {*} privateKey
 *   Armored private key object parsed by OpenPGP.
 * @param {string} passphrase
 *   Full passphrase used to decrypt the private key.
 * @param {HTMLAnchorElement} temporaryDownloadLink
 *   Hidden anchor used when decrypted files must be downloaded.
 * @param {*} opLink
 *   jQuery-wrapped operation link driving the workflow.
 *
 * @returns {Promise<void>}
 */
export async function processDecryption($, procURLs, openpgp, privateKey, passphrase, temporaryDownloadLink, opLink) {
  await Promise.all(
    drupalSettings.proc.proc_ids.map(async (cipherId, cipherIndex) => {
      let cachedCiphers = await processCache(procURLs, cipherIndex);
      const cipherText = cachedCiphers ? (await cachedCiphers.json()).pubkey[0].armored
        : '';
      const decryptedPrivateKey = await decryptPrivateKey($, openpgp, privateKey, passphrase);

      if (!cipherText) {
        console.warn(`No cipher text found for cipher ID ${cipherId}`);
        return false;
      }
      const message = await openpgp.readMessage({
        armoredMessage: cipherText,
      });
      let procFormat = 'binary';
      if (drupalSettings.proc.proc_sources_input_modes[cipherIndex]) {
        procFormat = drupalSettings.proc.proc_sources_input_modes[cipherIndex];
      }
      if (decryptedPrivateKey) {
        console.info('Decrypted private key');
        const decryptedResult = await procDecrypt(
          openpgp,
          decryptedPrivateKey,
          message,
          procFormat,
          $
        );

        await handleDecryptionResult(
          $,
          decryptedResult,
          cipherId,
          temporaryDownloadLink,
          cipherIndex,
          opLink
        );
      }
      return false;
    }),
  );
}
