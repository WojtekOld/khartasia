(function($) {
  Drupal.behaviors.custom_search = {
    attach: function(context) {

      if (!Drupal.settings.custom_search.solr) {
        // Check if the search box is not empty on submit
        $('form.search-form', context).submit(function(){
          var $this = $(this);
          var box = $this.find('input.custom-search-box');
          if (box.val() != undefined && box.val() == '') {
            $this.find('input.custom-search-box').addClass('error');
            return false;
          }
          // If basic search is hidden, copy or value to the keys
          if ($this.find('#edit-keys').parents('div.element-invisible').attr('class') == 'element-invisible') {
            $this.find('#edit-keys').val($this.find('#edit-or').val());
            $this.find('#edit-or').val('');
          }
          return true;
        });
      }

      // Search from target
      $('form.search-form').attr('target', Drupal.settings.custom_search.form_target);

      // Displays Popup.
      $('form.search-form input.custom-search-box', context).bind('click focus', function(e){
        var $parentForm = $(this).parents('form');
        // check if there's something in the popup and displays it
        var popup = $parentForm.find('fieldset.custom_search-popup');
        if (popup.find('input,select').length && !popup.hasClass('opened')) {
          popup.fadeIn().addClass('opened');
        }
        e.stopPropagation();
      });
      $(document).bind('click focus', function(){
        $('fieldset.custom_search-popup').hide().removeClass('opened');
      });

      // Handle checkboxes
      $('.custom-search-selector input:checkbox', context).each(function(){
        var el = $(this);
        if (el.val() == 'c-all') {
          el.change(function(){
            $(this).parents('.custom-search-selector').find('input:checkbox[value!=c-all]').attr('checked', false);
          });
        }
        else {
          if (el.val().substr(0,2) == 'c-') {
            el.change(function(){
              $('.custom-search-selector input:checkbox').each(function(){
                if ($(this).val().substr(0,2) == 'o-') {
                  $(this).attr('checked', false);
                }
              });
              $(this).parents('.custom-search-selector').find('input:checkbox[value=c-all]').attr('checked', false);
            });
          } else {
            el.change(function(){
              $(this).parents('.custom-search-selector').find('input:checkbox[value!=' + el.val() + ']').attr('checked', false);
            });
          }
        }
      });

      // Handle popup.
      var popup = $('fieldset.custom_search-popup:not(.custom_search-processed)', context).addClass("custom_search-processed");
      popup.click(function(e){
        e.stopPropagation();
      })
      popup.append('<a class="custom_search-popup-close" href="#">' + Drupal.t('Close') + '</a>');
      $('a.custom_search-popup-close').click(function(e){
        $('fieldset.custom_search-popup.opened').hide().removeClass('opened');
        e.preventDefault();
      });

    }
  }
})(jQuery);
;
/**
 * @file
 * Attaches behaviors for the Dashboard module.
 */

