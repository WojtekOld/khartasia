<?php
/**
 * installer_regles_nomenclature.php — installe les deux règles de validation de la nomenclature dans le module khartasia_core.
 *
 * Usage, depuis la racine du projet :
 *   ddev exec php installer_regles_nomenclature.php              (simulation : n'écrit rien)
 *   ddev exec php installer_regles_nomenclature.php --appliquer  (écrit, après sauvegarde)
 *
 * Ce qu'il fait : crée quatre classes dans web/modules/custom/khartasia_core/src/Plugin/Validation/Constraint/
 * et ajoute à khartasia_core.module la fonction khartasia_core_entity_type_alter(), qui enregistre les deux règles sur les nœuds.
 *
 * DÉFINITIONS
 * contrainte de validation : règle que Drupal vérifie quand on enregistre un contenu dans un formulaire (ou quand un script
 *   appelle validate()). Elle est portée par une classe « contrainte » et une classe « validateur ».
 * clause de maintien : la règle ne s'applique qu'à un contenu nouveau, ou dont le point visé a changé ; les contenus existants
 *   ne deviennent pas invalides.
 * hook (hook_entity_type_alter) : fonction que Drupal appelle à un moment précis ; celle-ci ajoute les règles au type « nœud ».
 * simulation / garde / idempotent / BOM / fin de ligne : voir appliquer_filtre_publication.php.
 */

$appliquer = in_array('--appliquer', $argv ?? [], TRUE);
$module = 'web/modules/custom/khartasia_core/khartasia_core.module';
$dossier = 'web/modules/custom/khartasia_core/src/Plugin/Validation/Constraint';
$dossier_sauvegardes = '_sauvegardes_locales';

$classes = [];
$classes['NomSansPlanteConstraint'] = <<<'KH_FIN'
<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règle : un nom vernaculaire sans plante liée doit dire à quoi il se rapporte.
 *
 * DÉFINITIONS
 * contrainte de validation : règle que Drupal vérifie quand on enregistre un contenu dans un formulaire
 *   (ou quand un script appelle explicitement validate()). Un simple save() ne la vérifie pas.
 * portée : à quoi se rapporte un nom qui n'a pas de plante liée (champ field_nom_portee) : taxon supérieur,
 *   appellatif générique (une fonction), ou « sans référent papetier » (faux nom). « Espèce » ne suffit pas :
 *   il signifie qu'une fiche Plante existe.
 * clause de maintien : la règle ne s'applique qu'à un nom nouveau, ou dont le lien vers la plante ou la portée
 *   a changé ; un nom existant sans plante peut donc être retouché sans qu'on lui réclame ce qu'il n'a jamais eu.
 */
#[Constraint(
  id: 'KhartasiaNomSansPlante',
  label: new TranslatableMarkup('Nom vernaculaire sans plante ni portée', [], ['context' => 'Validation']),
  type: ['entity']
)]
class NomSansPlanteConstraint extends SymfonyConstraint {

  /**
   * Message affiché sur le champ « Portée du nom ».
   */
  public string $message = 'Un nom vernaculaire sans plante liée doit préciser sa portée : taxon supérieur, appellatif générique ou sans référent papetier.';

}
KH_FIN;

$classes['NomSansPlanteConstraintValidator'] = <<<'KH_FIN'
<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide la règle « un nom vernaculaire sans plante liée doit préciser sa portée ».
 *
 * Voir NomSansPlanteConstraint pour les définitions (contrainte, portée, clause de maintien).
 */
class NomSansPlanteConstraintValidator extends ConstraintValidator {

