/**
 * @file
 * Web Worker for background re-encryption of pending update jobs.
 *
 * This worker is spawned after a successful decryption. It reuses the
 * passphrase to decrypt pending update job ciphers and re-encrypts them
 * for the wished recipients, posting results to the edit-cipher API.
 *
 * Messages received:
 *   {
 *     type: 'start',
 *     openpgpPath: string,      // Absolute URL to openpgp.min.js
 *     basePath: string,         // Drupal base URL (e.g., '/web/')
 *     origin: string,           // window.location.origin
 *     armoredPrivateKey: string, // User's armored private key
 *     passphrase: string,       // Full passphrase (server prefix + user input)
 *     uid: string|number,       // Current user ID
 *     jobsLimit: number,        // Max jobs to process
 *     csrfToken: string,        // CSRF token for API requests
 *   }
 *
 * Messages sent:
 *   { type: 'progress', jobId, status: 'success'|'error', message }
 *   { type: 'complete', processed, succeeded, failed }
 *   { type: 'error', message }
 */

/* global importScripts */

/**
 * Fetch wrapper with CSRF token.
 */
function apiFetch(url, csrfToken, options = {}) {
  const headers = new Headers(options.headers || {});
  headers.set('X-CSRF-Token', csrfToken);
  headers.set('Accept', 'application/json');
  if (options.body && typeof options.body === 'string') {
    headers.set('Content-Type', 'application/json');
  }
  return fetch(url, Object.assign({}, options, {
    headers,
    credentials: 'same-origin',
  }));
}

/**
 * Process a single update job: decrypt -> re-encrypt -> submit.
 */
async function processJob(jobId, decryptedPrivateKey, config) {
  const { origin, basePath, csrfToken } = config;
  const apiBase = origin + basePath;

  // Fetch the cipher text and metadata.
  const cipherUrl = `${apiBase}api/proc/getcipher/${jobId}?cache_bust=${Date.now()}`;
  const cipherResponse = await apiFetch(cipherUrl, csrfToken, { method: 'GET' });
  if (!cipherResponse.ok) {
    throw new Error(`Failed to fetch cipher for job ${jobId}: HTTP ${cipherResponse.status}`);
  }
  const cipherData = await cipherResponse.json();
  const cipherItem = cipherData.pubkey[0];

  if (!cipherItem || !cipherItem.armored) {
    throw new Error(`No cipher data found for job ${jobId}`);
  }

  const wishedRecipients = cipherItem.wished_recipients || [];
  if (wishedRecipients.length === 0) {
    throw new Error(`No wished recipients for job ${jobId}, skipping`);
  }

  // Decrypt the cipher text.
  const message = await self.openpgp.readMessage({ armoredMessage: cipherItem.armored });
  const decrypted = await self.openpgp.decrypt({
    decryptionKeys: decryptedPrivateKey,
    message,
    format: 'binary',
  });

  // Fetch public keys for wished recipients.
  const recipientUids = wishedRecipients.map(r => r.target_id);
  const pubkeyUrl = `${apiBase}api/proc/getpubkey/${recipientUids.join(',')}/user_id?cache_bust=${Date.now()}`;
  const pubkeyResponse = await apiFetch(pubkeyUrl, csrfToken, { method: 'GET' });
  if (!pubkeyResponse.ok) {
    throw new Error(`Failed to fetch public keys for job ${jobId}: HTTP ${pubkeyResponse.status}`);
  }
  const pubkeyData = await pubkeyResponse.json();
  const recipientKeys = await Promise.all(
    pubkeyData.pubkey.map(pk => self.openpgp.readKey({ armoredKey: pk.key }))
  );

  if (recipientKeys.length === 0) {
    throw new Error(`No valid public keys found for wished recipients of job ${jobId}`);
  }

  // Re-encrypt for the wished recipients.
  const startSeconds = Date.now() / 1000;
  const newMessage = await self.openpgp.createMessage({ binary: decrypted.data });
  const encrypted = await self.openpgp.encrypt({
    encryptionKeys: recipientKeys,
    message: newMessage,
    format: 'armored',
    config: {
      preferredCompressionAlgorithm: self.openpgp.enums.compression.zip,
    },
  });
  const endSeconds = Date.now() / 1000;

  // Build the recipients map for the API (same format as batch update).
  const recipientsMap = {};
  recipientsMap[jobId] = recipientUids;

  // POST the re-encrypted cipher text.
  const editUrl = `${apiBase}api/proc/edit-cipher?cache_bust=${Date.now()}_${Math.floor(Math.random() * 1000000)}`;
  const postResponse = await apiFetch(editUrl, csrfToken, {
    method: 'POST',
    body: JSON.stringify({
      entity_id: jobId,
      recipients: JSON.stringify(recipientsMap),
      cipher_text: encrypted,
      browser_fingerprint: 'WebWorker background re-encryption',
      generation_timestamp: startSeconds,
      generation_timespan: endSeconds - startSeconds,
      signed: false,
    }),
  });

  if (!postResponse.ok) {
    throw new Error(`Failed to submit re-encrypted cipher for job ${jobId}: HTTP ${postResponse.status}`);
  }

  const result = await postResponse.json();
  return result;
}

