/**
 * @file
 * Glossary entries search.
 */
// eslint-disable-next-line func-names
(function ($, Drupal, once) {
  Drupal.behaviors.entriesSearch = {
    attach(context) {
      const elements = once('entries-search', '.entries-search-input', context);
      if (!elements.length) return;

      const $searchInput = $(elements);
      const $resetButton = $('.entries-reset-button', context);
      const $entriesWrapper = $('.entries-multivalue-wrapper', context);

      // Preprocess entry data during initialization.
      const entriesData = this.prepareEntriesData($entriesWrapper);

      // Search input.
      let searchTimeout;
      $searchInput.on('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          // eslint-disable-next-line no-jquery/no-val
          const searchTerm = $searchInput.val().toLowerCase().trim();
          if (searchTerm.length >= 3) {
            this.performSearch(entriesData, searchTerm, $entriesWrapper);
          } else if (!searchTerm.length) {
            this.showAllEntries($entriesWrapper);
          }
        }, 300);
      });

      // Reset button handler.
      $resetButton.on('click', (e) => {
        e.preventDefault();
        // eslint-disable-next-line no-jquery/no-val
        $searchInput.val('');
        this.showAllEntries($entriesWrapper);
        $searchInput.trigger('focus');
      });
    },

    // Prepare entries data for search.
    prepareEntriesData($entriesWrapper) {
      const entriesData = [];
      const $subjectInputs = $entriesWrapper.find('input[name*="[subject]"]');

      // Process each subject input to find its associated container.
      // eslint-disable-next-line func-names
      $subjectInputs.each(function () {
        const $subjectInput = $(this);
        const name = $subjectInput.attr('name');
        const indexMatch = name.match(/entries\[(\d+)\]/);
        if (!indexMatch) return;

        const index = indexMatch[1];
        const $definitionInput = $entriesWrapper.find(
          `input[name='entries[${index}][definition]']`,
        );
        if (!$definitionInput.length) return;

        // Find common container.
        let $container = $subjectInput.closest(
          'td, tr, .form-wrapper, .field-multiple-row',
        );
        for (let i = 0; i < 5; i++) {
          if ($container.find($definitionInput).length) break;
          $container = $container.parent();
        }

        // Fallback to subject input's parent if no container found.
        if (!$container.find($definitionInput).length) {
          $container = $subjectInput.parent();
        }

        entriesData.push({
          $container,
          $subjectInput,
          $definitionInput,
          $row: $container.closest('tr'),
        });
      });

      return entriesData;
    },

    // Perform actual search.
    performSearch(entriesData, searchTerm, $entriesWrapper) {
      let matchCount = 0;

      entriesData.forEach((entry) => {
        // eslint-disable-next-line no-jquery/no-val
        const subjectVal = (entry.$subjectInput.val() || '').toLowerCase();
        const definitionVal = // eslint-disable-next-line no-jquery/no-val
          (entry.$definitionInput.val() || '').toLowerCase();
        const matches =
          subjectVal.includes(searchTerm) || definitionVal.includes(searchTerm);

        if (matches) {
          entry.$container.closest('tr').show();
          // eslint-disable-next-line no-plusplus
          matchCount++;
        } else {
          entry.$container.closest('tr').hide();
        }
      });

      this.updateSearchResults($entriesWrapper, searchTerm, matchCount);
    },

    showAllEntries($entriesWrapper) {
      $entriesWrapper.find('tr').show();
      this.removeNoResultsMessage($entriesWrapper);
    },

    updateSearchResults($entriesWrapper, searchTerm, matchCount) {
      this.removeNoResultsMessage($entriesWrapper);

      if (matchCount === 0) {
        const message = Drupal.t('No entries found matching "@term".', {
          '@term': searchTerm,
        });
        $entriesWrapper.prepend(
          `<div class='entries-no-results'><p>${message}</p></div>`,
        );
      }
    },

    removeNoResultsMessage($entriesWrapper) {
      $entriesWrapper.find('.entries-no-results').remove();
    },
  };
})(jQuery, Drupal, once);
