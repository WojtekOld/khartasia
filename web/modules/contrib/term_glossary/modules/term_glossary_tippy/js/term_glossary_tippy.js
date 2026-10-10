((Drupal, once, tippy) => {
  // Hide on escape Tippy plugin.
  // @see https://atomiks.github.io/tippyjs/v6/plugins/#hideonesc
  const hideOnEsc = {
    name: 'hideOnEsc',
    defaultValue: true,
    fn: function fn(instance) {
      function onKeyDown(event) {
        if (event.keyCode === 27) {
          instance.hide();
        }
      }
      return {
        onShow: function onShow() {
          document.addEventListener('keydown', onKeyDown);
        },
        onHide: function onHide() {
          document.removeEventListener('keydown', onKeyDown);
        },
      };
    },
  };

  Drupal.behaviors.termGlossaryTippy = {
    attach(context, settings) {
      if (!context.querySelector('.glos-term-tippy')) {
        // No term to process.
        return;
      }
      // Initialize Tippy options only once per context.
      if (!context.termGlossaryTippyOptions) {
        let tippyOptions = {
          // Use title or aria-label.
          content: (reference) =>
            reference.getAttribute('title') ||
            reference.getAttribute('aria-label'),
          onShow(instance) {
            if (
              instance.reference.tagName.toLowerCase() === 'abbr' &&
              instance.reference.hasAttribute('title')
            ) {
              // Prevent default tooltip.
              instance.reference.setAttribute(
                'data-title-save',
                instance.reference.getAttribute('title'),
              );
              instance.reference.removeAttribute('title');
            }
          },
          onHide(instance) {
            if (instance.reference.hasAttribute('data-title-save')) {
              // Restore title.
              instance.reference.setAttribute(
                'title',
                instance.reference.getAttribute('data-title-save'),
              );
              instance.reference.removeAttribute('data-title-save');
            }
          },
          allowHTML: true,
          arrow: true,
          theme: 'light',
          placement: 'top',
        };
        if (settings.termGlossaryTippy.a11yFeatures) {
          const a11yOptions = {
            interactive: true,
            aria: {
              content: 'describedby',
            },
            appendTo: 'parent',
            plugins: [hideOnEsc],
          };
          tippyOptions = { ...tippyOptions, ...a11yOptions };
        }
        context.termGlossaryTippyOptions = tippyOptions;
      }
      // Select all term elements and apply Tippy.js
      once('termGlossaryTippy', '.glos-term-tippy', context).forEach((term) => {
        if (term.hasAttribute('title') || term.hasAttribute('aria-label')) {
          tippy(term, context.termGlossaryTippyOptions);
        }
      });
    },
  };
})(Drupal, once, window.tippy);
