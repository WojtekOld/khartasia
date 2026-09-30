(function (Drupal) {
  'use strict';

  /**
   * Bloque le clic droit pour les visiteurs non connectés uniquement —
   * la sélection de texte est déjà désactivée en pur CSS (voir
   * kh-protection.css, ciblant body:not(.user-logged-in)).
   */
  Drupal.behaviors.khProtectionClicDroit = {
    attach: function (context) {
      if (context !== document) return;
      if (document.body.classList.contains('user-logged-in')) return;

      document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
      });
    }
  };

}(Drupal));
