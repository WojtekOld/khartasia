<?php
$node = \Drupal\node\Entity\Node::load(4564);
echo "Nœud 4564 existe : " . ($node ? "OUI, titre=" . $node->label() : "NON") . "\n";

echo "\n=== Alias de chemin pour ce nœud ===\n";
$path_alias_manager = \Drupal::service('path_alias.manager');
$alias = $path_alias_manager->getAliasByPath('/node/4564');
echo "Alias : $alias\n";

echo "\n=== Ce nœud a-t-il un alias qui ressemble à la racine ? ===\n";
$db = \Drupal::database();
$rows = $db->query("SELECT * FROM path_alias WHERE path = '/node/4564'")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n=== Requête directe Drupal::request pour /node/4564 ===\n";
$request = \Symfony\Component\HttpFoundation\Request::create('/node/4564');
$path = \Drupal::service('path.current')->getPath($request);
echo "Chemin résolu en interne : $path\n";
