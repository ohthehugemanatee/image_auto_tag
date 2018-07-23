<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Plugin\QueueWorker;

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
