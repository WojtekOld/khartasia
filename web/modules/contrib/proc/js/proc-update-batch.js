/**
 * @file
 * Batch updates cipher texts.
 */

import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { initPasswordCaching } from './modules/atomics/proc-init-password-caching-module.js';
import { triggerCacheDecryption } from './modules/proc-trigger-cache-decryption-module.js';
import { resetPasswordField } from './modules/atomics/proc-reset-password-field-module.js';
import { decryptHandler } from './modules/proc-decrypt-handler-module.js';
import { fetchWithCsrf } from './modules/atomics/proc-csrf-token-module.js';

(function($, Drupal, drupalSettings, once, openpgp) {
  Drupal.behaviors.ProcBehavior = {
    async attach(context, settings) {
      const messages = new Drupal.Message();
      const updateLink = $('#update-batch-link');
      // Only proceed if Blob is supported.
      if (!window.Blob) {
        messages.add(drupalSettings.proc.proc_labels.proc_fileapi_err_msg, {
          type: 'error',
        });
        return;
      }
      initPasswordCaching();
      await triggerCacheDecryption($, openpgp, getPassSessionCachingSalt());
      $(once('on', 'input#edit-password', context)).on('focusin', function () {
        resetPasswordField($, messages, updateLink);
      });
      let currentProgress = 0;

      const handleUpdateClick = async function () {
        // Disable the link to prevent multiple clicks.
        updateLink.off('click').css('pointer-events', 'none').addClass('is-disabled');
        // Make the link look like disabled:
        updateLink.css({
          'opacity': '0.6',
          'cursor': 'not-allowed',
        });

        const url = new URL(window.location.origin + drupalSettings.path.baseUrl + `api/proc/getpubkey/${drupalSettings.user.uid}/user_id`);
        url.searchParams.set('cache_bust', `${Date.now()}_${Math.floor(Math.random() * 1000000)}`);
        const response = await fetchWithCsrf(url.toString(), {
          method: 'GET',
        });
        let data = await response.json();
        let update_jobs = data.pubkey[0]['update_jobs'];

        // Normalize update_jobs to ensure serial indexes without gaps.
        if (update_jobs && typeof update_jobs === 'object') {
          update_jobs = Object.values(update_jobs).filter(job => job != null);
          console.info('Normalized update_jobs to ensure it is an array with sequential indexes.');
        }

        // Ensure iterability and avoid division-by-zero.
        if (!Array.isArray(update_jobs) || update_jobs.length === 0) {
          messages.add('No update jobs found.', { type: 'warning' });
          location.reload();
          return;
        }

        let processedCount = 0;
        let updatedCount = 0;
        const maxFilesUpdate = drupalSettings.proc.proc_max_files_update;
        const jobsToProcess = update_jobs.slice(0, maxFilesUpdate);
        const totalJobs = jobsToProcess.length;

        try {
          for (const update_job of jobsToProcess) {
            drupalSettings.proc.proc_ids = [update_job];
            try {
              await decryptHandler($, updateLink);
              await new Promise(resolve => setTimeout(resolve, 1000));
              updatedCount++;
              console.info(`Re-encrypted proc with ID ${update_job}`);
            } catch (err) {
              console.error(`Failed to re-encrypt proc ${update_job}:`, err);
              messages.add(`Failed to re-encrypt proc with ID ${update_job}`, { type: 'error' });
            }

            processedCount++;
            const progressBar = $('#proc-update-progress');
            const progressBarInfo = $('#proc-update-progress-info');

            // Deterministic progress, no float accumulation drift.
            currentProgress = Math.min((processedCount / totalJobs) * 100, 100);
            progressBar.attr('value', currentProgress);

            const percent = Math.round((updatedCount / totalJobs) * 100);
            progressBarInfo.text(`Updated ${updatedCount} of ${totalJobs} ( ${percent}% )`);
          }
        } catch (err) {
          console.error('Error iterating update_jobs:', err);
          messages.add('Failed to iterate update jobs. Please refresh the page and try again.', { type: 'error' });
        } finally {
          location.reload();
        }
      };
      updateLink.off().on('click', handleUpdateClick);
      currentProgress = 0;

    },
  };
})(jQuery, Drupal, drupalSettings, once, openpgp);
