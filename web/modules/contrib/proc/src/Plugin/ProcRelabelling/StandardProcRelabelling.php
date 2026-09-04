<?php

namespace Drupal\proc\Plugin\ProcRelabelling;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\proc\Attribute\ProcRelabelling;
use Drupal\proc\ProcRelabellingBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides relabelling with a standard pattern.
 */
#[ProcRelabelling(
  id: 'proc_standard_relabelling',
  description: new TranslatableMarkup('Standard Relabelling'),
)]
class StandardProcRelabelling extends ProcRelabellingBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $translation) {
    $this->setStringTranslation($translation);
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function relabel(array $metadata): string {
    $hostEntity = $metadata['host_entity'];
    $hostEntityLabel = $metadata['host_entity']->label();
    $procId = $metadata['proc_id'];
    $referencedEntity = $hostEntity->get($metadata['proc_field'])->referencedEntities()[$metadata['proc_key']] ?? FALSE;
    if ($referencedEntity) {
      $authorLabel = $referencedEntity->getOwner()->label();
      $encryptionDateTime = DrupalDateTime::createFromTimestamp((int) $referencedEntity->getMeta()[0]['generation_timestamp'])->format('d/m/Y - H:i:s T');
      $standardLabel = $hostEntityLabel . '_' . $procId . ' | ' . $this->t(
        'Encrypted by %authorLabel on %encryptionDateTime', [
          '%authorLabel' => $authorLabel,
          '%encryptionDateTime' => $encryptionDateTime,
        ]);
      if ($referencedEntity->label() != $standardLabel) {
        $referencedEntity->set('label', $standardLabel);
        return $referencedEntity->save();
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->t('Standard Relabelling Pattern: [host_entity_label]_[proc_id] | Encrypted by [author_label] on [encryption_date_time]');
  }

}