  /**
   * Portées qui justifient l'absence de plante (« espece » n'en fait pas partie).
   */
  const PORTEES_VALIDES = ['taxon_superieur', 'fonction', 'sans_referent_papetier'];

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof FieldableEntityInterface || $entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'common_names') {
      return;
    }
    if (!$entity->hasField('field_genus_com_entity') || !$entity->hasField('field_nom_portee')) {
      return;
    }

    $plantes = $this->cibles($entity, 'field_genus_com_entity');
    $portee = $this->valeur($entity, 'field_nom_portee');

    // Règle satisfaite : une plante est liée, ou la portée explique son absence.
    if ($plantes || in_array($portee, self::PORTEES_VALIDES, TRUE)) {
      return;
    }

    // Clause de maintien : un nom existant dont ni le lien ni la portée n'ont changé n'est pas concerné.
    $original = $this->original($entity);
    if ($original
      && $this->cibles($original, 'field_genus_com_entity') === $plantes
      && $this->valeur($original, 'field_nom_portee') === $portee) {
      return;
    }

    $this->context->buildViolation($constraint->message)
      ->atPath('field_nom_portee')
      ->addViolation();
  }

  /**
   * Identifiants cibles d'un champ de référence, triés (ordre sans importance).
   */
  private function cibles(FieldableEntityInterface $entite, string $champ): array {
    $ids = [];
    foreach ($entite->get($champ)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    sort($ids);
    return $ids;
  }

  /**
   * Valeur d'un champ simple, chaîne vide si absente.
   */
  private function valeur(FieldableEntityInterface $entite, string $champ): string {
    $item = $entite->get($champ)->getValue();
    return isset($item[0]['value']) ? (string) $item[0]['value'] : '';
  }

  /**
   * Version enregistrée du contenu (NULL pour un contenu nouveau), dans la langue du contenu validé.
   */
  private function original(FieldableEntityInterface $entite) {
    if ($entite->isNew() || !$entite->id()) {
      return NULL;
    }
    $original = \Drupal::entityTypeManager()->getStorage($entite->getEntityTypeId())->loadUnchanged($entite->id());
    if (!$original) {
      return NULL;
    }
    $langue = $entite->language()->getId();
    return $original->hasTranslation($langue) ? $original->getTranslation($langue) : $original;
  }

}
KH_FIN;

$classes['AttestationConceptConstraint'] = <<<'KH_FIN'
<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règle : une attestation liée à une plante doit indiquer le référentiel taxonomique suivi.
 *
 * DÉFINITIONS
 * concept taxonomique (field_att_concept_taxo) : le référentiel « sensu » (au sens de) qui fixe les limites du
 *   taxon : POWO, GRIN, Flora of China ou autre. Ces référentiels peuvent délimiter un taxon différemment.
 * clause de maintien : la règle ne s'applique qu'à une attestation nouvelle, ou dont le lien vers la plante a
 *   changé, ou dont le concept déjà renseigné est vidé ; les attestations de plante existantes sans concept
 *   (par exemple les caractérisations du CRCC) ne deviennent pas invalides.
 */
#[Constraint(
  id: 'KhartasiaAttestationConcept',
  label: new TranslatableMarkup('Attestation de plante sans concept taxonomique', [], ['context' => 'Validation']),
  type: ['entity']
)]
class AttestationConceptConstraint extends SymfonyConstraint {

  /**
   * Message affiché sur le champ « Concept taxonomique ».
   */
  public string $message = 'Une attestation liée à une plante doit préciser le référentiel taxonomique suivi (POWO, GRIN, Flora of China ou autre).';

}
KH_FIN;

$classes['AttestationConceptConstraintValidator'] = <<<'KH_FIN'
<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide la règle « une attestation liée à une plante doit avoir son concept taxonomique ».
 *
 * Voir AttestationConceptConstraint pour les définitions (concept taxonomique, clause de maintien).
 */
class AttestationConceptConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof FieldableEntityInterface || $entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'attestation') {
      return;
    }
    if (!$entity->hasField('field_att_plante') || !$entity->hasField('field_att_concept_taxo')) {
      return;
    }

    $plantes = $this->cibles($entity, 'field_att_plante');
    if (!$plantes) {
      return;
    }
    $concept = $this->valeur($entity, 'field_att_concept_taxo');
    if ($concept !== '') {
      return;
    }

    // Clause de maintien : même plante et concept déjà vide dans la version enregistrée.
    $original = $this->original($entity);
    if ($original
      && $this->cibles($original, 'field_att_plante') === $plantes
      && $this->valeur($original, 'field_att_concept_taxo') === '') {
      return;
    }

    $this->context->buildViolation($constraint->message)
      ->atPath('field_att_concept_taxo')
      ->addViolation();
  }

  /**
   * Identifiants cibles d'un champ de référence, triés (ordre sans importance).
   */
  private function cibles(FieldableEntityInterface $entite, string $champ): array {
    $ids = [];
    foreach ($entite->get($champ)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    sort($ids);
    return $ids;
  }

  /**
   * Valeur d'un champ simple, chaîne vide si absente.
   */
  private function valeur(FieldableEntityInterface $entite, string $champ): string {
    $item = $entite->get($champ)->getValue();
    return isset($item[0]['value']) ? (string) $item[0]['value'] : '';
  }

  /**
   * Version enregistrée du contenu (NULL pour un contenu nouveau), dans la langue du contenu validé.
   */
  private function original(FieldableEntityInterface $entite) {
    if ($entite->isNew() || !$entite->id()) {
      return NULL;
    }
    $original = \Drupal::entityTypeManager()->getStorage($entite->getEntityTypeId())->loadUnchanged($entite->id());
    if (!$original) {
      return NULL;
    }
    $langue = $entite->language()->getId();
    return $original->hasTranslation($langue) ? $original->getTranslation($langue) : $original;
  }

}
KH_FIN;

