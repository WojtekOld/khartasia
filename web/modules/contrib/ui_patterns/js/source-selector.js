/**
 * @file
 * The rows of the source selector: what the browser cannot tell a host.
 *
 * A cancelled row puts values back, a source switch lands a form: both come
 * from the server, so the browser fires no change event. The marker on the
 * refreshed markup fires it here, natively: jQuery-triggered events never
 * reach a listener bound with addEventListener, and hosts bind that way.
 */

((Drupal, once) => {
  const ROW = '[id*="ui-patterns-prop-row"], .uip-slot-row';

  Drupal.behaviors.uiPatternsSourceSelector = {
    attach(context) {
      once('uip-announce', '[data-uip-announce]', context).forEach(
        (control) => {
          control.removeAttribute('data-uip-announce');
          control.dispatchEvent(new Event('change', { bubbles: true }));
        },
      );
      // Escape cancels the row, and stops there: the dialog, off-canvas or
      // editor holding the form would close instead. A widget that already
      // took the key (an autocomplete, an editor) keeps it.
      once('uip-escape', ROW, context).forEach((row) => {
        row.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape' || event.defaultPrevented) {
            return;
          }
          const cancel = [
            ...row.querySelectorAll('.uip-source-selector-toggle--cancel'),
          ].find((button) => button.closest(ROW) === row);
          if (cancel) {
            event.preventDefault();
            event.stopPropagation();
            cancel.click();
          }
        });
      });
    },
  };
})(Drupal, once);
