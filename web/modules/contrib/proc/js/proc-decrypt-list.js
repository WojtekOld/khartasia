/**
 * @file
 * Decrypts cipher texts in a list.
 */
import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { decryptPrivateKey } from './modules/atomics/proc-decrypt-privkey-module.js';
import { handlePasswordDecryption } from './modules/proc-handle-password-decryption-module.js';

(function($, Drupal) {
  Drupal.behaviors.ProcBehavior = {
    async attach(context) {
      if (context.id !== '') {
        const cipherTexts = [];
        const cipherTextTypes = [];
        document.querySelectorAll('[data-proc-list-item^="proc-list-item"]').forEach((procItem) => {
          cipherTexts.push(procItem.getAttribute('data-proc-list-item-cipher-text'));
          cipherTextTypes.push(procItem.getAttribute('data-proc-list-item-type'));
        });
        const passwordSessionCachingSalt = getPassSessionCachingSalt();
        const cachedPassword = sessionStorage.getItem(
          `proc.password_cache.key_user_id.${drupalSettings.user.uid}`,
        );
        const items = cipherTexts.map((cipherText, index) => ({ cipherText, context: index }));
        const onDecryptError = (err) => {
          const errorMessage = new Drupal.Message();
          errorMessage.add(`${Drupal.t(err)}`, { type: 'error' });
        };
        /**
         * Appends rendered media nodes into a list item wrapper.
         *
         * @param {HTMLElement} procElement
         *   List item element that receives decrypted output.
         * @param {HTMLElement} targetElement
         *   Rendered node (image, svg, link, etc.) to append.
         *
         * @returns {Promise<void>}
         */
        const procAppendElement = async (procElement, targetElement) => {
          procElement.appendChild(document.createElement('div').appendChild(targetElement));
        };
        /**
         * Creates a download link when the MIME type has no inline renderer.
         *
         * @param {Object} decrypted
         *   OpenPGP decrypt result containing binary data.
         * @param {HTMLElement} procElement
         *   List item element used as the fallback render container.
         *
         * @returns {Promise<void>}
         */
        const linkUnrecognizedMimeTypes = async (decrypted, procElement) => {
          // Add temporary download link the same way as in proc-decrypt.js.
          const temporaryDownloadLink = document.createElement('a');
          temporaryDownloadLink.setAttribute(
            'href',
            URL.createObjectURL(new Blob([decrypted.data], {
              type: 'application/octet-binary',
              endings: 'native',
            }))
          );
          temporaryDownloadLink.setAttribute(
            'download',
            procElement.innerText,
          );
          temporaryDownloadLink.innerText = Drupal.t('Download');
          // If it has not yet been added.
          if (!procElement.innerHTML.includes('blob')) {
            procElement.appendChild(document.createElement('div').appendChild(temporaryDownloadLink));
          }
        };
        /**
         * Renders decrypted payloads using a MIME-aware viewer strategy.
         *
         * Depending on the source type, this function renders plain text,
         * inline image/video/audio/PDF content, or a fallback download action.
         *
         * @param {number} cipherTextIndex
         *   Index of the current decrypted item in the list.
         * @param {Object} decrypted
         *   OpenPGP decrypt result containing binary data.
         *
         * @returns {Promise<void>}
         */
        const renderMedia = async (cipherTextIndex, decrypted) => {
          let procElement = document.querySelectorAll('[data-proc-list-item^="proc-list-item"]')[cipherTextIndex];
          let contentType = cipherTextTypes[cipherTextIndex];
          let url = URL.createObjectURL(new Blob([decrypted.data]));
          const setTextContent = (element, text) => {
            if (element.innerText) {
              element.innerText = text;
            } else {
              element.value = text;
            }
          };
          const renderMediaTag = (tagName) => {
            const mediaElement = document.createElement(tagName.toUpperCase());
            mediaElement.src = url;
            mediaElement.controls = true;
            if (!procElement.innerHTML.includes('blob')) {
              const wrapper = document.createElement('div');
              wrapper.appendChild(mediaElement);
              procElement.appendChild(wrapper);
            }
          };
          const renderPdfIframe = () => {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.setAttribute('title', Drupal.t('Protected Content PDF Viewer'));
            iframe.style.width = '100%';
            iframe.style.minHeight = '600px';
            if (!procElement.innerHTML.includes('blob')) {
              const wrapper = document.createElement('div');
              wrapper.appendChild(iframe);
              procElement.appendChild(wrapper);
            }
          };
          if (contentType === 'text/plain') {
            setTextContent(procElement, new TextDecoder().decode(decrypted.data));
          } else if (contentType.includes('image')) {
            if (contentType.includes('svg')) {
              const parser = new DOMParser();
              const svg = parser.parseFromString(
                new TextDecoder().decode(decrypted.data),
                'text/html'
              ).querySelector('svg');
              await procAppendElement(procElement, svg);
            } else {
              const image = new Image();
              image.src = url;
              if (!procElement.innerHTML.includes('blob')) {
                await procAppendElement(procElement, image);
              }
            }
          } else if (contentType.includes('video')) {
            renderMediaTag("video");
          } else if (contentType.includes('audio')) {
            renderMediaTag("audio");
          } else if (contentType === 'application/pdf') {
            renderPdfIframe();
          } else {
            await linkUnrecognizedMimeTypes(decrypted, procElement);
          }
        };
        await handlePasswordDecryption({
          $,
          openpgp,
          drupalSettings,
          cachedPassword,
          passwordSessionCachingSalt,
          items,
          onItemDecrypted: async (decrypted, item) => {
            await renderMedia(item.context, decrypted);
          },
          onDecryptError,
          decryptPrivateKeyHandler: decryptPrivateKey,
        });
      }
    },
  };
})(jQuery, Drupal);
