/**
 * @file
 * Process cache.
 */

// Number of oldest entries to drop when the cache is full (quota error).
const CACHE_EVICTION_BATCH = 50;

/**
 * Resolves the configured maximum number of cipher cache entries.
 *
 * @returns {number}
 *   The cap, or 0 when disabled/unavailable.
 */
function getMaxEntries() {
  try {
    return parseInt(drupalSettings.proc.proc_cipher_cache_max_entries, 10) || 0;
  } catch (e) {
    return 0;
  }
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
  const max = getMaxEntries();
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
 * Stores a response while keeping the cache bounded.
 *
 * Handles quota exhaustion by evicting the oldest entries and retrying, then
 * enforces the soft cap on the number of cached entries.
 *
 * @param {Cache} cache
 *   The opened cipher cache.
 * @param {string} url
 *   The request URL key.
 * @param {Response} response
 *   The response to store.
 *
 * @returns {Promise<void>}
 */
async function putBounded(cache, url, response) {
  try {
    await cache.put(url, response);
  } catch (err) {
    // Most likely a QuotaExceededError. Evict the oldest entries and retry
    // once; if it still fails, skip caching.
    await evictOldest(cache, CACHE_EVICTION_BATCH);
    try {
      await cache.put(url, response);
    } catch (err2) {
      console.warn('Could not cache cipher (storage full):', err2.message);
      return;
    }
  }
  await enforceMaxEntries(cache);
}

/**
 * Retrieves cipher payloads using the browser Cache API when available.
 *
 * Checks whether the requested cipher response is cached, fetches and stores it
 * on cache miss, and returns the cached/fetched Response object. Requests
 * carrying a cache_bust parameter (always-fresh, e.g. re-encryption) are never
 * cached, and the cache is kept bounded to avoid exhausting the browser storage
 * quota.
 *
 * @param {string[]} procURLs
 *   Fully-qualified getcipher URLs indexed by cipher position.
 * @param {number} cipherIndex
 *   Index of the cipher URL to resolve.
 *
 * @returns {Promise<Response|null>}
 *   Cached or freshly fetched response, or null when Cache API is unavailable.
 */
export async function processCache(procURLs, cipherIndex) {
  const url = procURLs[cipherIndex];

  /**
   * Extracts the cipher ID segment from a getcipher URL.
   *
   * @param {string} candidate
   *   Candidate URL that may contain `/proc/getcipher/{id}/`.
   *
   * @returns {string|null}
   *   Extracted cipher ID string, or null when no match is found.
   */
  function extractCipherId(candidate) {
    const match = candidate && candidate.match(/proc\/getcipher\/(\d+)\//);
    return match ? match[1] : null;
  }

  // Always-fresh requests (cache_bust) are unique and never reused; skip the
  // cache entirely to avoid unbounded growth.
  if (url && url.indexOf('cache_bust') !== -1) {
    return fetch(url);
  }

  if (!('caches' in self)) {
    return null;
  }

  const cache = await caches.open('proc');
  const cached = await cache.match(url);
  const id = extractCipherId(url);

  if (cached) {
    console.info(id ? `Reusing cipher from cache (id: ${id})` : 'Reusing cipher from cache');
    return cached;
  }

  console.info(id ? `Adding cipher to cache (id: ${id})` : 'Adding cipher to cache');
  const cipherResponse = await fetch(url);
  const forCache = cipherResponse.clone();
  if (cipherResponse.status === 200) {
    await putBounded(cache, url, forCache);
  }
  return cipherResponse;
}
