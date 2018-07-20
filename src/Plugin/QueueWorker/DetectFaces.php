<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
class DetectFaces extends QueueWorkerBase {

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
