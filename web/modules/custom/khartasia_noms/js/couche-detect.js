/**
 * @file
 * Heuristique de couche (miroir client de detect_couche() PHP, F3/F8).
 *
 * L1 : ne coche JAMAIS la radio automatiquement — suggestion visuelle
 *      seulement (doctrine Wallpaper Canton P1 : "jamais de pré-coche").
 * L2 : déclenchement au blur, pas à chaque frappe — même granularité que
 *      le binding #ajax côté serveur (event: 'change').
 * L3 : la valeur suggérée part dans un champ caché ; c'est submitForm()
 *      côté PHP qui journalise suggestion vs valeur retenue, jamais ce JS.
 */
((Drupal, once) => {
  const HAN = /\p{Script=Han}/u;

  Drupal.behaviors.khartasiaCouche = {
    attach(context) {
      once('khartasia-couche', '[data-drupal-selector="edit-valeur"]', context).forEach((input) => {
        input.addEventListener('blur', () => {
          const value = input.value.trim();
          if (!value) return;

          const suggestion = HAN.test(value) ? 'graphie' : 'romanisation';
          const form = input.form;
          if (!form) return;

          // Retire une suggestion précédente avant d'en poser une nouvelle.
          form.querySelectorAll('[data-couche-suggestion]').forEach((el) => el.remove());

          const alreadyChecked = form.querySelector('input[name="couche"]:checked');
          if (!alreadyChecked) {
            const radio = form.querySelector(`input[name="couche"][value="${suggestion}"]`);
            const label = radio ? radio.closest('label') : null;
            if (label) {
              const hint = document.createElement('span');
              hint.dataset.coucheSuggestion = '1';
              hint.className = 'khartasia-suggestion';
              hint.textContent = ' (suggestion : cliquez pour confirmer)';
              label.appendChild(hint);
            }
          }

          // L3 : valeur suggérée transmise pour journalisation serveur
          // uniquement — jamais utilisée ici pour décider quoi que ce soit.
          let hidden = form.querySelector('input[name="couche_suggeree"]');
          if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'couche_suggeree';
            form.appendChild(hidden);
          }
          hidden.value = suggestion;
        });
      });
    },
  };
})(Drupal, once);
