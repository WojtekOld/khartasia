/**
 * @file
 * Helper for entity reference proc field.
 */

import { fetchWithCsrf } from './modules/atomics/proc-csrf-token-module.js';
import { processCache } from './modules/atomics/proc-process-cache-module.js';
import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { decryptPrivateKey } from './modules/atomics/proc-decrypt-privkey-module.js';
import { handlePasswordDecryption } from './modules/proc-handle-password-decryption-module.js';
import { renderMedia } from './modules/atomics/proc-media-viewer-module.js';

// Keep this aligned with proc-field version number in proc.libraries.yml.
const PROC_FIELD_VERSION = '10.1.99';

(function($, Drupal, drupalSettings, once, openpgp) {
  const ProcBehavior = {
    attach(context, settings) {
      once('proc-decrypt', 'html', context).forEach(initProcBehavior);
    }
  };
  /**
   * Bootstraps Proc field behavior for the current page context.
   *
   * It discovers relevant field elements, applies initial UI wiring for
   * encryption/decryption toggles, and starts recipient-fetcher synchronization.
   *
   * @returns {void}
   */
  function initProcBehavior() {
    const { procFieldElements, encryptCheckboxes, procFields, procInlineDecryptionLinks } = selectProcElements();
    const procFetchers = document.querySelectorAll('[proc="true"][data-proc-fetcher]');

    setupProcElements(procFieldElements, encryptCheckboxes, procFields, procInlineDecryptionLinks);
    handleProcFetchers(procFetchers);
  }
  /**
   * Selects Proc-enabled form controls.
   *
   * @returns {{procFieldElements: NodeListOf<Element>, encryptCheckboxes: NodeListOf<Element>, procFields: string[], procInlineDecryptionLinks: NodeListOf<Element>}}
   *   Grouped DOM collections and normalized field names.
   */
  function selectProcElements() {
    const procFieldElements = document.querySelectorAll(
      '[proc="true"]:not(.form-autocomplete)'
    );
    const procFields = Array.from(procFieldElements, extractFieldName);
    const encryptCheckboxes = document.querySelectorAll('[id^="encrypt-checkbox-"]');
    const procInlineDecryptionLinks = document.querySelectorAll(
      '[data-drupal-proc-inline-decryption]'
    );

    return { procFieldElements, encryptCheckboxes, procFields, procInlineDecryptionLinks };
  }
  /**
   * Applies initial UI bindings for Proc field controls.
   *
   * @param {NodeListOf<Element>} procFieldElements
   *   Proc-enabled text/textarea field elements.
   * @param {NodeListOf<Element>} encryptCheckboxes
   *   Encryption toggles aligned with Proc field elements.
   * @param {string[]} procFields
   *   Canonical field names extracted from inputs.
   * @param {NodeListOf<Element>} procInlineDecryptionLinks
   *   Links that trigger inline decryption viewers.
   *
   * @returns {void}
   */
  function setupProcElements(procFieldElements, encryptCheckboxes, procFields, procInlineDecryptionLinks) {
    setTextSiblingsAttribute(procFields, procFieldElements, encryptCheckboxes);
    setInitialStateCheckboxes(procFieldElements, encryptCheckboxes);
    disableEncryptCheckboxIfEmpty(procFieldElements);
    switchSubmitOnInput(procFields, procFieldElements);
    switchSubmitOnCheckbox(encryptCheckboxes);
    setForInlineDecryption(procInlineDecryptionLinks);
  }
  /**
   * Processes dynamic recipient fetcher widgets on the page.
   *
   * @param {NodeListOf<Element>} procFetchers
   *   Elements carrying recipient fetcher metadata.
   *
   * @returns {void}
   */
  function handleProcFetchers(procFetchers) {
    const endpointCache = new Map();

    procFetchers.forEach(procFetcher => {
      const fetcherRecipients = procFetcher.getAttribute('data-proc-recipients') || '';
      const fetcherEndpoint = procFetcher.getAttribute('data-proc-fetcher');
      const fetcherProcId = procFetcher.getAttribute('data-proc-id') || '';

      if (!procFetcher.hasAttribute('data-proc-wished-recipients') && fetcherRecipients) {
        processFetcher(endpointCache, fetcherEndpoint, fetcherRecipients, fetcherProcId).then(r => {
          console.info(`[proc-field ${PROC_FIELD_VERSION}]`, 'Proc fetcher processed for proc ID', fetcherProcId);
        });
      }
    });
  }
  /**
   * Resolves recipient CSV for a fetcher and updates entity state if needed.
   *
   * @param {Map<string, Promise<string>>} endpointCache
   *   Shared promise cache keyed by endpoint URL.
   * @param {string} endpoint
   *   Remote endpoint used to resolve intended recipients.
   * @param {string} currentRecipients
   *   Current recipients CSV stored on the fetcher element.
   * @param {string} procId
   *   Proc entity ID being synchronized.
   *
   * @returns {Promise<void>}
   */
  async function processFetcher(endpointCache, endpoint, currentRecipients, procId) {
    try {
      const csv = await getCachedEndpointCSV(endpointCache, endpoint, procId);
      if (!csv) {
        return;
      }
      if (csv !== currentRecipients && csv !== '') {
        await updateWishedRecipients(procId, csv);
      }
    } catch (error) {
      console.error('Error processing fetcher:', error);
    }
  }
  /**
   * Returns a memoized promise for recipient CSV from a given endpoint.
   *
   * @param {Map<string, Promise<string>>} endpointCache
   *   Cache of in-flight/completed endpoint resolution promises.
   * @param {string} endpoint
   *   Endpoint URL used as cache key.
   * @param {string} procId
   *   Proc ID used for logging in downstream fetches.
   *
   * @returns {Promise<string>}
   *   Promise resolving to normalized recipient IDs as CSV.
   */
  function getCachedEndpointCSV(endpointCache, endpoint, procId) {
    if (!endpointCache.has(endpoint)) {
      endpointCache.set(endpoint, fetchEndpointRecipientsCSV(endpoint, procId));
    }
    return endpointCache.get(endpoint);
  }
  /**
   * Fetches recipient data from an endpoint and normalizes it to CSV IDs.
   *
   * Supports absolute and relative endpoints, removes invalid identifiers, and
   * deduplicates recipient IDs before returning the CSV payload.
   *
   * @param {string} endpoint
   *   Source endpoint returning recipient-like objects.
   * @param {string} procId
   *   Proc ID used for contextual logging.
   *
   * @returns {Promise<string>}
   *   Comma-separated list of valid recipient user IDs.
   */
  async function fetchEndpointRecipientsCSV(endpoint, procId) {
    try {
      console.info(`[proc-field ${PROC_FIELD_VERSION}] Fetching recipients of proc #${procId} from endpoint: ${endpoint}`);
      // If endpoint is not fully qualified, assume it is localhost and prepend
      // base URL.
      if (!endpoint.startsWith('http://') && !endpoint.startsWith('https://')) {
        endpoint = window.location.origin + drupalSettings.path.baseUrl + endpoint;
      }

      const response = await fetch(endpoint, { method: 'get' });
      const result = await response.json();
      const items = typeof result === 'object' ? Object.values(result) : result;

      const ids = items
        .map(item => item.id || item.uid)
        .filter(Boolean)
        // Convert to number and filter out non-integers
        .map(id => Number(id))
        // Filter out negatives and the anonymous user (ID 0)
        .filter(id => Number.isInteger(id) && id > 0);

      // Discard possibly duplicated IDs.
      const uniqueIds = Array.from(new Set(ids));
      return uniqueIds.join();
    } catch (error) {
      console.error('Error fetching endpoint:', endpoint, error);
      return '';
    }
  }
  /**
   * Persists updated wished recipients for a Proc entity through the API.
   *
   * @param {string} procId
   *   Proc entity ID to update.
   * @param {string} recipientsCSV
   *   Comma-separated recipient user IDs.
   *
   * @returns {Promise<void>}
   */
  async function updateWishedRecipients(procId, recipientsCSV) {
    console.info(`[proc-field ${PROC_FIELD_VERSION}] Updating wished recipients for proc ID ${procId}`);

    try {
      // Build URL with cache-busting query param.
      const url = new URL(window.location.origin + drupalSettings.path.baseUrl + 'proc/edit-entity');
      url.searchParams.set('cache_bust', `${Date.now()}_${Math.floor(Math.random() * 1000000)}`);

      const response = await fetchWithCsrf(url.toString(), {
        body: JSON.stringify({
          entity_id: procId,
          field_wished_recipients_set: recipientsCSV,
        }),
        method: 'POST',
      });

      const result = await response.json();

      if (result.status !== 'success') {
        new Error(result.message || 'Unknown error');
      }
      console.info(`[proc-field ${PROC_FIELD_VERSION}] Entity with ID ${procId} updated successfully`);
    } catch (error) {
      console.error('Error updating entity:', error);
    }
  }
  /**
   * Extracts the base field name from a form input element name attribute.
   *
   * @param {Element} fieldElement
   *   Proc-enabled form field element.
   *
   * @returns {string}
   *   Field machine name without nested key suffix.
   */
  function extractFieldName(fieldElement) {
    return fieldElement.attributes.name.value.split('[')[0];
  }
  /**
   * Initializes encrypt checkbox disabled state from field emptiness.
   *
   * @param {NodeListOf<Element>} procFieldElements
   *   Proc-enabled editable fields.
   * @param {NodeListOf<Element>} encryptCheckboxes
   *   Encryption toggle checkboxes tied to each field.
   *
   * @returns {void}
   */
  function setInitialStateCheckboxes(procFieldElements, encryptCheckboxes) {
    procFieldElements.forEach((fieldElement, index) => {
      encryptCheckboxes[index].disabled = fieldElement.value === '';
    });
  }
  /**
   * Binds input listeners that toggle encryption checkbox availability.
   *
   * @param {NodeListOf<Element>} procFieldElements
   *   Proc-enabled editable fields.
   *
   * @returns {void}
   */
  function disableEncryptCheckboxIfEmpty(procFieldElements) {
    procFieldElements.forEach((fieldElement, index) => {
      fieldElement.addEventListener('input', inputChangeCheckbox);
    });
  }
  /**
   * Enables/disables the paired encrypt checkbox as field content changes.
   *
   * @param {Event} inputEvent
   *   Input event fired by a Proc-enabled field.
   *
   * @returns {void}
   */
  function inputChangeCheckbox(inputEvent) {
    document.querySelector(`[id="encrypt-checkbox-${extractFieldName(inputEvent.target)}"]`).disabled = inputEvent.target.value === '';
  }
  /**
   * Wires submit-button toggling to text field input changes.
   *
   * @param {string[]} procFields
   *   Canonical Proc field names.
   * @param {NodeListOf<Element>} procFieldElements
   *   Proc-enabled editable fields.
   *
   * @returns {void}
   */
  function switchSubmitOnInput(procFields, procFieldElements) {
    // Process when there is a submit element specified in drupalSettings.
    if (drupalSettings.proc && drupalSettings.proc.submit_element_id) {
      procFields.forEach((fieldName, index) => {
        procFieldElements[index].setAttribute(
          'data-proc-text-siblings',
          procFields.join(),
        );
        procFieldElements[index].addEventListener('input', inputChangeSubmit);
      });
    }
  }
  /**
   * Stores sibling field metadata used by submit-button state checks.
   *
   * @param {string[]} procFields
   *   Canonical Proc field names.
   * @param {NodeListOf<Element>} procFieldElements
   *   Proc-enabled editable fields.
   * @param {NodeListOf<Element>} encryptCheckboxes
   *   Encryption checkboxes associated with Proc fields.
   *
   * @returns {void}
   */
  function setTextSiblingsAttribute(
    procFields,
    procFieldElements,
    encryptCheckboxes,
  ) {
    const procFieldsString = procFields.join();
    procFields.forEach((fieldName, index) => {
      procFieldElements[index].setAttribute(
        'data-proc-text-siblings',
        procFieldsString,
      );
      encryptCheckboxes[index].setAttribute(
        'data-proc-text-siblings',
        procFieldsString,
      );
    });
  }
  /**
   * Recomputes submit availability after Proc text field edits.
   *
   * @param {Event} inputEvent
   *   Input event fired by a Proc text field.
   *
   * @returns {void}
   */
  function inputChangeSubmit(inputEvent) {
    switchSubmit(
      getSubmitElements(),
      getSiblings(inputEvent).every(sibling => {
        const siblingField = document.querySelector(`[name^="${sibling}"]`);
        return siblingField.value === '' || siblingField.style.display === 'none';
      })
    );
  }
  /**
   * Recomputes submit availability after encryption checkbox changes.
   *
   * @param {Event} checkBoxEvent
   *   Input event fired by an encryption checkbox.
   *
   * @returns {void}
   */
  function checkboxChangeSubmit(checkBoxEvent) {
    switchSubmit(
      getSubmitElements(),
      getSiblings(checkBoxEvent).every(sibling => {
        const siblingCheckbox = document.querySelector(
          `[id="encrypt-checkbox-${sibling}"]`,
        );
        return siblingCheckbox.checked || siblingCheckbox.disabled;
      })
    );
  }
  /**
   * Binds submit-button toggling to encryption checkbox state changes.
   *
   * @param {NodeListOf<Element>} encryptCheckboxes
   *   Encryption checkboxes associated with Proc fields.
   *
   * @returns {void}
   */
  function switchSubmitOnCheckbox(encryptCheckboxes) {
    // Process when there is a submit element specified in drupalSettings.
    if (drupalSettings.proc && drupalSettings.proc.submit_element_id) {
      encryptCheckboxes.forEach((encryptCheckbox) => {
        encryptCheckbox.addEventListener('input', checkboxChangeSubmit);
      });
    }
  }
  /**
   * Enables or disables configured submit buttons based on computed state.
   *
   * @param {Array<HTMLElement|null>} submitElements
   *   Submit elements resolved from Drupal settings.
   * @param {boolean} enable
   *   TRUE to enable submission, FALSE to disable.
   *
   * @returns {void}
   */
  function switchSubmit(submitElements, enable) {
    submitElements.forEach(submitElement => {
      // Switch disabled attribute only if necessary and when the submit element exists:
      if (submitElement && submitElement.disabled !== !enable) {
        submitElement.disabled = !enable;
      }
    });
  }
  /**
   * Resolves configured submit buttons from Drupal settings.
   *
   * @returns {Array<HTMLElement|null>}
   *   Submit elements referenced by `submit_element_id`.
   */
  function getSubmitElements() {
    return drupalSettings.proc.submit_element_id
      .replace(/ /g, '')
      .split(',')
      .map(submitId => document.getElementById(submitId));
  }
  /**
   * Returns sibling field names encoded on a Proc input/checkbox target.
   *
   * @param {Event} event
   *   Event whose target carries `data-proc-text-siblings` metadata.
   *
   * @returns {string[]}
   *   List of sibling field names used for submit-state evaluation.
   */
  function getSiblings(event) {
    return event.target
      .getAttribute('data-proc-text-siblings')
      .split(',');
  }
  /**
   * Displays a Drupal message for decryption errors.
   *
   * @param {string} err
   *   Error message to display.
   *
   * @returns {void}
   */
  const onDecryptError = (err) => {
    const errorMessage = new Drupal.Message();
    errorMessage.add(`${Drupal.t(err)}`, {type: 'error'});
  };
  /**
   * Binds click handlers that decrypt and preview inline Proc references.
   *
   * @param {NodeListOf<Element>} procInlineDecryptionLinks
   *   Links that carry proc ID metadata for inline decryption.
   *
   * @returns {void}
   */
  function setForInlineDecryption(procInlineDecryptionLinks) {
    if (procInlineDecryptionLinks) {
      procInlineDecryptionLinks.forEach(link => {
        // Add an event listener that triggers once the link is clicked
        link.addEventListener('click', async function (event) {
          event.preventDefault();
          // Get the value of data-drupal-proc-inline-decryption attribute of the link
          const inlineDecryptionId = link.getAttribute('data-drupal-proc-inline-decryption');
          const inlineDecryptionChanged = link.getAttribute('data-drupal-proc-inline-decryption-changed') || '0';
          const procURLs = [
            `${window.location.origin + drupalSettings.path.baseUrl}api/proc/getcipher/${inlineDecryptionId}/?cipherchanged=${inlineDecryptionChanged}`,
          ];
          const cipherIndex = 0;
          let cachedCiphers = await processCache(procURLs, cipherIndex);
          const cipherText = cachedCiphers ? (await cachedCiphers.json()).pubkey[0].armored
            : '';

          const passwordSessionCachingSalt = getPassSessionCachingSalt();
          const cachedPassword = sessionStorage.getItem(
            `proc.password_cache.key_user_id.${drupalSettings.user.uid}`,
          );

          await handlePasswordDecryption({
            $,
            openpgp,
            drupalSettings,
            cachedPassword,
            passwordSessionCachingSalt,
            items: [{ cipherText, context: link }],
            onItemDecrypted: async (decrypted, item) => {
              const contentType = item.context.getAttribute('data-drupal-proc-inline-decryption-item-type') || '';
              const fileName = item.context.getAttribute('data-drupal-proc-inline-decryption-item-name') || '';
              await renderMedia($, decrypted, contentType, fileName);
            },
            onDecryptError,
            decryptPrivateKeyHandler: decryptPrivateKey,
          });
        });
      })
    }
  }
  Drupal.behaviors.ProcBehavior = ProcBehavior;
})(jQuery, Drupal, drupalSettings, once, openpgp);
