import re

path = '/var/www/html/web/themes/custom/khartasia_ui/templates/node/node--article.html.twig'
content = open(path).read()

old1 = "{{ drupal_view('papers_lies', 'block_1', node.id) }}"
new1 = "{% for paper in papers_lies %}<div class='kh-paper-item'><a href='{{ paper.url }}'>{{ paper.titre }}</a>{% if paper.origin %} - {{ paper.origin }}{% endif %}</div>{% endfor %}"

old2 = "{{ drupal_view('plante_noms_communs', 'block_1', node.field_tax_genus.target_id) }}"
new2 = "{% if noms_communs %}<table class='kh-noms-table'><thead><tr><th>Nom</th><th>Langue</th><th>Ecriture locale</th></tr></thead><tbody>{% for nom in noms_communs %}<tr><td><a href='{{ nom.url }}'>{{ nom.titre }}</a></td><td>{{ nom.langue }}</td><td>{{ nom.scripture }}</td></tr>{% endfor %}</tbody></table>{% endif %}"

content = content.replace(old1, new1)
content = content.replace(old2, new2)
open(path, 'w').write(content)
print('OK - remplacements effectues')
print('papers_lies present:', 'papers_lies' in content)
print('noms_communs present:', 'noms_communs' in content)
