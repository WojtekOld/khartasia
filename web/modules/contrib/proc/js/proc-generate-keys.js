/**
 * @file
 * Protected Content key generation.
 */
(function($, Drupal, once, drupalSettings, openpgp, navigator) {
  Drupal.behaviors.ProcBehavior = {
    attach(context) {
      once('proc-generate-keys', 'html', context).forEach(function() {
        const procJsLabels = drupalSettings.proc.proc_labels;
        const procData = drupalSettings.proc.proc_data;
        const procKeySize = Number(drupalSettings.proc.proc_key_size || '4096');
        const $pass1 = $('#edit-password-confirm-pass1');
        const $pass2 = $('#edit-password-confirm-pass2');
        const $submit = $('#edit-submit');
        /**
         * Clears both password inputs after validation or generation failures.
         *
         * @returns {void}
         */
        function resetPasswordFields() {
          $pass1.val('');
          $pass2.val('');
        }
        /**
         * Validates password presence, confirmation, and required strength.
         *
         * Returns a translated error message key when validation fails or zero
         * when the password can be used for key generation.
         *
         * @param {string} pass
         *   User-entered password value.
         * @param {string} passConfirm
         *   Password confirmation value.
         * @param {string} strength
         *   Required textual strength label from Drupal settings.
         *
         * @returns {number|string}
         */
        function validatePassword(pass, passConfirm, strength) {
          if (pass.length === 0) {
            return procJsLabels.proc_password_required;
          }
          if (pass !== passConfirm) {
            return procJsLabels.proc_password_match;
          }
          if ($('.password-strength__text').text() !== strength) {
            return procJsLabels.proc_pass_weak;
          }
          return 0;
        }

        $submit.on('click', async function(e) {
          e.preventDefault();
          const pass = $pass1.val();
          const passConfirm = $pass2.val();

          if (pass.length > 0 && passConfirm.length > 0) {
            const passPlaceholder = 'x';
            let passConfirmationPlaceholder;

            if (pass === passConfirm) {
              passConfirmationPlaceholder = passPlaceholder;
            } else {
              passConfirmationPlaceholder = 'y';
            }

            $('#edit-password-confirm-pass1')[0].value = passPlaceholder.repeat(pass.length);
            $('#edit-password-confirm-pass2')[0].value = passConfirmationPlaceholder.repeat(passConfirm.length);
          }

          const passwordError = validatePassword(
            pass,
            passConfirm,
            procJsLabels.proc_minimal_password_strength,
          );

          if (!passwordError) {
            $submit[0].value =
              procJsLabels.proc_button_state_processing;
            openpgp.config.useIndutnyElliptic = false;
            openpgp.config.showComment = true;
            openpgp.config.showVersion = true;
            openpgp.config.commentString = `${procData.proc_name}:${procData.proc_email}`;
            const cryptoPass = procData.proc_pass.concat(pass);
            const startSeconds = new Date().getTime() / 1000;

            const {
              privateKey,
              publicKey,
            } = await openpgp
              .generateKey({
                userIDs: [
                  {
                    name: procData.proc_name,
                    email: procData.proc_email,
                  },
                ],
                type: 'rsa',
                passphrase: cryptoPass,
                rsaBits: procKeySize,
                format: 'armored',
              })
              .catch(function(err) {
                const message = new Drupal.Message();
                message.add(`${Drupal.t(err)}`, {type: 'error'});
                resetPasswordFields();
                $submit[0].value = procJsLabels.proc_generate_keys_submit_label;
              });

            const endSeconds = new Date().getTime() / 1000;
            $submit[0].value = procJsLabels.proc_submit_saving_state;
            $('input[name=public_key]')[0].value = publicKey;
            $('input[name=encrypted_private_key]')[0].value = privateKey;
            $('input[name=generation_timestamp]')[0].value = endSeconds;
            $('input[name=generation_timespan]')[0].value = endSeconds - startSeconds;
            $(
              'input[name=browser_fingerprint]',
            )[0].value = `${navigator.userAgent} , (${screen.width} x ${screen.height})`;
            $('input[name=proc_email]')[0].value = procData.proc_email;
            $('#proc-keys-generation-form').submit();
          } else {
            const message = new Drupal.Message();
            message.add(`${Drupal.t(passwordError)}`, {type: 'error'});
            resetPasswordFields();
          }
        });

        // Disable submit button until password is strong enough and confirmed.
        new MutationObserver(function(mutationsList) {
          mutationsList.forEach(function(innerMutation) {
            $submit.prop('disabled', true);
            if (
              innerMutation.target.classList.contains('ok') &&
              document.querySelector('[data-drupal-selector="password-strength-text"]').innerText === 'Strong'
            ) {
              $submit.prop('disabled', false);
            }
          });
        }).observe(document.querySelector('#edit-password-confirm'), {
          subtree: true,
          childList: true,
        });
      });
    },
  };
})(jQuery, Drupal, once, drupalSettings, openpgp, navigator);