(function ($) {

/**
 * Implements Drupal.behaviors for the Dashboard module.
 */
Drupal.behaviors.dashboard = {
  attach: function (context, settings) {
    $('#dashboard', context).once(function () {
      $(this).prepend('<div class="customize clearfix"><ul class="action-links"><li><a href="#">' + Drupal.t('Customize dashboard') + '</a></li></ul><div class="canvas"></div></div>');
      $('.customize .action-links a', this).click(Drupal.behaviors.dashboard.enterCustomizeMode);
    });
    Drupal.behaviors.dashboard.addPlaceholders();
    if (Drupal.settings.dashboard.launchCustomize) {
      Drupal.behaviors.dashboard.enterCustomizeMode();
    }
  },

  addPlaceholders: function() {
    $('#dashboard .dashboard-region .region').each(function () {
      var empty_text = "";
      // If the region is empty
      if ($('.block', this).length == 0) {
        // Check if we are in customize mode and grab the correct empty text
        if ($('#dashboard').hasClass('customize-mode')) {
          empty_text = Drupal.settings.dashboard.emptyRegionTextActive;
        } else {
          empty_text = Drupal.settings.dashboard.emptyRegionTextInactive;
        }
        // We need a placeholder.
        if ($('.dashboard-placeholder', this).length == 0) {
          $(this).append('<div class="dashboard-placeholder"></div>');
        }
        $('.dashboard-placeholder', this).html(empty_text);
      }
      else {
        $('.dashboard-placeholder', this).remove();
      }
    });
  },

  /**
   * Enters "customize" mode by displaying disabled blocks.
   */
  enterCustomizeMode: function () {
    $('#dashboard').addClass('customize-mode customize-inactive');
    Drupal.behaviors.dashboard.addPlaceholders();
    // Hide the customize link
    $('#dashboard .customize .action-links').hide();
    // Load up the disabled blocks
    $('div.customize .canvas').load(Drupal.settings.dashboard.drawer, Drupal.behaviors.dashboard.setupDrawer);
  },

  /**
   * Exits "customize" mode by simply forcing a page refresh.
   */
  exitCustomizeMode: function () {
    $('#dashboard').removeClass('customize-mode customize-inactive');
    Drupal.behaviors.dashboard.addPlaceholders();
    location.href = Drupal.settings.dashboard.dashboard;
  },

  /**
   * Sets up the drag-and-drop behavior and the 'close' button.
   */
  setupDrawer: function () {
    $('div.customize .canvas-content input').click(Drupal.behaviors.dashboard.exitCustomizeMode);
    $('div.customize .canvas-content').append('<a class="button" href="' + Drupal.settings.dashboard.dashboard + '">' + Drupal.t('Done') + '</a>');

    // Initialize drag-and-drop.
    var regions = $('#dashboard div.region');
    regions.sortable({
      connectWith: regions,
      cursor: 'move',
      cursorAt: {top:0},
      dropOnEmpty: true,
      items: '> div.block, > div.disabled-block',
      placeholder: 'block-placeholder clearfix',
      tolerance: 'pointer',
      start: Drupal.behaviors.dashboard.start,
      over: Drupal.behaviors.dashboard.over,
      sort: Drupal.behaviors.dashboard.sort,
      update: Drupal.behaviors.dashboard.update
    });
  },

  /**
   * Makes the block appear as a disabled block while dragging.
   *
   * This function is called on the jQuery UI Sortable "start" event.
   *
   * @param event
   *  The event that triggered this callback.
   * @param ui
   *  An object containing information about the item that is being dragged.
   */
  start: function (event, ui) {
    $('#dashboard').removeClass('customize-inactive');
    var item = $(ui.item);

    // If the block is already in disabled state, don't do anything.
    if (!item.hasClass('disabled-block')) {
      item.css({height: 'auto'});
    }
  },

  /**
   * Adapts block's width to the region it is moved into while dragging.
   *
   * This function is called on the jQuery UI Sortable "over" event.
   *
   * @param event
   *  The event that triggered this callback.
   * @param ui
   *  An object containing information about the item that is being dragged.
   */
  over: function (event, ui) {
    var item = $(ui.item);

    // If the block is in disabled state, remove width.
    if ($(this).closest('#disabled-blocks').length) {
      item.css('width', '');
    }
    else {
      item.css('width', $(this).width());
    }
  },

  /**
   * Adapts a block's position to stay connected with the mouse pointer.
   *
   * This function is called on the jQuery UI Sortable "sort" event.
   *
   * @param event
   *  The event that triggered this callback.
   * @param ui
   *  An object containing information about the item that is being dragged.
   */
  sort: function (event, ui) {
    var item = $(ui.item);

    if (event.pageX > ui.offset.left + item.width()) {
      item.css('left', event.pageX);
    }
  },

  /**
   * Sends block order to the server, and expand previously disabled blocks.
   *
   * This function is called on the jQuery UI Sortable "update" event.
   *
   * @param event
   *   The event that triggered this callback.
   * @param ui
   *   An object containing information about the item that was just dropped.
   */
  update: function (event, ui) {
    $('#dashboard').addClass('customize-inactive');
    var item = $(ui.item);

    // If the user dragged a disabled block, load the block contents.
    if (item.hasClass('disabled-block')) {
      var module, delta, itemClass;
      itemClass = item.attr('class');
      // Determine the block module and delta.
      module = itemClass.match(/\bmodule-(\S+)\b/)[1];
      delta = itemClass.match(/\bdelta-(\S+)\b/)[1];

      // Load the newly enabled block's content.
      $.get(Drupal.settings.dashboard.blockContent + '/' + module + '/' + delta, {},
        function (block) {
          if (block) {
            item.html(block);
          }

          if (item.find('div.content').is(':empty')) {
            item.find('div.content').html(Drupal.settings.dashboard.emptyBlockText);
          }

          Drupal.attachBehaviors(item);
        },
        'html'
      );
      // Remove the "disabled-block" class, so we don't reload its content the
      // next time it's dragged.
      item.removeClass("disabled-block");
    }

    Drupal.behaviors.dashboard.addPlaceholders();

    // Let the server know what the new block order is.
    $.post(Drupal.settings.dashboard.updatePath, {
        'form_token': Drupal.settings.dashboard.formToken,
        'regions': Drupal.behaviors.dashboard.getOrder
      }
    );
  },

  /**
   * Returns the current order of the blocks in each of the sortable regions.
   *
   * @return
   *   The current order of the blocks, in query string format.
   */
  getOrder: function () {
    var order = [];
    $('#dashboard div.region').each(function () {
      var region = $(this).parent().attr('id').replace(/-/g, '_');
      var blocks = $(this).sortable('toArray');
      $.each(blocks, function() {
        order.push(region + '[]=' + this);
      });
    });
    order = order.join('&');
    return order;
  }
};

})(jQuery);
;
