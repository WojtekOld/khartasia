<?php

namespace Drupal\khartasia_guided_forms\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\khartasia_guided_forms\Controller\SaisieGuideeController;

/**
 * Bloc d'accès à la page « Saisie guidée » (à placer sur la page d'accueil : Structure > Mise en page des blocs).
 * Visible seulement pour les utilisateurs qui peuvent saisir.
 */
#[Block(
  id: 'khartasia_saisie_guidee',
  admin_label: new TranslatableMarkup('Saisie guidée (accès)'),
  category: new TranslatableMarkup('Khartasia'),
)]
class SaisieGuideeBlock extends BlockBase {

  public function build() {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['khartasia-saisie-bloc']],
      'texte' => ['#markup' => '<p>' . $this->t('Papiers, plantes, matériaux, attestations : choisissez le bon formulaire.') . '</p>'],
      'lien' => [
        '#type' => 'link',
        '#title' => $this->t('Saisie guidée'),
        '#url' => Url::fromRoute('khartasia_guided_forms.saisie_guidee'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
  }

  protected function blockAccess(AccountInterface $account) {
    return SaisieGuideeController::access($account);
  }

  public function getCacheContexts() {
    return ['user.permissions'];
  }

}
