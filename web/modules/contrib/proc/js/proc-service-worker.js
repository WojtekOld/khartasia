/**
 * @file
 * Protected Content Service Worker.
 *
 * Responsibilities:
 * 1. Cache-first delivery of cipher texts (intercepts /api/proc/getcipher/*).
 * 2. Autonomous background re-encryption of pending update jobs.
 *
 * SECURITY / LIFECYCLE NOTES:
 * - The passphrase and the unlocked private key are held ONLY in the worker's
 *   in-memory variables. They are NEVER written to Cache, IndexedDB, or any
 *   persistent storage. When the browser terminates this idle worker, that
 *   material is lost and must be re-fed by an open project tab.
 * - This worker cannot read sessionStorage. The passphrase is delivered by a
 *   controlled page via postMessage (see proc-sw-register.js).
 *
 * Messages received (via postMessage from a controlled client):
 *   {
 *     type: 'proc-reencrypt-start',
 *     openpgpPath: string,       // Absolute URL to openpgp.min.js
 *     basePath: string,          // Drupal base URL (e.g. '/')
 *     origin: string,            // window.location.origin
 *     armoredPrivateKey: string, // User's armored (locked) private key
 *     passphrase: string,        // Full passphrase (server prefix + user input)
 *     uid: string|number,        // Current user ID
 *     jobsLimit: number,         // Max jobs to process this burst
 *     csrfToken: string,         // CSRF token for API requests
 *   }
 *
 * Messages broadcast back to clients:
 *   { type: 'proc-reencrypt-progress', jobId, status: 'success'|'error', message }
 *   { type: 'proc-reencrypt-complete', processed, succeeded, failed }
 *   { type: 'proc-reencrypt-error', message }
 */

/* global importScripts, clients */

// Name of the Cache API bucket. Shared with the legacy page-side processCache
// module for consistency.
const PROC_CIPHER_CACHE = 'proc';

// Number of oldest entries to drop when the cache is full (quota error).
const CACHE_EVICTION_BATCH = 50;

// Load OpenPGP.js during the worker's INITIAL evaluation. importScripts() is
// only reliably allowed at the top level (or in the install handler); calling
// it later from a message/fetch handler throws "A network error occurred".
// The absolute URL is injected by the serving controller as
// self.PROC_OPENPGP_URL (the worker is served from the base-path root and
// cannot otherwise derive the module directory).
if (self.PROC_OPENPGP_URL) {
  importScripts(self.PROC_OPENPGP_URL);
}

// Guards against overlapping re-encryption bursts within the same worker
// instance.
let processing = false;

/**
 * Install: activate immediately without waiting for old workers to release.
 */
self.addEventListener('install', () => {
  self.skipWaiting();
});

/**
 * Activate: take control of already-open clients so the handoff works without
 * requiring a page reload.
 */
self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

/**
 * Fetch: cache-first delivery for cipher-text API responses.
 *
 * Only GET requests to /api/proc/getcipher/* are intercepted. The full URL
 * (including the cipherchanged query parameter) is the cache key, so an updated
 * cipher naturally misses the cache. Requests carrying a cache_bust parameter
 * (used by re-encryption) are always-miss by construction, which is the desired
 * behaviour: re-encryption must never operate on stale cipher text.
 */
self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (!url.pathname.includes('/api/proc/getcipher/')) {
    return;
  }

  event.respondWith(cacheFirstCipher(request));
});

/**
 * Cache-first resolver for a cipher request.
 *
 * @param {Request} request
 *   The intercepted cipher request.
 *
 * @returns {Promise<Response>}
 *   The cached or freshly fetched response.
 */
async function cacheFirstCipher(request) {
  const url = new URL(request.url);

  // Requests carrying a cache_bust parameter (re-encryption, always-fresh) are
  // unique per call and would never be reused. Do not cache them at all — they
  // are the primary source of unbounded cache growth during bulk processing.
  if (url.searchParams.has('cache_bust')) {
    return fetch(request);
  }

  const cache = await caches.open(PROC_CIPHER_CACHE);
  const cached = await cache.match(request);
  if (cached) {
    return cached;
  }
  const response = await fetch(request);
  // Only cache successful, complete responses.
  if (response && response.status === 200) {
    await putBounded(cache, request, response.clone());
  }
  return response;
}

