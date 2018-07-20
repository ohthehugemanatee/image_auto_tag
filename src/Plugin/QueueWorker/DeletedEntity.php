<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Plugin\QueueWorker;

/**
 * Queue Worker for image_auto_tag_deleted_entity.
 *
 * @package Drupal\image_auto_tag\Plugin\QueueWorker
 *
 * @QueueWorker(
 *   id = "image_auto_tag_deleted_entity",
 *   title = @Translation("Process Person"),
 *   cron = {"time" = 10}
 * )
 */
class DeletedEntity extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($data['entityType'])->load($data['entityId']);
    $this->entityOperations->deleteEntity($entity);
    $entity->save();
  }

}
