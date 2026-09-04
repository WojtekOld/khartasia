/**
 * @file
 * Initialize password caching.
 */

/**
 * Initializes decryption UI state and default password-cache preference.
 *
 * Hides any running loader and sets the cache_password form input according to
 * module configuration so decryption forms start in a predictable state.
 *
 * @returns {void}
 */
export function initPasswordCaching() {
  console.info('Initializing password caching.');
  // Hide the spinner/loader.
  const loaderElement = document.getElementById('proc-op-loader');
  if (loaderElement) {
    loaderElement.style.display = 'none';
  }
  // Initialize password caching.
  if (document.querySelector('[name="cache_password"]')) {
    // Caching password is disabled by default.
    document.querySelector('[name="cache_password"]').value = 0;
    if (drupalSettings.proc.proc_cache_password_mode === '2') {
      // Caching password is enabled by default.
      document.querySelector('[name="cache_password"]').value = 1;
    }
  }
}
