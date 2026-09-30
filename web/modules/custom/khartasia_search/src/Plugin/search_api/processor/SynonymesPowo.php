<?php

namespace Drupal\khartasia_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Ajoute les synonymes botaniques POWO comme texte indexé.
 *
 * @SearchApiProcessor(
 *   id = "khartasia_synonymes_powo",
 *   label = @Translation("Synonymes botaniques (POWO)"),
 *   description = @Translation("Ajoute le texte des synonymes POWO liés au genre de la plante, pour que la recherche plein texte les trouve."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */
class SynonymesPowo extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];
    if (!$datasource || $datasource->getPluginId() === 'entity:node') {
      $definition = [
        'label' => $this->t('Synonymes botaniques (POWO)'),
        'description' => $this->t('Texte agrégé des synonymes POWO pour la recherche plein texte.'),
        'type' => 'text',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['khartasia_synonymes_powo'] = new ProcessorProperty($definition);
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    if (!($node instanceof Node) || $node->bundle() !== 'article') {
      return;
    }
    if (!$node->hasField('field_tax_genus') || $node->get('field_tax_genus')->isEmpty()) {
      return;
    }
    $genus_tid = $node->get('field_tax_genus')->target_id;

    $db = \Drupal::database();
    $syn_tids = $db->query("
      SELECT entity_id FROM taxonomy_term__field_syn_nom_accepte
      WHERE field_syn_nom_accepte_target_id = :tid
    ", [':tid' => $genus_tid])->fetchCol();

    if (!$syn_tids) {
      return;
    }

    $noms = [];
    foreach (Term::loadMultiple($syn_tids) as $syn) {
      $noms[] = $syn->label();
    }
    $texte = implode('. ', $noms);

    // Corrigé : 'entity:node' au lieu de NULL, pour correspondre au
    // datasource_id réellement défini dans field_settings.
    $fields = $this->getFieldsHelper()->filterForPropertyPath(
      $item->getFields(),
      'entity:node',
      'khartasia_synonymes_powo'
    );
    foreach ($fields as $field) {
      $field->addValue($texte);
    }
  }

}
