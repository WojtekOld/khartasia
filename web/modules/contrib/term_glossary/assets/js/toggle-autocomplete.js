// noinspection JSJQueryEfficiency

/**
 * @file
 * Js for Glossary Content.
 */

((Drupal, once) => {
  Drupal.behaviors.GlossaryToggleAutocomplete = {
    attach(context) {
      once(
        'term-glossary-toggle-autocomplete',
        'input.toggle-vocabulary-autocomplete',
        context,
      ).forEach((checkbox) => {
        const target = checkbox.form.querySelector(
          `[data-toggle-target-id=${checkbox.dataset.toggleTarget}]`,
        );
        const autocompleteItem = target.closest('.form-item');
        // Initial state
        if (!checkbox.checked) {
          autocompleteItem.classList.add('hidden');
        }
        // Toggle on checkbox change
        checkbox.addEventListener('change', function onChange() {
          if (this.checked) {
            autocompleteItem.classList.remove('hidden');
          } else {
            autocompleteItem.classList.add('hidden');
          }
        });
      });
    },
  };
})(Drupal, once);
