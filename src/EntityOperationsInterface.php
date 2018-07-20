<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;


/**
 * Operations used in entity hook implementations.
 *
 * Because we want to reuse the same code on entity hooks and on cron.
 *
 * @package Drupal\image_auto_tag
 */
interface EntityOperationsInterface {

  /**
   * Do face detection on a given entity and image field, and tag appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The target entity to be tagged.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The image field to use for face detection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function findFacesAndTag(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition): void;

  /**
   * Synchronize a local Person record with the remote service.
   *
   * This is one-way sync only, from local to remote.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The "Person" entity to be synced.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function syncPerson(ContentEntityInterface $entity): void;

  /**
   * Process a deleted entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being deleted.
   */
  public function deleteEntity(ContentEntityInterface $entity): void;
}