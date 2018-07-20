<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\image_auto_tag\EntityOperationsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue Worker for image_auto_tag_detect_faces.
 *
 * @package Drupal\image_auto_tag\Plugin\QueueWorker
 *
 * @QueueWorker(
 *   id = "image_auto_tag_detect_faces",
 *   title = @Translation("Detect Faces"),
 *   cron = {"time" = 10}
 * )
 */
class DetectFaces extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity Operations service.
   *
   * @var \Drupal\image_auto_tag\EntityOperationsInterface
   */
  protected $entityOperations;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DetectFaces constructor.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $plugin_id
   *   Plugin Id.
   * @param $plugin_definition
   *   Plugin Definition.
   * @param \Drupal\image_auto_tag\EntityOperationsInterface $entityOperations
   *   Entity Operations service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityOperationsInterface $entityOperations, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityOperations = $entityOperations;
    $this->entityTypeManager = $entityTypeManager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('image_auto_tag.entity_operations'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($data['entityType'])->load($data['entityId']);
    $fieldDefinition = $entity->getFieldDefinition($data['fieldName']);
    $this->entityOperations->findFacesAndTag($entity, $fieldDefinition);
    $entity->save();
  }

}
