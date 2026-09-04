/**
 * @file
 * Remove cached password.
 */

/**
 * Removes all session-stored password cache entries for the current user.
 *
 * @returns {void}
 */
export function removeCachedPassword() {
  sessionStorage.removeItem(`proc.password_cache.key_user_id.${drupalSettings.user.uid}`);
  sessionStorage.removeItem(`proc.password_cache_type.key_user_id.${drupalSettings.user.uid}`);
}
