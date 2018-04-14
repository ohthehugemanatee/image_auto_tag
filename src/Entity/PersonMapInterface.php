<?php

namespace Drupal\media_auto_tag\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for defining Person map entities.
 *
 * @ingroup media_auto_tag
 */
interface PersonMapInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the Person map creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Person map.
   */
  public function getCreatedTime() : int;

  /**
   * Sets the Person map creation timestamp.
   *
   * @param int $timestamp
   *   The Person map creation timestamp.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person map entity.
   */
  public function setCreatedTime($timestamp) : PersonMapInterface;

  /**
   * Get the foreign Id component of the map.
   *
   * @return string
   *   The foreign ID.
   */
  public function getForeignId() : string;

  /**
   * Sets the Person map Foreign Id..
   *
   * @param string $foreignId
   *   The Person map creation timestamp.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person Map entity.
   */
  public function setForeignId($foreignId) : PersonMapInterface;

  /**
   * Get the Id of the referenced Drupal entity.
   *
   * @return string
   *   The Id of the referenced Drupal entity.
   */
  public function getLocalId() : string;

  /**
   * Set the Id of the referenced Drupal entity.
   *
   * @param string $localId
   *   The Id of the referenced Drupal entity.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person Map entity.
   */
  public function setLocalId(string $localId) : PersonMapInterface;

  /**
   * Get the Entity Type Id of the local side of the mapping.
   *
   * @return string
   *   The local entity type Id.
   */
  public function getLocalEntityTypeId() : string;

  /**
   * Set the entity type Id of the referenced Drupal entity.
   *
   * @param string $entityTypeId
   *   The entity type Id of the referenced Drupal entity.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person Map entity.
   */
  public function setLocalEntityTypeId(string $entityTypeId) : PersonMapInterface;

  /**
   * Get the referenced Drupal entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Drupal entity referenced by the map.
   */
  public function getLocalEntity() : EntityInterface;

}
