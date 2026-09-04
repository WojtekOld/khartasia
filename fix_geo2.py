lines = open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig').readlines()
new_lines = []
skip = False
for i, line in enumerate(lines):
    if '{% if content.field_zones_geo|render %}' in line:
        skip = True
        new_lines.append('      {% if content.field_zones_geo|render %}\n')
        new_lines.append('      <section class="kh-plante__section kh-plante__section--geo">\n')
        new_lines.append('        <h2 class="kh-plante__section-title">Zones de production</h2>\n')
        new_lines.append('        <div class="kh-accordion">\n')
        new_lines.append('          {% for item in node.field_zones_geo %}\n')
        new_lines.append('            {% set geo = item.entity %}\n')
        new_lines.append('            {% if geo %}\n')
        new_lines.append('              {% set pays = geo.field_pg_pays.entity %}\n')
        new_lines.append('              <div class="kh-accordion__item">\n')
        new_lines.append('                <button class="kh-accordion__trigger" aria-expanded="false">\n')
        new_lines.append('                  <span>{{ pays ? pays.label : geo.label }}</span>\n')
        new_lines.append('                  <span class="kh-accordion__icon" aria-hidden="true">+</span>\n')
        new_lines.append('                </button>\n')
        new_lines.append('                <div class="kh-accordion__panel" hidden>\n')
        new_lines.append('                  {{ drupal_entity("node", geo.id, "embedded") }}\n')
        new_lines.append('                </div>\n')
        new_lines.append('              </div>\n')
        new_lines.append('            {% endif %}\n')
        new_lines.append('          {% endfor %}\n')
        new_lines.append('        </div>\n')
        new_lines.append('      </section>\n')
        new_lines.append('      {% endif %}\n')
    elif skip and '{% endif %}' in line and 'field_zones_geo' not in line:
        skip = False
    elif not skip:
        new_lines.append(line)

open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig','w').writelines(new_lines)
print('OK - ' + str(len(new_lines)) + ' lines')