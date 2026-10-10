/**
 * @file
 * Attaches behaviors for the term_glossary_per_node module.
 */

(function ($, Drupal) {
  /**
   * Provides the summary information for the node form vertical tabs.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the node form summaries.
   */
  Drupal.behaviors.termGlossaryPerNodeSummary = {
    attach() {
      // The drupalSetSummary method required for this behavior is not available
      // on the Blocks administration page, so we need to make sure this
      // behavior is processed only if drupalSetSummary is defined.
      if (typeof $.fn.drupalSetSummary === 'undefined') {
        return;
      }

      function glossarySummary(context) {
        const active = context.querySelector(
          '[data-drupal-selector="edit-term-glossary-active"]',
        );
        if (active && active.checked) {
          const vocabulariesInput = document.querySelector(
            '[data-drupal-selector="edit-term-glossary-vocabulary"]',
          );

          if (vocabulariesInput && vocabulariesInput.value.trim()) {
            const vocabularies = vocabulariesInput.value.split(',');
            return Drupal.t('Enabled for @vocabularies', {
              '@vocabularies': vocabularies
                .map((vocabulary) =>
                  vocabulary.trim().replace(/\s*\(.*?\)\s*$/, ''),
                )
                .join(', '),
            });
          }
          return Drupal.t('Enabled');
        }
      }

      $(
        '[data-drupal-selector="edit-term-glossary-per-node"]',
      ).drupalSetSummary(glossarySummary);
    },
  };
})(jQuery, Drupal);
