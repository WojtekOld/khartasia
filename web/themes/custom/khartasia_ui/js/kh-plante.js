(function (Drupal) {
  'use strict';

  Drupal.behaviors.khPlanteTabs = {
    attach: function (context, settings) {

      const container = context.querySelector('.kh-lang-tabs');
      if (!container) return;

      const tabs = container.querySelectorAll('.kh-lang-tab');
      const content = container.querySelector('.kh-lang-tabs__content');
      if (!tabs.length || !content) return;

      const rows = content.querySelectorAll('.views-row');
      if (!rows.length) return;

      // Lire data-lang depuis .kh-plante-texte enfant
      rows.forEach(function(row) {
        const inner = row.querySelector('.kh-plante-texte[data-lang]');
        if (inner) {
          row.setAttribute('data-lang', inner.getAttribute('data-lang'));
        }
      });

      // Langues disponibles
      const available = new Set();
      rows.forEach(function(row) {
        const l = row.getAttribute('data-lang');
        if (l) available.add(l);
      });

      // Masquer onglets sans contenu
      tabs.forEach(function(tab) {
        tab.style.display = available.has(tab.getAttribute('data-lang')) ? '' : 'none';
      });

      // Activer premier onglet visible
      let defaultLang = null;
      for (const tab of tabs) {
        if (tab.style.display !== 'none') {
          defaultLang = tab.getAttribute('data-lang');
          tab.classList.add('active');
          break;
        }
      }

      // Afficher langue par defaut
      rows.forEach(function(row) {
        row.style.display = (row.getAttribute('data-lang') === defaultLang) ? '' : 'none';
      });

      // Clics
      tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-lang');
          tabs.forEach(function(t) { t.classList.remove('active'); });
          this.classList.add('active');
          rows.forEach(function(row) {
            row.style.display = (row.getAttribute('data-lang') === target) ? '' : 'none';
          });
        });
      });

      // Accordeons
      context.querySelectorAll('.kh-accordion__trigger').forEach(function(trigger) {
        if (trigger._khInit) return;
        trigger._khInit = true;
        trigger.addEventListener('click', function() {
          const panel = this.nextElementSibling;
          const expanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', String(!expanded));
          const icon = this.querySelector('.kh-accordion__icon');
          if (icon) icon.textContent = expanded ? '+' : '-';
          expanded ? panel.setAttribute('hidden','') : panel.removeAttribute('hidden');
        });
      });
    }
  };

}(Drupal));
