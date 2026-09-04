content = open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig').read()

replacements = [
    ('{{ content.body }}', '{% if node.body.value %}<div class="field__item">{{ node.body.value|raw }}</div>{% endif %}'),
    ('{{ content.field_gen_culture }}', '{% if node.field_gen_culture.value %}<div class="field__item">{{ node.field_gen_culture.value|raw }}</div>{% endif %}'),
    ('{{ content.field_aire_croissance }}', '{% if node.field_aire_croissance.value %}<div class="field__item">{{ node.field_aire_croissance.value|raw }}</div>{% endif %}'),
    ('{{ content.field_aire_utilis }}', '{% if node.field_aire_utilis.value %}<div class="field__item">{{ node.field_aire_utilis.value|raw }}</div>{% endif %}'),
    ('{{ content.field_synonymes_bot }}', '{% if node.field_synonymes_bot.value %}<span>{{ node.field_synonymes_bot.value|raw }}</span>{% endif %}'),
]

for old, new in replacements:
    if old in content:
        content = content.replace(old, new)
        print('OK: ' + old[:40])
    else:
        print('NON TROUVE: ' + old[:40])

open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig','w').write(content)
print('Done')