/**
 * Stores a response while keeping the cache bounded.
 *
 * Handles quota exhaustion by evicting the oldest entries and retrying, and
 * enforces a soft cap on the number of cached entries (FIFO).
 *
 * @param {Cache} cache
 *   The opened cipher cache.
 * @param {Request} request
 *   The request key.
 * @param {Response} response
 *   The response to store.
 *
 * @returns {Promise<void>}
 */
async function putBounded(cache, request, response) {
  try {
    await cache.put(request, response);
  } catch (err) {
    // Most likely a QuotaExceededError. Evict a batch of oldest entries and
    // retry once; if it still fails, skip caching (the caller already has the
    // response).
    await evictOldest(cache, CACHE_EVICTION_BATCH);
    try {
      await cache.put(request, response);
    } catch (err2) {
      console.warn('[Proc SW] Could not cache cipher (storage full):', err2.message);
      return;
    }
  }
  await enforceMaxEntries(cache);
}

/**
 * Evicts the oldest entries from the cache (FIFO; cache.keys() is insertion
 * ordered).
 *
 * @param {Cache} cache
 *   The opened cipher cache.
 * @param {number} count
 *   Number of entries to remove.
 *
 * @returns {Promise<void>}
 */
async function evictOldest(cache, count) {
  const keys = await cache.keys();
  const limit = Math.min(count, keys.length);
  for (let i = 0; i < limit; i++) {
    await cache.delete(keys[i]);
  }
}

/**
 * Enforces the configured maximum number of cached entries (FIFO eviction).
 *
 * @param {Cache} cache
 *   The opened cipher cache.
 *
 * @returns {Promise<void>}
 */
async function enforceMaxEntries(cache) {
  const max = parseInt(self.PROC_CIPHER_CACHE_MAX, 10) || 0;
  if (max <= 0) {
    return;
  }
  const keys = await cache.keys();
  if (keys.length > max) {
    const excess = keys.length - max;
    for (let i = 0; i < excess; i++) {
      await cache.delete(keys[i]);
    }
  }
}

/**
 * Message: entry point for the autonomous re-encryption handoff.
 */
self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type !== 'proc-reencrypt-start') {
    return;
  }
  // Keep the worker alive until the burst resolves.
  event.waitUntil(runReencryptionBurst(data));
});

/**
 * Broadcast a message to all controlled clients.
 *
 * @param {Object} message
 *   The message payload.
 *
 * @returns {Promise<void>}
 */
async function broadcast(message) {
  const all = await self.clients.matchAll({ includeUncontrolled: true });
  all.forEach((client) => client.postMessage(message));
}

/**
 * Fetch wrapper that injects the CSRF token and same-origin credentials.
 *
 * @param {string} url
 *   Request URL.
 * @param {string} csrfToken
 *   CSRF token value.
 * @param {RequestInit} [options]
 *   Additional fetch options.
 *
 * @returns {Promise<Response>}
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
 * Run one bounded burst of re-encryption jobs.
 *
 * @param {Object} data
 *   The start-message payload.
 *
 * @returns {Promise<void>}
 */
