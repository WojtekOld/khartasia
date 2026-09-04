/**
 * @file
 * Protected Content dialog on field display.
 */
(function($, Drupal, once, drupalSettings, window) {
  Drupal.behaviors.ProcDecryptDialogBehavior = {
    attach(context) {
      once(
        'procDecryptDialogProcessed',
        '.proc-attach-dialog',
        context,
      ).forEach(function(element) {
        const field_info =
          drupalSettings.proc[element.getAttribute('data-proc-field-name')];
        const element_href = element
          .getAttribute('href')
          .replace(drupalSettings.path.baseUrl, '');
        const base_url = window.location.origin + drupalSettings.path.baseUrl;
        const path_prefix =
          base_url + element_href;
        let path_suffix = '?proc_standalone_mode=FALSE';
        path_suffix += `&proc_field_name='${element.getAttribute(
          'data-proc-field-name',
        )}'`;
        if (field_info.settings) {
          path_suffix = field_info.settings.proc_allow_password_caching
            ? `&proc_cache_password_mode=${field_info.settings.proc_allow_password_caching}`
            : '';
          path_suffix += field_info.settings.proc_field_input_mode
            ? `&proc_in_mode=${field_info.settings.proc_field_input_mode}`
            : '';
          path_suffix += field_info.settings.proc_field_decryption_mode
            ? `&proc_decryption_mode=${field_info.settings.proc_field_decryption_mode}`
            : '';
          path_suffix += field_info.settings.proc_trigger_format
            ? `&proc_decryption_trigger_fields=${field_info.settings.proc_trigger_format}`
            : '';
        }
        const proc_path = `${path_prefix}/${path_suffix}`;

        const ajaxSettings = {
          url: proc_path,
          dialogType: 'dialog',
          dialog: {
            width: 400,
            classes: {
              'ui-dialog': `class-${element.getAttribute(
                'data-proc-field-name',
              )}`,
            },
          },
        };
        const decryptDialog = Drupal.ajax(ajaxSettings);
        $(element).on('click', function(event) {
          event.preventDefault();
          const ajaxExecute = decryptDialog.execute();
          ajaxExecute
            .done(() => {
              console.info('Opening decrypt modal form');
            })
            .fail(() => {
              const warning_message = new Drupal.Message();
              warning_message.add(`${Drupal.t('Access denied for this content.')}`, {type: 'warning'});
            });
        });
      });
    },
  };
})(jQuery, Drupal, once, drupalSettings, window);
