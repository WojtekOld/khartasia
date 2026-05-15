<?php

namespace Drupal\bibcite_export\Plugin\Action;

use Drupal\bibcite\Plugin\BibciteFormatInterface;
use Drupal\bibcite\Plugin\BibciteFormatManagerInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Export multiple references.
 *
 * @Action(
 *   id = "bibcite_export_multiple_vbo",
 *   label = @Translation("Download Selected Citations"),
 *   type = "bibcite_reference",
 *   confirm = TRUE,
 * )
 */
class ExportReferenceVBO extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Bibcite format manager service.
   *
   * @var \Drupal\bibcite\Plugin\BibciteFormatManagerInterface
   */
  protected $formatManager;

  /**
   * Construct new ExportReferenceVBO object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render object.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\bibcite\Plugin\BibciteFormatManagerInterface $format_manager
   *   The bibcite format manager.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serialize to use.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              RendererInterface $renderer,
                              StreamWrapperManagerInterface $streamWrapperManager,
                              BibciteFormatManagerInterface $format_manager,
                              SerializerInterface $serializer,
                              EntityTypeManagerInterface $manager,
                              AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->formatManager = $format_manager;
    $this->serializer = $serializer;
    $this->entityTypeManager = $manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('stream_wrapper_manager'),
      $container->get('plugin.manager.bibcite_format'),
      $container->get('serializer'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Output generated string to file. Message user.
   *
   * @param string $output
   *   The string that will be saved to a file.
   */
  protected function sendToFile($output) {
    if (!empty($output)) {
      $rand = substr(hash('ripemd160', uniqid()), 0, 8);
      $filename = 'bibtex' . '_' . date('Y_m_d_H_i', \Drupal::time()->getRequestTime()) . '-' . $rand . '.' . 'bibtex';

      $wrappers = $this->streamWrapperManager->getWrappers();
      if (isset($wrappers['private'])) {
        $wrapper = 'private';
      }
      else {
        $wrapper = 'public';
      }

      $wrapper = 'public';
      $destination = $wrapper . '://' . $filename;
      $file = \Drupal::service('file.repository')->writeData($output, $destination, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
      $file->setTemporary();
      $file->save();
      $file_url = \Drupal::service('file_url_generator')->generate($file->getFileUri());
      $link = Link::fromTextAndUrl($this->t('Click here'), $file_url);
      $this->messenger()->addStatus($this->t('Export file created, @link to download.', [
        '@link' => $link->toString(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\bibcite_entity\Entity\ReferenceInterface $entity */
    $this->executeMultiple([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $format = $this->formatManager->createInstance('bibtex');
    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $ids = [];

    /** @var \Drupal\bibcite_entity\Entity\ReferenceInterface $entity */
    foreach ($entities as $entity) {
      $ids[] = $entity->id();
    }

    $references = $storage->loadMultiple($ids);
    $output = $this->generateOutput($references, $format);
    $this->sendToFile($output);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $account->hasPermission('access bibcite export') && $object->access('view', $account, $return_as_object);
  }

  /**
   * Serialize and create response contains file in selected format.
   *
   * @param array $entities
   *   Array of entities objects.
   * @param \Drupal\bibcite\Plugin\BibciteFormatInterface $bibcite_format
   *   Instance of format plugin.
   */
  protected function generateOutput(array $entities, BibciteFormatInterface $bibcite_format) {
    $result = $this->serializer->serialize($entities, $bibcite_format->getPluginId());
    $result = is_array($result) ? implode("\n", $result) : $result;

    return $result;
  }

}
