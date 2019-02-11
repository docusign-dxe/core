/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, Drupal, window) {
  Drupal.MediaLibrary = {
    currentSelection: []
  };

  Drupal.behaviors.MediaLibraryWidgetWarn = {
    attach: function attach(context) {
      $('.js-media-library-item a[href]', context).once('media-library-warn-link').on('click', function (e) {
        var message = Drupal.t('Unsaved changes to the form will be lost. Are you sure you want to leave?');
        var confirmation = window.confirm(message);
        if (!confirmation) {
          e.preventDefault();
        }
      });
    }
  };

  Drupal.behaviors.MediaLibraryTabs = {
    attach: function attach(context) {
      var $menu = $('.js-media-library-menu');
      $menu.find('a', context).once('media-library-menu-item').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var ajaxObject = Drupal.ajax({
          wrapper: 'media-library-content',
          url: e.currentTarget.href,
          dialogType: 'ajax',
          progress: {
            type: 'fullscreen',
            message: Drupal.t('Please wait...')
          }
        });

        ajaxObject.success = function (response, status) {
          var _this = this;

          if (this.progress.element) {
            $(this.progress.element).remove();
          }
          if (this.progress.object) {
            this.progress.object.stopMonitoring();
          }
          $(this.element).prop('disabled', false);

          Object.keys(response || {}).forEach(function (i) {
            if (response[i].command && _this.commands[response[i].command]) {
              _this.commands[response[i].command](_this, response[i], status);
            }
          });

          document.getElementById('media-library-content').focus();

          this.settings = null;
        };
        ajaxObject.execute();

        $menu.find('.active-tab').remove();
        $menu.find('a').removeClass('active');
        $(e.currentTarget).addClass('active').html(Drupal.t('@title<span class="active-tab visually-hidden"> (active tab)</span>', { '@title': $(e.currentTarget).html() }));
      });
    }
  };

  Drupal.behaviors.MediaLibraryItemSelection = {
    attach: function attach(context, settings) {
      var $form = $('.js-media-library-views-form', context);
      var currentSelection = Drupal.MediaLibrary.currentSelection;

      if (!$form.length) {
        return;
      }

      var $mediaItems = $('.js-media-library-item input[type="checkbox"]', $form);

      $mediaItems.once('media-item-change').on('change', function (e) {
        var id = e.currentTarget.value;

        var position = currentSelection.indexOf(id);
        if (e.currentTarget.checked) {
          if (position === -1) {
            currentSelection.push(id);
          }
        } else if (position !== -1) {
          currentSelection.splice(position, 1);
        }

        $form.find('#media-library-modal-selection').val(currentSelection.join()).trigger('change');
      });

      function disableItems($items) {
        $items.prop('disabled', true).closest('.js-media-library-item').addClass('media-library-item--disabled');
      }

      function enableItems($items) {
        $items.prop('disabled', false).closest('.js-media-library-item').removeClass('media-library-item--disabled');
      }

      function updateSelectionInfo(remaining) {
        var $buttonPane = $('.media-library-widget-modal .ui-dialog-buttonpane');
        if (!$buttonPane.length) {
          return;
        }

        var latestCount = Drupal.theme('mediaLibrarySelectionCount', Drupal.MediaLibrary.currentSelection, remaining);
        var $existingCount = $buttonPane.find('.media-library-selected-count');
        if ($existingCount.length) {
          $existingCount.replaceWith(latestCount);
        } else {
          $buttonPane.append(latestCount);
        }
      }

      $('#media-library-modal-selection', $form).once('media-library-selection-change').on('change', function (e) {
        updateSelectionInfo(settings.media_library.selection_remaining);

        if (currentSelection.length === settings.media_library.selection_remaining) {
          disableItems($mediaItems.not(':checked'));
          enableItems($mediaItems.filter(':checked'));
        } else {
          enableItems($mediaItems);
        }
      });

      currentSelection.forEach(function (value) {
        $form.find('input[type="checkbox"][value="' + value + '"]').prop('checked', true).trigger('change');
      });

      $(window).once('media-library-toggle-buttons').on('dialog:aftercreate', function () {
        updateSelectionInfo(settings.media_library.selection_remaining);
      });
    }
  };

  Drupal.behaviors.MediaLibraryModalClearSelection = {
    attach: function attach() {
      $(window).once('media-library-clear-selection').on('dialog:afterclose', function () {
        Drupal.MediaLibrary.currentSelection = [];
      });
    }
  };

  Drupal.theme.mediaLibrarySelectionCount = function (selection, remaining) {
    var selectItemsText = Drupal.formatPlural(remaining, '@selected of @count item selected', '@selected of @count items selected', {
      '@selected': selection.length
    });
    if (remaining === -1) {
      selectItemsText = Drupal.formatPlural(selection.length, '1 item selected', '@count items selected');
    }
    return '<div class="media-library-selected-count" aria-live="polite">' + selectItemsText + '</div>';
  };
})(jQuery, Drupal, window);