$hook = <<<'KH_FIN'
/**
 * Implements hook_entity_type_alter().
 *
 * Enregistre sur les nœuds deux règles de validation (voir src/Plugin/Validation/Constraint) :
 * un nom vernaculaire sans plante doit préciser sa portée ; une attestation liée à une plante doit
 * indiquer son concept taxonomique. Chaque règle ne s'applique qu'à son type de contenu.
 */
function khartasia_core_entity_type_alter(array &$entity_types) {
  if (isset($entity_types['node'])) {
    $entity_types['node']->addConstraint('KhartasiaNomSansPlante');
    $entity_types['node']->addConstraint('KhartasiaAttestationConcept');
  }
}
KH_FIN;

function arreter($msg) { echo "ERREUR : $msg\nAucun fichier n'a été modifié.\n"; exit(1); }
function php_valide($contenu) {
  $tmp = tempnam(sys_get_temp_dir(), 'kh_') . '.php';
  file_put_contents($tmp, $contenu);
  exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
  @unlink($tmp);
  return $code === 0 ? NULL : implode(' ', $sortie);
}

echo "=== Installation des règles de nomenclature — " . ($appliquer ? 'APPLICATION' : 'SIMULATION') . " ===\n";
if (!is_file($module)) { arreter("fichier introuvable : $module (lancez ce script depuis la racine du projet)."); }
$c_module = file_get_contents($module);
$bom = substr($c_module, 0, 3) === "\xEF\xBB\xBF";
if ($bom) { $c_module = substr($c_module, 3); }
$eol = strpos($c_module, "\r\n") !== FALSE ? "\r\n" : "\n";
if (strpos($c_module, 'function khartasia_core_node_presave') === FALSE) { arreter("$module ne contient pas khartasia_core_node_presave : ce n'est pas le fichier attendu."); }
$hook_present = strpos($c_module, 'function khartasia_core_entity_type_alter') !== FALSE;

// --- classes ---
$a_ecrire = [];
echo "\nclasses :\n";
foreach ($classes as $nom => $contenu) {
  $chemin = "$dossier/$nom.php";
  $contenu = rtrim($contenu, "\n") . "\n";
  if (is_file($chemin)) {
    if (file_get_contents($chemin) === $contenu) { echo "  = $nom : déjà installée, identique\n"; continue; }
    arreter("$chemin existe déjà avec un contenu DIFFÉRENT ; je ne l'écrase pas.");
  }
  if (($erreur = php_valide($contenu)) !== NULL) { arreter("la classe $nom n'a pas une syntaxe PHP valide : $erreur"); }
  echo "  + $nom : à créer\n";
  $a_ecrire[$chemin] = $contenu;
}

// --- hook dans le .module ---
echo "\n.module :\n";
$nouveau_module = $c_module;
if ($hook_present) { echo "  = khartasia_core_entity_type_alter existe déjà ; rien à ajouter\n"; }
else {
  $nouveau_module = rtrim($c_module, "\r\n") . $eol . $eol . str_replace("\n", $eol, $hook) . $eol;
  if (($erreur = php_valide($nouveau_module)) !== NULL) { arreter("le .module obtenu n'a pas une syntaxe PHP valide : $erreur"); }
  echo "  + khartasia_core_entity_type_alter à ajouter (syntaxe PHP du résultat : OK)\n";
}

if (!$a_ecrire && $hook_present) { echo "\nRien à faire : tout est déjà installé.\n"; exit(0); }
if (!$appliquer) { echo "\nSimulation : rien n'a été écrit. Relancez avec --appliquer pour appliquer.\n"; exit(0); }

if (!is_dir($dossier_sauvegardes)) { mkdir($dossier_sauvegardes, 0775, TRUE); }
if (!$hook_present) {
  $copie = $dossier_sauvegardes . '/khartasia_core.module.avant_regles_nomenclature_' . date('Ymd_His');
  if (!copy($module, $copie)) { arreter("sauvegarde impossible : $copie"); }
  echo "\nsauvegarde : $copie\n";
}
if ($a_ecrire && !is_dir($dossier)) { mkdir($dossier, 0775, TRUE); }
foreach ($a_ecrire as $chemin => $contenu) { file_put_contents($chemin, $contenu); echo "écrit : $chemin\n"; }
if (!$hook_present) { file_put_contents($module, ($bom ? "\xEF\xBB\xBF" : '') . $nouveau_module); echo "écrit : $module\n"; }
echo "\nFait. Envoyer ces fichiers en production, puis vider les caches (la découverte des règles en dépend).\n";
