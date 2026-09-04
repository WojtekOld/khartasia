content = open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig').read()

old_papers = """      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Papers</h2>
        {{ drupal_view('papers_lies', 'block_1', node.id) }}
      </section>"""

new_papers = """      <section class="kh-plante__section">
        <div class="kh-accordion">
          <div class="kh-accordion__item">
            <button class="kh-accordion__trigger" aria-expanded="false">
              <span>Papers</span>
              <span class="kh-accordion__icon" aria-hidden="true">+</span>
            </button>
            <div class="kh-accordion__panel" hidden>
              {{ drupal_view('papers_lies', 'block_1', node.id) }}
            </div>
          </div>
        </div>
      </section>"""

old_noms = """      <section class="kh-plante__section">
        <h2 class="kh-plante__section-title">Noms communs</h2>
        {{ drupal_view('plante_noms_communs', 'block_1', node.id) }}
      </section>"""

new_noms = """      <section class="kh-plante__section">
        <div class="kh-accordion">
          <div class="kh-accordion__item">
            <button class="kh-accordion__trigger" aria-expanded="false">
              <span>Noms communs</span>
              <span class="kh-accordion__icon" aria-hidden="true">+</span>
            </button>
            <div class="kh-accordion__panel" hidden>
              {{ drupal_view('plante_noms_communs', 'block_1', node.id) }}
            </div>
          </div>
        </div>
      </section>"""

content = content.replace(old_papers, new_papers)
content = content.replace(old_noms, new_noms)
open('web/themes/custom/khartasia_ui/templates/node/node--article.html.twig','w').write(content)
print('OK')