/**
 * Main worker entry point.
 */
self.onmessage = async function (event) {
  const data = event.data;

  if (data.type !== 'start') {
    return;
  }

  try {
    // Import OpenPGP.js library.
    importScripts(data.openpgpPath);

    const {
      armoredPrivateKey,
      passphrase,
      uid,
      jobsLimit,
      csrfToken,
      origin,
      basePath,
    } = data;

    const config = { origin, basePath, csrfToken };

    // Unlock the private key.
    const privateKey = await self.openpgp.readPrivateKey({ armoredKey: armoredPrivateKey });
    const decryptedPrivateKey = await self.openpgp.decryptKey({ privateKey, passphrase });

    // Fetch update jobs list.
    const apiBase = origin + basePath;
    const jobsUrl = `${apiBase}api/proc/getpubkey/${uid}/user_id?cache_bust=${Date.now()}`;
    const jobsResponse = await apiFetch(jobsUrl, csrfToken, { method: 'GET' });
    if (!jobsResponse.ok) {
      throw new Error(`Failed to fetch update jobs: HTTP ${jobsResponse.status}`);
    }
    const jobsData = await jobsResponse.json();
    let updateJobs = jobsData.pubkey[0]?.update_jobs || [];

    // Normalize to array.
    if (updateJobs && typeof updateJobs === 'object' && !Array.isArray(updateJobs)) {
      updateJobs = Object.values(updateJobs).filter(job => job != null);
    }

    if (!Array.isArray(updateJobs) || updateJobs.length === 0) {
      self.postMessage({ type: 'complete', processed: 0, succeeded: 0, failed: 0 });
      return;
    }

    // Limit the number of jobs to process.
    const jobsToProcess = updateJobs.slice(0, jobsLimit);

    let succeeded = 0;
    let failed = 0;

    // Process each job sequentially.
    for (const jobId of jobsToProcess) {
      try {
        await processJob(jobId, decryptedPrivateKey, config);
        succeeded++;
        self.postMessage({
          type: 'progress',
          jobId,
          status: 'success',
          message: `Re-encrypted proc ${jobId} successfully`,
        });
      } catch (err) {
        failed++;
        self.postMessage({
          type: 'progress',
          jobId,
          status: 'error',
          message: `Failed to re-encrypt proc ${jobId}: ${err.message}`,
        });
      }
    }

    self.postMessage({
      type: 'complete',
      processed: succeeded + failed,
      succeeded,
      failed,
    });

  } catch (err) {
    self.postMessage({
      type: 'error',
      message: `Background re-encryption worker error: ${err.message}`,
    });
  }
};
