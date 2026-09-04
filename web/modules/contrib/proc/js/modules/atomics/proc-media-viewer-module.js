/**
 * @file
 * Reusable MIME-aware media viewer for decrypted Protected Content.
 *
 */

/**
 * Creates a modal dialog for decrypted content previews.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {HTMLElement} contentElement
 *   Element rendered inside the jQuery UI dialog body.
 * @param {string} title
 *   Dialog title shown in the titlebar.
 * @param {Function} [onClose]
 *   Optional callback executed before dialog teardown.
 * @param {Object} [dialogOptions]
 *   Additional jQuery UI dialog options merged into defaults.
 *
 * @returns {void}
 */
const procViewerDialog = ($, contentElement, title, onClose, dialogOptions = {}) => {
  const dialogDiv = $(document.createElement('div'));
  dialogDiv.addClass('proc-content-viewer-dialog');
  dialogDiv.append(contentElement);
  dialogDiv.dialog({
    width: 'auto',
    modal: true,
    title,
    ...dialogOptions,
    close: function () {
      if (typeof onClose === 'function') {
        onClose();
      }
      dialogDiv.dialog('destroy');
      dialogDiv.remove();
    },
  });
};

/**
 * Resolves the typed Blob MIME string from a content-type value.
 *
 * Returns a browser-safe MIME type so Chromium correctly identifies the
 * Blob content rather than rendering it as plain text.
 *
 * @param {string} contentType
 *   Normalized (lowercase) MIME type string.
 *
 * @returns {string}
 *   Blob-compatible MIME string.
 */
const resolveBlobType = (contentType) => {
  if (contentType === 'application/pdf') {
    return 'application/pdf';
  }
  if (contentType === 'text/plain') {
    return 'text/plain;charset=utf-8';
  }
  if (
    contentType.startsWith('image/') ||
    contentType.startsWith('video/') ||
    contentType.startsWith('audio/')
  ) {
    return contentType;
  }
  return 'application/octet-stream';
};

/**
 * Renders fallback download UI when inline preview is not supported.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} decrypted
 *   OpenPGP decrypt result containing binary data.
 * @param {string} fileName
 *   Original file name for the download attribute.
 *
 * @returns {void}
 */
const linkUnrecognizedMimeTypes = ($, decrypted, fileName) => {
  const fileUrl = URL.createObjectURL(new Blob([decrypted.data], {
    type: 'application/octet-binary',
    endings: 'native',
  }));
  const temporaryDownloadLink = document.createElement('a');
  temporaryDownloadLink.setAttribute('href', fileUrl);
  temporaryDownloadLink.setAttribute('download', fileName || Drupal.t('protected-content.bin'));
  temporaryDownloadLink.innerText = Drupal.t('Download');

  const wrapper = document.createElement('div');
  wrapper.appendChild(temporaryDownloadLink);
  procViewerDialog($, wrapper, Drupal.t('Protected Content File Viewer'), () => {
    URL.revokeObjectURL(fileUrl);
  });
};

/**
 * Renders decrypted inline content using a MIME-aware dialog viewer.
 *
 * Supports text, images, video, audio, and PDF previews, with fallback to a
 * download link dialog for unsupported content types.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} decrypted
 *   OpenPGP decrypt result containing binary data.
 * @param {string} contentType
 *   MIME type of the decrypted content (raw, normalized internally).
 * @param {string} [fileName]
 *   Original file name used for download attributes.
 *
 * @returns {Promise<void>}
 */
export const renderMedia = async ($, decrypted, contentType, fileName) => {
  // When no contentType is supplied by the caller (e.g. stand-alone mode where
  // source_file_type was not stored), fall back to the first non-empty MIME
  // type available in drupalSettings so the viewer can still render inline.
  const resolvedType = contentType
    || (window.drupalSettings?.proc?.proc_sources_file_types ?? []).find(t => t)
    || '';
  const normalizedType = resolvedType.toLowerCase();
  const url = URL.createObjectURL(new Blob([decrypted.data], { type: resolveBlobType(normalizedType) }));

  const renderInDialog = (element, title) => {
    procViewerDialog($, element, title, () => {
      URL.revokeObjectURL(url);
    });
  };

  const renderMediaTag = (tagName) => {
    const mediaElement = document.createElement(tagName.toUpperCase());
    mediaElement.src = url;
    mediaElement.controls = true;
    renderInDialog(mediaElement, Drupal.t('Protected Content Viewer'));
  };

  const renderPdfIframe = () => {
    // Use a File-backed Object URL so the browser's native PDF reader
    // picks up the original filename for its "Save" / download button
    // instead of defaulting to "document.pdf".
    const pdfFile = new File([decrypted.data], fileName || 'document.pdf', { type: 'application/pdf' });
    const pdfUrl = URL.createObjectURL(pdfFile);
    // Append filename as a fragment so Firefox's pdf.js
    // getPdfFilenameFromUrl() picks it up instead of "document.pdf".
    const pdfSrc = fileName ? pdfUrl + '#' + encodeURIComponent(fileName) : pdfUrl;

    const iframe = document.createElement('iframe');
    iframe.src = pdfSrc;
    iframe.type = 'application/pdf';
    iframe.setAttribute('title', Drupal.t('Protected Content PDF Viewer'));
    iframe.style.width = '100%';
    iframe.style.height = '75vh';

    const wrapper = document.createElement('div');
    if (fileName) {
      const downloadLink = document.createElement('a');
      downloadLink.setAttribute('href', pdfUrl);
      downloadLink.setAttribute('download', fileName);
      downloadLink.innerText = Drupal.t('Download @name', { '@name': fileName });
      downloadLink.style.display = 'block';
      downloadLink.style.marginBottom = '0.5em';
      wrapper.appendChild(downloadLink);
    }
    wrapper.appendChild(iframe);

    procViewerDialog(
      $,
      wrapper,
      Drupal.t('Protected Content PDF Viewer'),
      () => {
        URL.revokeObjectURL(pdfUrl);
      },
      {
        width: Math.min(window.innerWidth * 0.9, 1200),
        height: Math.min(window.innerHeight * 0.9, 900),
      }
    );
  };

  if (normalizedType === 'text/plain') {
    const pre = document.createElement('pre');
    pre.textContent = new TextDecoder().decode(decrypted.data);
    procViewerDialog($, pre, Drupal.t('Protected Content Text Viewer'));
  } else if (normalizedType.includes('image')) {
    if (normalizedType.includes('svg')) {
      const parser = new DOMParser();
      const svg = parser.parseFromString(
        new TextDecoder().decode(decrypted.data),
        'text/html'
      ).querySelector('svg');
      renderInDialog(svg, Drupal.t('Protected Content Image Viewer'));
    } else {
      const image = new Image();
      image.src = url;
      renderInDialog(image, Drupal.t('Protected Content Image Viewer'));
    }
  } else if (normalizedType.includes('video')) {
    renderMediaTag('video');
  } else if (normalizedType.includes('audio')) {
    renderMediaTag('audio');
  } else if (normalizedType === 'application/pdf') {
    renderPdfIframe();
  } else {
    URL.revokeObjectURL(url);
    linkUnrecognizedMimeTypes($, decrypted, fileName);
  }
};

