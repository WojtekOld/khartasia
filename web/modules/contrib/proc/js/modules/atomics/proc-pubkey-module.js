/**
 * @file
 * Public key module.
 */

/**
 * Resolves recipient public keys from local cache and remote API fallback.
 *
 * The function loads each selected recipient pubkey from localStorage when
 * possible, removes stale key versions, fetches missing keys from the server,
 * and returns parsed OpenPGP key objects ready for encryption.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} drupalSettings
 *   Drupal settings object containing recipients and API path data.
 * @param {Object} openpgp
 *   OpenPGP.js library instance.
 * @param {string|number|null} procId
 *   Optional proc entity ID used only for contextual logging.
 *
 * @returns {Promise<Array>}
 *   Parsed OpenPGP public keys for all resolved recipients.
 */
export async function procReadKey($, drupalSettings, openpgp, procId) {
  const recipientsPubkeys = [];
  const remoteKey = [];
  let recipients_selection = drupalSettings.proc.proc_recipients_pubkeys_changed;
  if (drupalSettings.proc.proc_recipients_pubkeys_changed_host) {
    recipients_selection = drupalSettings.proc.proc_recipients_pubkeys_changed_host;
  }
  const recipientsUidsKeysChanged = JSON.parse(
    recipients_selection
  );

  let keysFound = 0;
  for (const userIdIterator in recipientsUidsKeysChanged) {
    const localKey = localStorage.getItem(
      `proc.key_user_id.${userIdIterator}.${recipientsUidsKeysChanged[userIdIterator]}`,
    );
    if (localKey) {
      keysFound++;
      recipientsPubkeys.push(localKey);
    } else {
      const storageKeys = Object.keys(localStorage);
      if (storageKeys.length > 0) {
        storageKeys.forEach(function(storageKey, storageKeyIndex) {
          if (
            storageKey.startsWith(
              `proc.key_user_id.${userIdIterator}`,
            )
          ) {
            localStorage.removeItem(storageKeys[storageKeyIndex]);
          }
        });
      }
      remoteKey.push(userIdIterator);
    }
  }
  console.info(`Public keys found in local storage: ${keysFound}`);
  if (remoteKey.length > 0) {
    const remoteKeyCsv = remoteKey.join(',');
    const pubKeyAjax = async remoteKeyCsv => {
      const response = await fetch(
        `${window.location.origin +
        drupalSettings.path.baseUrl}api/proc/getpubkey/${remoteKeyCsv}/user_id`,
      );
      const pubkeysJson = await response.json();
      if (pubkeysJson.pubkey.length > 0) {
        let addedKeys = 0;
        pubkeysJson.pubkey.forEach(function(pubkey, index) {
          recipientsPubkeys.push(pubkey.key);
          try {
            localStorage.setItem(
              `proc.key_user_id.${remoteKey[index]}.${pubkey.changed}`,
              pubkey.key,
            );
            addedKeys++;
          } catch (error) {
            console.warn(error);
          }
        });
        console.info(`Added ${addedKeys} public keys to local storage`);
      }
    };
    await pubKeyAjax(remoteKeyCsv);
  }
  if (procId) {
    console.info(`Reading ${recipientsPubkeys.length} public key(s) for encryption of proc ID ${procId}.`);
  }
  else {
    console.info(`Reading ${recipientsPubkeys.length} public key(s).`);
  }
  return await Promise.all(
    recipientsPubkeys.map(armoredKey =>
      openpgp.readKey({
        armoredKey,
      }),
    ),
  );
}
