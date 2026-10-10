/**
 * @file
 * Js for block.
 */

(($, Drupal, drupalSettings, once) => {
  Drupal.behaviors.AlphaBlock = {
    attach(context) {
      if (context === document) {
        // Set up stuff here.
      }

      function removeActiveClass() {
        $('#glossary-alpha-block li a').each(function processLink() {
          if ($(this).hasClass('active')) {
            $(this).removeClass('active');
          }
        });
      }

      function handleResults(data) {
        const resultsEle = $('#glossary-alpha-results');
        resultsEle.html('');
        if (data.message) {
          resultsEle.html(data.message);
        } else {
          const items = Array.isArray(data) ? data : Object.values(data);
          items.forEach((item) => {
            let string = `<div class='media' data-term='${item.tid}'>`;
            string += item.html || '';
            string += '</div>';
            resultsEle.append(string);
          });
        }
      }
      function searchTerm(term) {
        const url = Drupal.url(`glossary-search-term?t=${encodeURI(term)}`);
        $.ajax({
          url,
          dataType: 'json',
          type: 'GET',
          success(data) {
            handleResults(data);
          },
        });
      }

      function searchLetter(letter) {
        const url = Drupal.url(`glossary-search-letter/${letter}`);
        $.ajax({
          url,
          dataType: 'json',
          type: 'GET',
          success(data) {
            handleResults(data);
          },
        });
      }

      // Search box.
      $(once('glossary-by-term', '#glossary-by-term-btn')).on(
        'click',
        function onClick(event) {
          event.preventDefault();
          const theText = $('#glossary-search-text')[0].value;
          if (theText) {
            searchTerm(theText);
          } else {
            $('#glossary-alpha-results').html('Please enter a search term.');
            return false;
          }
        },
      );
      // End search box.
      $(once('glossary-alpha-block', '#glossary-alpha-block li a')).on(
        'click',
        function onClick(event) {
          event.preventDefault();
          removeActiveClass();
          const letter = this.textContent;
          const item = $(this);
          item.addClass('active');
          searchLetter(letter);
        },
      );
    },
  };
})(jQuery, Drupal, drupalSettings, once);
