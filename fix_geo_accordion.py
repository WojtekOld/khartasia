content = open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig').read()

old = """      {% if content.field_zones_geo|render %}
      <section class="kh-plante__section kh-plante__section--geo">
        <h2 class="kh-plante__section-title">Zones de production</h2>
        <div class="kh-accordion">
          {{ content.field_zones_geo }}
        </div>
      </section>
      {% endif %}"""

new = """      {% set geo_items = drupal_field('field_zones_geo', 'node', node.id, 'full') %}
      {% if content.field_zones_geo|render %}
      <section class="kh-plante__section kh-plante__section--geo">
        <h2 class="kh-plante__section-title">Zones de production</h2>
        <div class="kh-accordion">
          {% for item in node.field_zones_geo %}
            {% set geo = item.entity %}
            {% if geo %}
              {% set pays = geo.field_pg_pays.entity %}
              <div class="kh-accordion__item">
                <button class="kh-accordion__trigger" aria-expanded="false">
                  <span>{{ pays ? pays.label : geo.label }}</span>
                  <span class="kh-accordion__icon" aria-hidden="true">+</span>
                </button>
                <div class="kh-accordion__panel" hidden>
                  {{ drupal_entity('node', geo.id, 'embedded') }}
                </div>
              </div>
            {% endif %}
          {% endfor %}
        </div>
      </section>
      {% endif %}"""

content = content.replace(old, new)
open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig','w').write(content)
print('OK - zones geo accordion')