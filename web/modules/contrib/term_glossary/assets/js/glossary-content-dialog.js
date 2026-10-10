// noinspection JSJQueryEfficiency

/**
 * @file
 * Js for Glossary Content.
 */

(($, Drupal, once) => {
  const tLoading = Drupal.t('Loading...');
  // Allow dialog title translation.
  const tTermDefinition = Drupal.t('Term definition');

  function calculateDialogSizeFromSetting(
    viewportSize,
    sizeSetting,
    defaultSize,
  ) {
    let dialogSize;
    if (sizeSetting === 'auto') {
      dialogSize = 'auto';
    } else {
      if (typeof sizeSetting === 'string' && sizeSetting.endsWith('%')) {
        // Percentage size (e.g., '66%').
        const percentage = parseFloat(sizeSetting) / 100;
        dialogSize = viewportSize * percentage;
      } else if (
        typeof sizeSetting === 'string' &&
        sizeSetting.endsWith('px')
      ) {
        // Fixed size in pixels (e.g., '500px').
        dialogSize = parseFloat(sizeSetting);
      } else {
        // Use the default size if no valid setting is provided.
        dialogSize = defaultSize;
      }
      // defaultSize could be 'auto'.
      if (dialogSize !== 'auto') {
        // Calculated size must be smaller than viewport.
        dialogSize = Math.min(dialogSize, viewportSize);
      }
    }
    return dialogSize;
  }

  function onTermActivation(event, settings) {
    event.preventDefault();

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    const widthSetting = settings.termGlossary.dialogWidth;
    const heightSetting = settings.termGlossary.dialogHeight;

    let dialogWidth;
    let dialogHeight;
    if (viewportWidth < 500) {
      // Use all width space on small screens (mobile).
      dialogWidth = viewportWidth * 0.95;
      dialogHeight = 'auto';
    } else {
      // Determine the width based on the setting.
      dialogWidth = calculateDialogSizeFromSetting(
        viewportWidth,
        widthSetting,
        300,
      );
      // Determine the height based on the setting.
      dialogHeight = calculateDialogSizeFromSetting(
        viewportHeight,
        heightSetting,
        'auto',
      );
    }

    const minHeightSetting = settings.termGlossary.dialogMinHeight;
    const dialogMinHeight = calculateDialogSizeFromSetting(
      viewportHeight,
      minHeightSetting,
      150,
    );

    // Prevent scrolling
    $('body').addClass('glossary-dialog-open');

    const $dialog = $(document.getElementById('glossary-dialog'));
    const dialog = $dialog.dialog({
      title: settings.termGlossary.dialogTitle || tTermDefinition,
      autoOpen: true,
      resizable: false,
      modal: true,
      width: dialogWidth,
      height: dialogHeight,
      minHeight: dialogMinHeight,
      position: {
        my: 'center',
        at: 'center',
        of: window,
      },
      // Allow default close label translation.
      closeText: Drupal.t('Close'),
      close() {
        // Re-enable scrolling
        $('body').removeClass('glossary-dialog-open');
        $dialog
          .find('#glossary-dialog-inner')
          .html(
            `<div class="ajax-progress ajax-progress-throbber"><div class="ajax-throbber">${tLoading}</div></div>`,
          );
      },
      // Show bottom close button only if enabled in settings.
      buttons: settings.termGlossary.closeButton
        ? [
            {
              // Allow close button label translation.
              text: Drupal.t('Close'),
              click(evt) {
                evt.preventDefault();
                dialog.dialog('close');
              },
              // WCAG: handle keypress event.
              keypress(evt) {
                // Needed to avoid the click event being fired next.
                evt.preventDefault();
                dialog.dialog('close');
              },
            },
          ]
        : [],
    });
    // Call Ajax here.
    const termId = event.currentTarget.dataset.gterm;
    const termText = event.currentTarget.textContent;
    const url = Drupal.url(`glossary-get-term-by-id/${termId}`);
    $.ajax({
      url,
      dataType: 'json',
      type: 'GET',
      success(data) {
        const $dialogInner = $dialog.find('#glossary-dialog-inner');
        if (data.html) {
          $dialogInner.html(data.html);
          $dialogInner.removeClass('hidden');
          // Recenter the dialog in case its size has changed.
          $dialog.dialog('option', 'position', {
            my: 'center',
            at: 'center',
            of: window,
          });
          const showEvent = new CustomEvent('glossary_modal_show', {
            detail: data,
          });
          document.dispatchEvent(showEvent);
        } else {
          // Allow "no results" message translation.
          const tNoResultsFound = Drupal.t('No results found for @term', {
            '@term': termText,
          });
          $dialogInner.html(tNoResultsFound);
        }
      },
    });
  }
  Drupal.behaviors.GlossaryContent = {
    attach(context, settings) {
      once('glossary-init-dialog', 'body', context).forEach((body) => {
        body.insertAdjacentHTML(
          'beforeend',
          `<div id="glossary-dialog" class="hidden"><div id="glossary-dialog-inner"><div class="ajax-progress ajax-progress-throbber"><div class="ajax-throbber">${tLoading}</div></div></div></div>`,
        );
      });
      once('glossaryListener', '.glos-term', context).forEach((term) => {
        term.addEventListener('click', (event) => {
          onTermActivation(event, settings);
        });
        // WCAG: handle keypress event.
        term.addEventListener('keypress', (event) => {
          onTermActivation(event, settings);
        });
      });
    },
  };
})(jQuery, Drupal, once);
