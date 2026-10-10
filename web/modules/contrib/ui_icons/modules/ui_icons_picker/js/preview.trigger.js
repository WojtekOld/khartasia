/**
 * @file
 * JavaScript behavior for UI Icons preview used as a picker trigger.
 */
(($, Drupal, once) => {
  /**
   * Grid glyph filling the preview box while no icon is selected.
   *
   * Without it an empty preview box is a blank square with nothing to suggest
   * it can be clicked.
   */
  const placeholder = `<span class="ui-icons-preview-placeholder" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false">
      <rect x="3" y="3" width="5" height="5" rx="1"/>
      <rect x="9.5" y="3" width="5" height="5" rx="1"/>
      <rect x="16" y="3" width="5" height="5" rx="1"/>
      <rect x="3" y="9.5" width="5" height="5" rx="1"/>
      <rect x="9.5" y="9.5" width="5" height="5" rx="1"/>
      <rect x="16" y="9.5" width="5" height="5" rx="1"/>
      <rect x="3" y="16" width="5" height="5" rx="1"/>
      <rect x="9.5" y="16" width="5" height="5" rx="1"/>
      <rect x="16" y="16" width="5" height="5" rx="1"/>
    </svg>
  </span>`;

  /**
   * Attaches the icon library dialog to the preview of an icon element.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the preview trigger behaviors.
   */
  Drupal.behaviors.icon_preview_trigger = {
    attach(context) {
      once(
        'iconPreviewTrigger',
        '.ui-icons-wrapper input[data-icon-preview-trigger]',
        context,
      ).forEach((input) => {
        const wrapper = input.closest('.ui-icons-wrapper');
        const preview = wrapper && wrapper.querySelector('.ui-icons-preview');
        if (!preview) {
          return;
        }

        const label = Drupal.t('Browse icons');
        preview.classList.add('ui-icons-preview--picker');
        preview.setAttribute('role', 'button');
        preview.setAttribute('tabindex', '0');
        preview.setAttribute('aria-label', label);
        preview.setAttribute('title', label);

        if (!preview.querySelector('.ui-icons-preview-icon')) {
          preview.insertAdjacentHTML('beforeend', placeholder);
        }

        preview.addEventListener('click', (event) => {
          event.preventDefault();
          Drupal.IconPicker.openDialog(input);
        });

        preview.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter' && event.key !== ' ') {
            return;
          }
          // Space scrolls the page and Enter submits the form otherwise.
          event.preventDefault();
          Drupal.IconPicker.openDialog(input);
        });
      });
    },
  };
})(jQuery, Drupal, once);
