(function (Drupal) {
  'use strict';

  /**
   * Anime les chiffres de la section statistiques (.kh-stat__num) de 0
   * jusqu'à leur valeur réelle, déclenché quand le bloc devient visible
   * à l'écran plutôt qu'au chargement de la page.
   */
  Drupal.behaviors.khHomeCompteurs = {
    attach: function (context) {
      var chiffres = context.querySelectorAll('.kh-stat__num');
      if (!chiffres.length) return;

      var animer = function (element) {
        if (element._khAnime) return;
        element._khAnime = true;

        var cible = parseInt(element.textContent.trim(), 10);
        if (isNaN(cible)) return; // pas un nombre (ex. "Pays" reste "4" en dur, animé quand même s'il l'est)

        var duree = 1200;
        var debut = null;

        function etape(horodatage) {
          if (!debut) debut = horodatage;
          var progression = Math.min((horodatage - debut) / duree, 1);
          var valeur = Math.floor(progression * cible);
          element.textContent = valeur;
          if (progression < 1) {
            window.requestAnimationFrame(etape);
          } else {
            element.textContent = cible; // valeur exacte finale, pas d'arrondi manquant
          }
        }
        window.requestAnimationFrame(etape);
      };

      if ('IntersectionObserver' in window) {
        var observateur = new IntersectionObserver(function (entrees) {
          entrees.forEach(function (entree) {
            if (entree.isIntersecting) {
              animer(entree.target);
              observateur.unobserve(entree.target);
            }
          });
        }, { threshold: 0.5 });

        chiffres.forEach(function (el) { observateur.observe(el); });
      } else {
        // Repli sans IntersectionObserver : animation immédiate.
        chiffres.forEach(animer);
      }
    }
  };

}(Drupal));
