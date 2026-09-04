content = """{#
  node--article.html.twig — Fiche plante Khartasia
#}
{{ attach_library('khartasia_ui/kh-plante') }}
<article class="kh-plante">
  <header class="kh-plante__header">
    <div class="kh-plante__header-left">
      <h1 class="kh-plante__title">{{ node.label }}</h1>
      {% if content.field_synonymes_bot|render %}<div class="kh-plante__synonymes">{{ content.field_synonymes_bot }}</div>{% endif %}
    </div>
    {% if content.field_image|render %}<div class="kh-plante__header-img">{{ content.field_image }}</div>{% endif %}
  </header>
  <div class="kh-plante__body">
    <div class="kh-plante__main">
      {% if content.body|render %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Description</h2>
        {{ content.body }}
      </section>
      {% endif %}
      {% if content.field_gen_culture|render %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Culture et usage</h2>
        {{ content.field_gen_culture }}
      </section>
      {% endif %}
      {% if content.field_aire_croissance|render %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Aire de croissance</h2>
        {{ content.field_aire_croissance }}
      </section>
      {% endif %}
      {% if content.field_aire_utilis|render %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Utilisation dans la fabrication</h2>
        {{ content.field_aire_utilis }}
      </section>
      {% endif %}
      {% if content.field_zones_geo|render %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Zones de production</h2>
        {{ content.field_zones_geo }}
      </section>
      {% endif %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Papers</h2>
        {{ drupal_view('plante_papers', 'block_papers', node.id) }}
      </section>
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Noms communs</h2>
        {{ drupal_view('plante_noms_communs', 'block_noms_communs', node.id) }}
      </section>
      {% if content.field_image_gallery_1|render %}
      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Galerie</h2>
        {{ content.field_image_gallery_1 }}
      </section>
      {% endif %}
    </div>
    <aside class="kh-plante__aside">
      <div class="kh-aside__card">
        <h3 class="kh-aside__title">Classification</h3>
        <dl class="kh-aside__dl">
          {% if content.field_tax_order|render %}<dt>Ordre</dt><dd>{{ content.field_tax_order }}</dd>{% endif %}
          {% if content.field_tax_family|render %}<dt>Famille</dt><dd>{{ content.field_tax_family }}</dd>{% endif %}
          {% if content.field_tax_genus|render %}<dt>Genre</dt><dd>{{ content.field_tax_genus }}</dd>{% endif %}
          {% if content.field_used_part_plant|render %}<dt>Partie utilisee</dt><dd>{{ content.field_used_part_plant }}</dd>{% endif %}
          {% if content.field_use_paper_making|render %}<dt>Usage papetier</dt><dd>{{ content.field_use_paper_making }}</dd>{% endif %}
        </dl>
      </div>
      {% if content.field_fibres|render %}
      <div class="kh-aside__card kh-aside__card--fibres">
        <h3 class="kh-aside__title">Identification des fibres</h3>
        {{ content.field_fibres }}
      </div>
      {% endif %}
      {% if content.field_tags|render %}
      <div class="kh-aside__card">
        <h3 class="kh-aside__title">Etiquettes</h3>
        {{ content.field_tags }}
      </div>
      {% endif %}
    </aside>
  </div>
</article>"""
open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig','w',encoding='utf-8').write(content)
print('OK')