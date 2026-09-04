content = open('web/themes/custom/khartasia_ui/templates/node/node--plante-geo--embedded.html.twig').read()

replacements = [
    ('{{ content.field_pg_utilisation }}', '{% if node.field_pg_utilisation.value %}<div class="field__item">{{ node.field_pg_utilisation.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pg_culture }}', '{% if node.field_pg_culture.value %}<div class="field__item">{{ node.field_pg_culture.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pg_intro }}', '{% if node.field_pg_intro.value %}<div class="field__item">{{ node.field_pg_intro.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pg_preparation }}', '{% if node.field_pg_preparation.value %}<div class="field__item">{{ node.field_pg_preparation.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pg_source }}', '{% if node.field_pg_source.value %}<span>{{ node.field_pg_source.value }}</span>{% endif %}'),
]
for old, new in replacements:
    if old in content:
        content = content.replace(old, new)
        print('OK geo: ' + old[:40])
open('web/themes/custom/khartasia_ui/templates/node/node--plante-geo--embedded.html.twig','w').write(content)

# Corriger node--plante-fibre--embedded -- supprimer titre et auteur
fibre = open('web/themes/custom/khartasia_ui/templates/node/node--plante-fibre--embedded.html.twig').read()
replacements2 = [
    ('{{ content.field_pf_type }}', '{% if node.field_pf_type.value %}<dd>{{ node.field_pf_type.value }}</dd>{% endif %}'),
    ('{{ content.field_pf_long_min }}', '{{ node.field_pf_long_min.value }}'),
    ('{{ content.field_pf_long_max }}', '{{ node.field_pf_long_max.value }}'),
    ('{{ content.field_pf_larg_min }}', '{{ node.field_pf_larg_min.value }}'),
    ('{{ content.field_pf_larg_max }}', '{{ node.field_pf_larg_max.value }}'),
    ('{{ content.field_pf_herzberg }}', '{% if node.field_pf_herzberg.value %}<dd>{{ node.field_pf_herzberg.value }}</dd>{% endif %}'),
    ('{{ content.field_pf_graff_c }}', '{% if node.field_pf_graff_c.value %}<dd>{{ node.field_pf_graff_c.value }}</dd>{% endif %}'),
    ('{{ content.field_pf_extremites }}', '{% if node.field_pf_extremites.value %}<div class="field__item">{{ node.field_pf_extremites.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pf_striations }}', '{% if node.field_pf_striations.value %}<div class="field__item">{{ node.field_pf_striations.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pf_cellules }}', '{% if node.field_pf_cellules.value %}<div class="field__item">{{ node.field_pf_cellules.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pf_particularites }}', '{% if node.field_pf_particularites.value %}<div class="field__item">{{ node.field_pf_particularites.value|raw }}</div>{% endif %}'),
    ('{{ content.field_pf_notes }}', '{% if node.field_pf_notes.value %}<div class="field__item">{{ node.field_pf_notes.value }}</div>{% endif %}'),
]
for old, new in replacements2:
    if old in fibre:
        fibre = fibre.replace(old, new)
        print('OK fibre: ' + old[:40])
open('web/themes/custom/khartasia_ui/templates/node/node--plante-fibre--embedded.html.twig','w').write(fibre)
print('Done')