async function runReencryptionBurst(data) {
  if (processing) {
    // A burst is already running in this worker instance.
    return;
  }
  processing = true;

  try {
    if (!self.openpgp) {
      throw new Error('OpenPGP.js is not available in the Service Worker.');
    }

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
    const apiBase = origin + basePath;

    // Unlock the private key — kept in memory only.
    const privateKey = await self.openpgp.readPrivateKey({ armoredKey: armoredPrivateKey });
    const decryptedPrivateKey = await self.openpgp.decryptKey({ privateKey, passphrase });

    // Fetch the current list of pending update jobs.
    const jobsUrl = `${apiBase}api/proc/getpubkey/${uid}/user_id?cache_bust=${Date.now()}`;
    const jobsResponse = await apiFetch(jobsUrl, csrfToken, { method: 'GET' });
    if (!jobsResponse.ok) {
      throw new Error(`Failed to fetch update jobs: HTTP ${jobsResponse.status}`);
    }
    const jobsData = await jobsResponse.json();
    let updateJobs = jobsData.pubkey[0]?.update_jobs || [];

    if (updateJobs && typeof updateJobs === 'object' && !Array.isArray(updateJobs)) {
      updateJobs = Object.values(updateJobs).filter((job) => job != null);
    }

    if (!Array.isArray(updateJobs) || updateJobs.length === 0) {
      await broadcast({ type: 'proc-reencrypt-complete', processed: 0, succeeded: 0, failed: 0 });
      return;
    }

    const jobsToProcess = updateJobs.slice(0, jobsLimit);
    let succeeded = 0;
    let failed = 0;

    for (const jobId of jobsToProcess) {
      try {
        await processJob(jobId, decryptedPrivateKey, config);
        succeeded++;
        await broadcast({
          type: 'proc-reencrypt-progress',
          jobId,
          status: 'success',
          message: `Re-encrypted proc ${jobId} successfully`,
        });
      } catch (err) {
        failed++;
        await broadcast({
          type: 'proc-reencrypt-progress',
          jobId,
          status: 'error',
          message: `Failed to re-encrypt proc ${jobId}: ${err.message}`,
        });
      }
    }

    await broadcast({
      type: 'proc-reencrypt-complete',
      processed: succeeded + failed,
      succeeded,
      failed,
    });
  } catch (err) {
    await broadcast({
      type: 'proc-reencrypt-error',
      message: `Service Worker re-encryption error: ${err.message}`,
    });
  } finally {
    processing = false;
  }
}

/**
 * Process a single update job: fetch cipher -> decrypt -> re-encrypt -> submit.
 *
 * @param {string|number} jobId
 *   The proc cipher entity ID to re-encrypt.
 * @param {Object} decryptedPrivateKey
 *   The unlocked private key (in memory).
 * @param {Object} config
 *   { origin, basePath, csrfToken }.
 *
 * @returns {Promise<Object>}
 *   The edit-cipher API result.
 */
async function processJob(jobId, decryptedPrivateKey, config) {
  const { origin, basePath, csrfToken } = config;
  const apiBase = origin + basePath;

  // Fetch cipher (cache_bust => always fresh, bypassing cache-first).
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

  // Decrypt.
  const message = await self.openpgp.readMessage({ armoredMessage: cipherItem.armored });
  const decrypted = await self.openpgp.decrypt({
    decryptionKeys: decryptedPrivateKey,
    message,
    format: 'binary',
  });

  // Fetch recipient public keys.
  const recipientUids = wishedRecipients.map((r) => r.target_id);
  const pubkeyUrl = `${apiBase}api/proc/getpubkey/${recipientUids.join(',')}/user_id?cache_bust=${Date.now()}`;
  const pubkeyResponse = await apiFetch(pubkeyUrl, csrfToken, { method: 'GET' });
  if (!pubkeyResponse.ok) {
    throw new Error(`Failed to fetch public keys for job ${jobId}: HTTP ${pubkeyResponse.status}`);
  }
  const pubkeyData = await pubkeyResponse.json();
  const recipientKeys = await Promise.all(
    pubkeyData.pubkey.map((pk) => self.openpgp.readKey({ armoredKey: pk.key }))
  );

  if (recipientKeys.length === 0) {
    throw new Error(`No valid public keys found for wished recipients of job ${jobId}`);
  }

  // Re-encrypt.
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

  // Submit.
  const recipientsMap = {};
  recipientsMap[jobId] = recipientUids;
  const editUrl = `${apiBase}api/proc/edit-cipher?cache_bust=${Date.now()}_${Math.floor(Math.random() * 1000000)}`;
  const postResponse = await apiFetch(editUrl, csrfToken, {
    method: 'POST',
    body: JSON.stringify({
      entity_id: jobId,
      recipients: JSON.stringify(recipientsMap),
      cipher_text: encrypted,
      browser_fingerprint: 'ServiceWorker background re-encryption',
      generation_timestamp: startSeconds,
      generation_timespan: endSeconds - startSeconds,
      signed: false,
    }),
  });

  if (!postResponse.ok) {
    throw new Error(`Failed to submit re-encrypted cipher for job ${jobId}: HTTP ${postResponse.status}`);
  }

  return postResponse.json();
}
