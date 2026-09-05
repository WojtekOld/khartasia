<?php
/**
 * @file
 * Création des vues simplifiées pour les papiers et noms communs.
 * 
 * Usage: ddev drush scr scripts/create_views_simple.php
 */

use Drupal\views\Entity\View;

// Vérifier que le module views est activé
if (!\Drupal::moduleHandler()->moduleExists('views')) {
    echo "❌ Le module Views n'est pas activé.\n";
    exit(1);
}

echo "=== CRÉATION DES VUES ===\n\n";

// Vue 1 : Papiers liés à une plante
try {
    // Supprimer si existe
    if ($view = View::load('plante_papiers_lies')) {
        $view->delete();
        echo "🗑️  Vue plante_papiers_lies supprimée\n";
    }
    
    // Créer la vue
    $view = View::create([
        'id' => 'plante_papiers_lies',
        'label' => 'Papiers liés à une plante',
        'module' => 'views',
        'description' => 'Liste des papiers associés à une plante',
        'tag' => 'khartasia',
        'base_table' => 'node_field_data',
        'base_field' => 'nid',
        'status' => TRUE,
    ]);
    
    $view->addDisplay('page', 'Page', 'page_1');
    $view->addDisplay('block', 'Block', 'block_1');
    
    $view->save();
    echo "✅ Vue plante_papiers_lies créée\n";
    
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";