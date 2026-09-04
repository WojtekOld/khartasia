/**
 * @file
 * Provides CSRF token retrieval, caching, and fetch helpers for Proc APIs.
 */
const CSRF_TTL_MS = 10 * 60 * 1000; // 10 minutes

let _cachedToken = null;
let _cachedAt = 0;
let _inflightPromise = null;

function _tokenUrl() {
  return `${window.location.origin + drupalSettings.path.baseUrl}session/token`;
}

/**
 * Returns a CSRF token, reusing a short-lived in-memory cache when possible.
 *
 * The function deduplicates concurrent token requests, refreshes expired
 * values, and stores successful results with a TTL to reduce network traffic.
 *
 * @returns {Promise<string>}
 *   Valid CSRF token string.
 */
export async function getCsrfToken() {
  const now = Date.now();
  if (_cachedToken && (now - _cachedAt) < CSRF_TTL_MS) {
    return _cachedToken;
  }

  if (_inflightPromise) {
    return _inflightPromise;
  }

  _inflightPromise = fetch(_tokenUrl(), {
    method: 'GET',
    credentials: 'same-origin'
  })
    .then((res) => {
      if (!res.ok) {
        throw new Error('Failed to fetch CSRF token');
      }
      return res.text();
    })
    .then((token) => {
      _cachedToken = token;
      _cachedAt = Date.now();
      return token;
    })
    .finally(() => {
      _inflightPromise = null;
    });

  return _inflightPromise;
}

/**
 * Clears the in-memory CSRF cache and in-flight token request state.
 *
 * @returns {void}
 */
export function invalidateCsrfToken() {
  _cachedToken = null;
  _cachedAt = 0;
  _inflightPromise = null;
}

/**
 * Performs fetch with CSRF header injection and one automatic retry.
 *
 * The helper attaches a valid token and enforces same-origin credentials. On a
 * likely CSRF failure status (403/419), it invalidates the cache, obtains a
 * fresh token, and retries the request once.
 *
 * @param {RequestInfo | URL} input
 *   Fetch request target.
 * @param {RequestInit} [init]
 *   Fetch options merged with CSRF and credential defaults.
 *
 * @returns {Promise<Response>}
 *   Final fetch response (first attempt or retry response).
 */
export async function fetchWithCsrf(input, init = {}) {
  const tryRequest = async (token) => {
    const headers = new Headers(init.headers || {});
    headers.set('X-CSRF-Token', token);
    headers.set('Accept', headers.get('Accept') || 'application/json');
    const opts = Object.assign({}, init, { headers, credentials: 'same-origin' });
    return fetch(input, opts);
  };

  const token = await getCsrfToken();
  let res = await tryRequest(token);

  if (res.status === 403 || res.status === 419) {
    // Token likely invalid/expired - invalidate and retry once
    invalidateCsrfToken();
    const freshToken = await getCsrfToken();
    res = await tryRequest(freshToken);
  }

  return res;
}
