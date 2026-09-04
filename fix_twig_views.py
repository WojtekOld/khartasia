content = open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig').read()
content = content.replace(
    '{# drupal_view plante_papers - a configurer via UI #}',
    "{{ drupal_view('papers_lies', 'block_1', node.id) }}"
)
content = content.replace(
    '{# drupal_view plante_noms_communs - a configurer via UI #}',
    "{{ drupal_view('plante_noms_communs', 'block_1', node.id) }}"
)
open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig','w').write(content)
print('OK')