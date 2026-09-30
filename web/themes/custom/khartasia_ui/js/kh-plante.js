(function (Drupal) {
  'use strict';

  // Onglets de langue (.kh-lang-tabs) — comportement indépendant de l'accordéon.
  Drupal.behaviors.khPlanteTabs = {
    attach: function (context, settings) {

      const container = context.querySelector('.kh-lang-tabs');
      if (!container) return;

      const tabs = container.querySelectorAll('.kh-lang-tab');
      const content = container.querySelector('.kh-lang-tabs__content');
      if (!tabs.length || !content) return;

      const rows = content.querySelectorAll('.views-row');
      if (!rows.length) return;

      rows.forEach(function(row) {
        const inner = row.querySelector('.kh-plante-texte[data-lang]');
        if (inner) {
          row.setAttribute('data-lang', inner.getAttribute('data-lang'));
        }
      });

      const available = new Set();
      rows.forEach(function(row) {
        const l = row.getAttribute('data-lang');
        if (l) available.add(l);
      });

      tabs.forEach(function(tab) {
        tab.style.display = available.has(tab.getAttribute('data-lang')) ? '' : 'none';
      });

      let defaultLang = null;
      for (const tab of tabs) {
        if (tab.style.display !== 'none') {
          defaultLang = tab.getAttribute('data-lang');
          tab.classList.add('active');
          break;
        }
      }

      rows.forEach(function(row) {
        row.style.display = (row.getAttribute('data-lang') === defaultLang) ? '' : 'none';
      });

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
    }
  };

  // Accordéon (.kh-accordion__trigger) — comportement séparé, ne dépend
  // d'aucun autre élément de la page. Auparavant dans la même fonction que
  // les onglets de langue : un retour anticipé de ceux-ci (absents sur les
  // pages utilisant l'accordéon) empêchait ce code de s'exécuter du tout.
  Drupal.behaviors.khPlanteAccordion = {
    attach: function (context, settings) {
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
