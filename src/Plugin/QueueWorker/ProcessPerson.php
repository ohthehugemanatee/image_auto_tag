<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Plugin\QueueWorker;

/**
 * Queue Worker for image_auto_tag_process_person.
 *
 * @package Drupal\image_auto_tag\Plugin\QueueWorker
 *
 * @QueueWorker(
 *   id = "image_auto_tag_process_person",
 *   title = @Translation("Process Person"),
 *   cron = {"time" = 10}
 * )
 */
class ProcessPerson extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($data['entityType'])->load($data['entityId']);
    $this->entityOperations->processPerson($entity);
    $entity->save();
  }

}
