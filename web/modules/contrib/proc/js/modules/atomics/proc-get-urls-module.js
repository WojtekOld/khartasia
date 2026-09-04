/**
 * Builds API URLs for all cipher entities currently selected for decryption.
 *
 * Each URL includes the entity ID and its `changed` value so downstream cache
 * logic can vary requests when the cipher has been updated.
 *
 * @param {Object} drupalSettings
 *   Drupal settings object containing proc IDs and timestamps.
 *
 * @returns {Promise<string[]>}
 *   Array of fully-qualified getcipher endpoint URLs.
 */
export async function getProcUrls(drupalSettings) {
  return drupalSettings.proc.proc_ids.map(
    (cipherId, cipherIdIndex) =>
      `${window.location.origin +
      drupalSettings.path.baseUrl}api/proc/getcipher/${cipherId}/?cipherchanged=${
        drupalSettings.proc.procs_changed[cipherIdIndex]
      }`,
  );
}
