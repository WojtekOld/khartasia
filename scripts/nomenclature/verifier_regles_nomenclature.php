<?php
/**
 * verifier_regles_nomenclature.php — vérification de bout en bout des deux règles de validation.
 *
 * LECTURE SEULE : aucun contenu n'est enregistré ; les contenus testés sont construits ou modifiés EN MÉMOIRE
 * puis abandonnés. À lancer sur le serveur APRÈS l'envoi des classes et le vidage des caches :
 *   cd /var/www/khartasia && vendor/bin/drush php-script verifier_regles_nomenclature.php
 *
 * DÉFINITIONS
 * validation : contrôle des règles d'un contenu (ici via entity->validate()), sans l'enregistrer.
 * violation : message d'erreur qu'une règle produit. On compte ici seulement celles de NOS deux règles.
 * cas témoin : cas qui DOIT violer la règle. S'il n'en produit pas, c'est la vérification qui est en défaut
 *   (règle non enregistrée, mauvaise classe, mauvais champ) : un vérificateur qui ne détecte jamais rien
 *   ne prouve rien (faux négatif).
 * clause de maintien : la règle ne s'applique qu'à un contenu nouveau, ou dont le point visé a changé.
 * non testé : cas pour lequel la base ne contient aucun contenu de ce genre ; ce n'est pas un échec.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\khartasia_core\Plugin\Validation\Constraint\AttestationConceptConstraint;
use Drupal\khartasia_core\Plugin\Validation\Constraint\NomSansPlanteConstraint;

$ok = 0; $ko = 0; $non_testes = 0;
$ligne = function ($reussi, $texte) use (&$ok, &$ko) { if ($reussi) { $ok++; echo "  ✓ $texte\n"; } else { $ko++; echo "  ✗ $texte\n"; } };
$compter = function ($noeud, $classe) { $n = 0; foreach ($noeud->validate() as $violation) { if ($violation->getConstraint() instanceof $classe) { $n++; } } return $n; };
$cas = function ($libelle, $noeud, $classe, $attendu) use ($compter, $ligne) { $obtenu = $compter($noeud, $classe); $ligne($obtenu === $attendu, "$libelle  [attendu $attendu, obtenu $obtenu]"); };
$saute = function ($libelle) use (&$non_testes) { $non_testes++; echo "  - non testé : $libelle (aucun contenu de ce genre dans la base)\n"; };

echo "=== Vérification des règles de nomenclature (lecture seule) ===\n";

echo "\nEnregistrement des règles :\n";
$contraintes = \Drupal::entityTypeManager()->getDefinition('node')->getConstraints();
$gestionnaire = \Drupal::service('validation.constraint');
foreach (['KhartasiaNomSansPlante', 'KhartasiaAttestationConcept'] as $id) {
  $ligne(array_key_exists($id, $contraintes), "$id enregistrée sur les nœuds");
  $ligne($gestionnaire->hasDefinition($id), "$id découverte par Drupal (plugin)");
}
$ligne(FieldConfig::loadByName('node', 'common_names', 'field_nom_portee') !== NULL, "champ field_nom_portee présent sur common_names");
$ligne(FieldConfig::loadByName('node', 'attestation', 'field_att_locateur') !== NULL, "champ field_att_locateur présent sur attestation");
if ($ko) { echo "\nLes règles ne sont pas en place : inutile de poursuivre. Vérifier l'envoi des fichiers et « drush cr ».\n"; return; }

$db = \Drupal::database();
$nom_sans_plante = $db->query("SELECT n.nid FROM {node_field_data} n LEFT JOIN {node__field_genus_com_entity} g ON g.entity_id = n.nid WHERE n.type = :t AND n.default_langcode = 1 AND g.entity_id IS NULL ORDER BY n.nid LIMIT 1", [':t' => 'common_names'])->fetchField();
$nom_avec_plante = $db->query("SELECT g.entity_id FROM {node__field_genus_com_entity} g WHERE g.bundle = :t ORDER BY g.entity_id LIMIT 1", [':t' => 'common_names'])->fetchField();
$att_sans_concept = $db->query("SELECT p.entity_id FROM {node__field_att_plante} p LEFT JOIN {node__field_att_concept_taxo} c ON c.entity_id = p.entity_id WHERE p.bundle = :b AND c.entity_id IS NULL ORDER BY p.entity_id LIMIT 1", [':b' => 'attestation'])->fetchField();
$att_avec_concept = $db->query("SELECT p.entity_id FROM {node__field_att_plante} p INNER JOIN {node__field_att_concept_taxo} c ON c.entity_id = p.entity_id WHERE p.bundle = :b ORDER BY p.entity_id LIMIT 1", [':b' => 'attestation'])->fetchField();
$plante = (int) $db->query("SELECT nid FROM {node_field_data} WHERE type = :t AND status = 1 AND default_langcode = 1 ORDER BY nid LIMIT 1", [':t' => 'article'])->fetchField();
echo "\nCas réels retenus : nom sans plante = " . ($nom_sans_plante ?: 'aucun') . " | nom avec plante = " . ($nom_avec_plante ?: 'aucun') . " | attestation de plante sans concept = " . ($att_sans_concept ?: 'aucune') . " | avec concept = " . ($att_avec_concept ?: 'aucune') . " | plante de test = $plante\n";

echo "\nRègle des noms (sans plante, il faut une portée) :\n";
$N = NomSansPlanteConstraint::class;
$cas("TÉMOIN nouveau nom sans plante ni portée : doit violer", Node::create(['type' => 'common_names', 'title' => 'essai']), $N, 1);
$cas("nouveau nom sans plante, portée « fonction » : accepté", Node::create(['type' => 'common_names', 'title' => 'essai', 'field_nom_portee' => 'fonction']), $N, 0);
$cas("TÉMOIN nouveau nom sans plante, portée « espece » : doit violer", Node::create(['type' => 'common_names', 'title' => 'essai', 'field_nom_portee' => 'espece']), $N, 1);
$cas("nouveau nom avec une plante : accepté", Node::create(['type' => 'common_names', 'title' => 'essai', 'field_genus_com_entity' => [['target_id' => $plante]]]), $N, 0);
if ($nom_sans_plante) {
  $cas("nom existant sans plante, inchangé (clause de maintien) : accepté", Node::load($nom_sans_plante), $N, 0);
  $n = Node::load($nom_sans_plante); $n->set('title', 'titre modifié en mémoire');
  $cas("nom existant sans plante, seul le titre change : accepté", $n, $N, 0);
  $n = Node::load($nom_sans_plante); $n->set('field_nom_portee', 'espece');
  $cas("TÉMOIN nom existant sans plante, portée changée en « espece » : doit violer", $n, $N, 1);
  $n = Node::load($nom_sans_plante); $n->set('field_nom_portee', 'fonction');
  $cas("nom existant sans plante, portée changée en « fonction » : accepté", $n, $N, 0);
} else { $saute("noms existants sans plante"); }
if ($nom_avec_plante) {
  $n = Node::load($nom_avec_plante); $n->set('field_genus_com_entity', []);
  $cas("TÉMOIN nom existant dont on retire la plante : doit violer", $n, $N, 1);
} else { $saute("noms existants avec plante"); }

echo "\nRègle des attestations (avec plante, il faut un concept taxonomique) :\n";
$A = AttestationConceptConstraint::class;
$cas("TÉMOIN nouvelle attestation liée à une plante, sans concept : doit violer", Node::create(['type' => 'attestation', 'title' => 'essai', 'field_att_plante' => [['target_id' => $plante]]]), $A, 1);
$cas("nouvelle attestation liée à une plante, concept « powo » : acceptée", Node::create(['type' => 'attestation', 'title' => 'essai', 'field_att_plante' => [['target_id' => $plante]], 'field_att_concept_taxo' => 'powo']), $A, 0);
$cas("nouvelle attestation sans plante, sans concept : acceptée", Node::create(['type' => 'attestation', 'title' => 'essai']), $A, 0);
if ($att_sans_concept) {
  $cas("attestation de plante existante sans concept, inchangée (clause de maintien) : acceptée", Node::load($att_sans_concept), $A, 0);
  $n = Node::load($att_sans_concept); $n->set('field_att_valeur', 'valeur modifiée en mémoire');
  $cas("attestation de plante existante sans concept, la valeur change : acceptée", $n, $A, 0);
  $actuelles = array_column(Node::load($att_sans_concept)->get('field_att_plante')->getValue(), 'target_id');
  $autre = ((int) ($actuelles[0] ?? 0) === $plante) ? $plante + 1 : $plante;
  $n = Node::load($att_sans_concept); $n->set('field_att_plante', [['target_id' => $autre]]);
  $cas("TÉMOIN attestation existante dont on change la plante (sans concept) : doit violer", $n, $A, 1);
  $n = Node::load($att_sans_concept); $n->set('field_att_concept_taxo', 'powo');
  $cas("attestation existante sans concept à laquelle on ajoute « powo » : acceptée", $n, $A, 0);
} else { $saute("attestations de plante sans concept"); }
if ($att_avec_concept) {
  $n = Node::load($att_avec_concept); $n->set('field_att_concept_taxo', []);
  $cas("TÉMOIN attestation de plante dont on vide un concept déjà rempli : doit violer", $n, $A, 1);
} else { $saute("attestations de plante avec concept"); }

echo "\nrésultat : $ok conformes | $ko en défaut | $non_testes non testés\n";
echo $ko ? "\n✗ AU MOINS UNE VÉRIFICATION EN DÉFAUT : ne pas considérer les règles comme fiables.\n" : "\n✓ Les règles se comportent comme prévu. (Rien n'a été enregistré.)\n";
