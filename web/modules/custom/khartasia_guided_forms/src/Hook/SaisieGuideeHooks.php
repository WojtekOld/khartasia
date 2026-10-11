<?php

namespace Drupal\khartasia_guided_forms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\LocalActionDefault;

/**
 * Hooks de la page « Saisie guidée » (par attribut : aucun fichier à fusionner).
 *  - theme : gabarit khartasia-saisie-guidee ;
 *  - library_info_build : feuille de style de la page ;
 *  - menu_local_actions_alter : bouton « Saisie guidée » sur la page /node/add ;
 *  - preprocess_page : variable `saisie_guidee_url` pour la navigation codée en Twig.
 */
class SaisieGuideeHooks {

  private function chemin(): string {
    return \Drupal::service('extension.list.module')->getPath('khartasia_guided_forms');
  }

  #[Hook('theme')]
  public function theme(): array {
    return [
      'khartasia_saisie_guidee' => [
        'variables' => ['vue' => 'diagramme', 'liens' => [], 'groupes' => [], 'selecteur' => NULL,
          'url_diagramme' => '', 'url_liste' => '', 'regles' => []],
        'template' => 'khartasia-saisie-guidee',
        'path' => $this->chemin() . '/templates',
      ],
    ];
  }

  #[Hook('library_info_build')]
  public function libraries(): array {
    return ['saisie_guidee' => ['css' => ['theme' => ['/' . $this->chemin() . '/css/saisie-guidee.css' => []]]]];
  }

  #[Hook('menu_local_actions_alter')]
  public function actionsLocales(array &$local_actions): void {
    $local_actions['khartasia_guided_forms.saisie_guidee_action'] = [
      'id' => 'khartasia_guided_forms.saisie_guidee_action',
      'class' => LocalActionDefault::class,
      'route_name' => 'khartasia_guided_forms.saisie_guidee',
      'route_parameters' => [],
      'title' => t('Saisie guidée'),
      'appears_on' => ['node.add_page'],
      'options' => [],
      'weight' => -10,
      'provider' => 'khartasia_guided_forms',
    ];
  }

  /**
   * Expose l'URL de la page dans tous les gabarits page.html.twig, uniquement
   * si l'utilisateur courant y a accès (sinon la variable est absente).
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $url = \Drupal\Core\Url::fromRoute('khartasia_guided_forms.saisie_guidee');
    if ($url->access()) {
      $variables['saisie_guidee_url'] = $url->toString();
    }
    $variables['#cache']['contexts'][] = 'user.permissions';
  }

}
